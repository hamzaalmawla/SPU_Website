<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Reads the text of a knowledge file: PDF, Word (.docx), plain text,
 * Markdown or CSV.
 *
 * Returns the text in reading order as sections. A PDF gives one section per
 * page (so answers can cite the page); other formats give one section.
 * Headings are kept as Markdown "#" lines so the chunker can label passages.
 *
 * Failure messages are translation keys under admin_chatbot.errors.*, safe to
 * show an editor. Nothing here touches the database.
 */
final class DocumentTextExtractor
{
    private const MAX_ZIP_ENTRIES = 2000;

    private const MAX_XML_BYTES = 40 * 1024 * 1024;

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return list<array{page: ?int, text: string}>
     *
     * @throws InvalidArgumentException
     */
    public static function extract(string $path, string $extension): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('admin_chatbot.errors.unreadable');
        }

        $sections = match (strtolower($extension)) {
            'pdf' => self::pdf($path),
            'docx' => [['page' => null, 'text' => self::docx($path)]],
            'txt', 'md' => [['page' => null, 'text' => self::plain((string) file_get_contents($path))]],
            'csv' => [['page' => null, 'text' => self::csv($path)]],
            default => throw new InvalidArgumentException('admin_chatbot.errors.unsupported_type'),
        };

        $sections = array_values(array_filter(
            array_map(static fn (array $section): array => ['page' => $section['page'], 'text' => self::clean($section['text'])], $sections),
            static fn (array $section): bool => $section['text'] !== '',
        ));

        if ($sections === []) {
            throw new InvalidArgumentException($extension === 'pdf' ? 'admin_chatbot.errors.no_text_pdf' : 'admin_chatbot.errors.no_text');
        }

        return $sections;
    }

    /**
     * Normalize text from any source: Unicode NFKC (which also turns Arabic
     * presentation forms from PDFs back into ordinary letters), no tatweel,
     * no control characters, tidy whitespace.
     */
    public static function clean(string $text): string
    {
        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1256');
            $text = is_string($converted) ? $converted : '';
        }

        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_KC);
            $text = is_string($normalized) ? $normalized : $text;
        }

        $text = str_replace(["\u{0640}", "\u{FEFF}", "\u{200B}", "\r\n", "\r"], ['', '', '', "\n", "\n"], $text);
        $text = (string) preg_replace('/[^\P{C}\n\t]+/u', ' ', $text);
        $text = (string) preg_replace('/[ \t\x{00A0}]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /** @return list<array{page: ?int, text: string}> */
    private static function pdf(string $path): array
    {
        try {
            $config = new PdfConfig;
            $config->setRetainImageContent(false);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);
            $pdf = (new PdfParser([], $config))->parseFile($path);

            $sections = [];
            foreach ($pdf->getPages() as $index => $page) {
                $sections[] = ['page' => $index + 1, 'text' => $page->getText()];
            }

            return $sections;
        } catch (Throwable $exception) {
            $message = strtolower($exception->getMessage());

            throw new InvalidArgumentException(
                str_contains($message, 'secured') || str_contains($message, 'encrypt')
                    ? 'admin_chatbot.errors.pdf_encrypted'
                    : 'admin_chatbot.errors.pdf_unreadable'
            );
        }
    }

    private static function docx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('admin_chatbot.errors.docx_unreadable');
        }

        try {
            $stat = $zip->statName('word/document.xml');
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES || $stat === false || $stat['size'] > self::MAX_XML_BYTES) {
                throw new InvalidArgumentException('admin_chatbot.errors.docx_unreadable');
            }
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || $xml === '') {
            throw new InvalidArgumentException('admin_chatbot.errors.docx_unreadable');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) || $document->doctype !== null) {
                throw new InvalidArgumentException('admin_chatbot.errors.docx_unreadable');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);

        $lines = [];
        foreach ($xpath->query('/w:document/w:body/*') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if ($element->localName === 'p') {
                $text = self::wordText($xpath, $element);
                if ($text === '') {
                    continue;
                }

                $level = self::headingLevel($xpath, $element);
                $lines[] = $level !== null ? str_repeat('#', $level).' '.$text : $text;
            } elseif ($element->localName === 'tbl') {
                foreach ($xpath->query('./w:tr', $element) ?: [] as $row) {
                    if (! $row instanceof DOMElement) {
                        continue;
                    }

                    $cells = [];
                    foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                        if ($cell instanceof DOMElement) {
                            $cells[] = self::wordText($xpath, $cell);
                        }
                    }

                    $cells = array_values(array_filter($cells, static fn (string $cell): bool => $cell !== ''));
                    if ($cells !== []) {
                        $lines[] = '| '.implode(' | ', $cells).' |';
                    }
                }
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private static function wordText(DOMXPath $xpath, DOMElement $element): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $element) ?: [] as $node) {
            $parts[] = match ($node->localName) {
                't' => $node->textContent,
                default => ' ',
            };
        }

        return trim((string) preg_replace('/\s+/u', ' ', implode('', $parts)));
    }

    private static function headingLevel(DOMXPath $xpath, DOMElement $paragraph): ?int
    {
        $style = $xpath->query('./w:pPr/w:pStyle/@w:val', $paragraph)?->item(0)?->nodeValue ?? '';
        if (preg_match('/^(?:heading|berschrift|titre|العنوان)\s*([1-6])$/iu', str_replace(['-', '_'], '', (string) $style), $matches) === 1) {
            return (int) $matches[1];
        }

        return strcasecmp((string) $style, 'Title') === 0 ? 1 : null;
    }

    private static function plain(string $text): string
    {
        // Drop a UTF-8 byte-order mark; clean() converts other encodings.
        return str_starts_with($text, "\xEF\xBB\xBF") ? substr($text, 3) : $text;
    }

    private static function csv(string $path): string
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('admin_chatbot.errors.unreadable');
        }

        $lines = [];
        $header = null;
        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $row = array_map(static fn (mixed $cell): string => trim(self::plain((string) $cell)), $row);
                if (implode('', $row) === '') {
                    continue;
                }

                if ($header === null) {
                    $header = $row;
                    $lines[] = '| '.implode(' | ', $row).' |';

                    continue;
                }

                // "Column: value" per row reads well for the model and keeps
                // each row's meaning inside whichever passage it lands in.
                $pairs = [];
                foreach ($row as $index => $cell) {
                    if ($cell !== '') {
                        $pairs[] = (($header[$index] ?? '') !== '' ? $header[$index].': ' : '').$cell;
                    }
                }
                $lines[] = implode(' ; ', $pairs);
            }
        } finally {
            fclose($handle);
        }

        return implode("\n", $lines);
    }
}
