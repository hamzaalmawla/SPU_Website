<?php

declare(strict_types=1);

namespace App\Support\Chatbot;

use App\DTOs\Chatbot\ChatbotScanImageDTO;
use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use Smalot\PdfParser\Config as PdfConfig;
use Smalot\PdfParser\Document as PdfDocument;
use Smalot\PdfParser\Page as PdfPage;
use Smalot\PdfParser\Parser as PdfParser;
use Smalot\PdfParser\XObject\Image as PdfImage;
use Throwable;
use ZipArchive;

/**
 * Reads the text of a knowledge file: PDF, Word (.docx), Excel (.xlsx),
 * plain text, Markdown or CSV, and finds the pages that are scans (page
 * images with no text layer), which ChatbotScanReader reads instead.
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

    /** File types read from page images by a vision model (see ChatbotScanReader). */
    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    /** A PDF page with fewer letters than this, and a page-sized image, is a scan. */
    private const SCAN_MAX_LETTERS = 40;

    /** The smallest image side (pixels) that can hold a page of text. */
    private const SCAN_MIN_SIDE = 400;

    /**
     * @return list<array{page: ?int, text: string}>
     *
     * @throws InvalidArgumentException
     */
    public static function extract(string $path, string $extension): array
    {
        $read = self::read($path, $extension);
        $sections = array_values(array_filter($read['sections'], static fn (array $section): bool => $section['text'] !== ''));

        if ($sections === []) {
            throw new InvalidArgumentException(strtolower($extension) === 'pdf' ? 'admin_chatbot.errors.no_text_pdf' : 'admin_chatbot.errors.no_text');
        }

        return $sections;
    }

    /**
     * The file's text, plus the pages that are images of text (scans) and
     * need a vision model. Unlike extract(), a file of scans alone is not an
     * error, and a scanned page keeps its (empty) section.
     *
     * @return array{sections: list<array{page: ?int, text: string}>, scans: list<int>}
     *
     * @throws InvalidArgumentException
     */
    public static function read(string $path, string $extension): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('admin_chatbot.errors.unreadable');
        }

        $extension = strtolower($extension);
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            if (self::imageInfo((string) file_get_contents($path)) === null) {
                throw new InvalidArgumentException('admin_chatbot.errors.image_unreadable');
            }

            return ['sections' => [['page' => 1, 'text' => '']], 'scans' => [1]];
        }

        $scans = [];
        $sections = match ($extension) {
            'pdf' => self::pdf($path, $scans),
            'docx' => [['page' => null, 'text' => self::docx($path)]],
            'txt', 'md' => [['page' => null, 'text' => self::plain((string) file_get_contents($path))]],
            'csv' => [['page' => null, 'text' => self::csv($path)]],
            'xlsx' => [['page' => null, 'text' => SpreadsheetText::xlsx($path)]],
            default => throw new InvalidArgumentException('admin_chatbot.errors.unsupported_type'),
        };

        // Markdown and Word tables: each row keeps its column names.
        $sections = array_map(
            static fn (array $section): array => ['page' => $section['page'], 'text' => TableRows::expandMarkdown(self::clean($section['text']))],
            $sections,
        );

        $scans = array_values(array_filter($scans, static function (int $page) use ($sections): bool {
            foreach ($sections as $section) {
                if ($section['page'] === $page) {
                    return (int) preg_match_all('/\p{L}/u', $section['text']) < self::SCAN_MAX_LETTERS;
                }
            }

            return false;
        }));

        // A scanned page's stray text (a scanner app's watermark) is noise.
        foreach ($sections as $index => $section) {
            if (in_array($section['page'], $scans, true)) {
                $sections[$index]['text'] = '';
            }
        }

        if ($scans === [] && array_filter($sections, static fn (array $section): bool => $section['text'] !== '') === []) {
            throw new InvalidArgumentException($extension === 'pdf' ? 'admin_chatbot.errors.no_text_pdf' : 'admin_chatbot.errors.no_text');
        }

        return ['sections' => array_values($sections), 'scans' => $scans];
    }

    /**
     * The images of the given scanned pages: for a PDF, the page-sized
     * images drawn on each page (largest first, at most three); for an image
     * file, the file itself as page 1. A page whose images are in a format
     * vision models cannot take (JBIG2, CCITT, JPEG 2000) is left out.
     *
     * @param  list<int>  $pages
     * @return array<int, list<ChatbotScanImageDTO>>
     *
     * @throws InvalidArgumentException
     */
    public static function pageImages(string $path, string $extension, array $pages): array
    {
        $extension = strtolower($extension);
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            $bytes = (string) file_get_contents($path);
            $info = self::imageInfo($bytes);

            return in_array(1, $pages, true) && $info !== null ? [1 => [new ChatbotScanImageDTO($info['mime'], $bytes)]] : [];
        }

        if ($extension !== 'pdf') {
            return [];
        }

        $wanted = array_flip($pages);
        $images = [];
        foreach (self::parsePdf($path, true)->getPages() as $index => $page) {
            $number = $index + 1;
            if (! isset($wanted[$number])) {
                continue;
            }

            $found = [];
            $seen = [];
            foreach (self::pageXObjects($page) as $xObject) {
                $image = self::scanImage($xObject);
                if ($image === null) {
                    continue;
                }
                $hash = sha1($image['dto']->bytes);
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
                $found[] = $image;
            }

            usort($found, static fn (array $a, array $b): int => $b['area'] <=> $a['area']);
            $found = array_slice($found, 0, 3);
            if ($found !== []) {
                $images[$number] = array_map(static fn (array $image): ChatbotScanImageDTO => $image['dto'], $found);
            }
        }

        return $images;
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

    /**
     * @param  list<int>  $scans  filled with pages that hold a page-sized image
     * @return list<array{page: ?int, text: string}>
     */
    private static function pdf(string $path, array &$scans): array
    {
        $pdf = self::parsePdf($path, false);

        $sections = [];
        foreach ($pdf->getPages() as $index => $page) {
            try {
                $text = PdfLayoutText::bestText($page, $page->getText());
            } catch (Throwable) {
                $text = '';
            }
            $sections[] = ['page' => $index + 1, 'text' => $text];

            if ((int) preg_match_all('/\p{L}/u', $text) < self::SCAN_MAX_LETTERS && self::hasPageImage($page)) {
                $scans[] = $index + 1;
            }
        }

        return $sections;
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function parsePdf(string $path, bool $withImages): PdfDocument
    {
        try {
            $config = new PdfConfig;
            $config->setRetainImageContent($withImages);
            $config->setDecodeMemoryLimit(64 * 1024 * 1024);

            return (new PdfParser([], $config))->parseFile($path);
        } catch (Throwable $exception) {
            $message = strtolower($exception->getMessage());

            throw new InvalidArgumentException(
                str_contains($message, 'secured') || str_contains($message, 'encrypt')
                    ? 'admin_chatbot.errors.pdf_encrypted'
                    : 'admin_chatbot.errors.pdf_unreadable'
            );
        }
    }

    /**
     * @return list<PdfImage>
     */
    private static function pageXObjects(PdfPage $page): array
    {
        try {
            return array_values(array_filter($page->getXObjects(), static fn (mixed $object): bool => $object instanceof PdfImage));
        } catch (Throwable) {
            return [];
        }
    }

    private static function hasPageImage(PdfPage $page): bool
    {
        foreach (self::pageXObjects($page) as $image) {
            [$width, $height] = self::declaredSize($image);
            if (min($width, $height) >= self::SCAN_MIN_SIDE) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function declaredSize(PdfImage $image): array
    {
        return [(int) self::detail($image, 'Width'), (int) self::detail($image, 'Height')];
    }

    /**
     * A value from an image's dictionary as plain text ("" when missing).
     * Indirect values (a width stored in its own object) come back as a
     * one-item list, names as strings, lists joined with spaces.
     */
    private static function detail(PdfImage $image, string $key): string
    {
        try {
            $value = $image->getHeader()->getDetails()[$key] ?? '';
        } catch (Throwable) {
            return '';
        }

        if (! is_array($value)) {
            return is_scalar($value) ? (string) $value : '';
        }

        $flat = [];
        array_walk_recursive($value, static function (mixed $item) use (&$flat): void {
            if (is_scalar($item)) {
                $flat[] = (string) $item;
            }
        });

        return implode(' ', $flat);
    }

    /**
     * A page image as JPEG or PNG bytes, or null when it is too small to be
     * a page or in a format a vision model cannot take.
     *
     * @return array{dto: ChatbotScanImageDTO, area: int}|null
     */
    private static function scanImage(PdfImage $image): ?array
    {
        [$width, $height] = self::declaredSize($image);
        if (min($width, $height) < self::SCAN_MIN_SIDE) {
            return null;
        }

        try {
            $content = $image->getContent();
        } catch (Throwable) {
            return null;
        }
        $filter = strtolower(self::detail($image, 'Filter'));

        if (! is_string($content) || $content === '') {
            return null;
        }

        $info = self::imageInfo($content);
        if ($info !== null) {
            return ['dto' => new ChatbotScanImageDTO($info['mime'], $content), 'area' => $info['width'] * $info['height']];
        }

        // Decoded samples (FlateDecode): wrap them in a PNG.
        if (str_contains($filter, 'jbig2') || str_contains($filter, 'ccitt') || str_contains($filter, 'jpx')) {
            return null;
        }

        $png = self::png($image, $content, $width, $height);

        return $png !== null ? ['dto' => new ChatbotScanImageDTO('image/png', $png), 'area' => $width * $height] : null;
    }

    /**
     * Raw 8-bit (or 1-bit) grey or RGB samples as a PNG. PNG wants each row
     * to start with a filter byte; samples written with the PNG predictor
     * already have one.
     */
    private static function png(PdfImage $image, string $samples, int $width, int $height): ?string
    {
        $bits = (int) (self::detail($image, 'BitsPerComponent') ?: 8);
        $space = strtolower(self::detail($image, 'ColorSpace'));

        $channels = str_contains($space, 'rgb') ? 3 : (str_contains($space, 'gray') || $space === '' ? 1 : 0);
        if ($channels === 0 || ! in_array($bits, [1, 2, 4, 8], true) || ($channels === 3 && $bits !== 8)) {
            return null;
        }

        $rowBytes = (int) ceil($width * $channels * $bits / 8);
        $length = strlen($samples);
        if ($length === $height * ($rowBytes + 1)) {
            $rows = $samples;
        } elseif ($length === $height * $rowBytes) {
            $rows = '';
            for ($row = 0; $row < $height; $row++) {
                $rows .= "\x00".substr($samples, $row * $rowBytes, $rowBytes);
            }
        } else {
            return null;
        }

        $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $compressed = gzcompress($rows, 6);
        if ($compressed === false) {
            return null;
        }

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, $bits, $channels === 3 ? 2 : 0, 0, 0, 0))
            .$chunk('IDAT', $compressed)
            .$chunk('IEND', '');
    }

    /**
     * @return array{mime: string, width: int, height: int}|null
     */
    private static function imageInfo(string $bytes): ?array
    {
        if ($bytes === '') {
            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if (! is_array($info) || ! in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        return ['mime' => (string) $info['mime'], 'width' => (int) $info[0], 'height' => (int) $info[1]];
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
