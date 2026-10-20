<?php

declare(strict_types=1);

namespace App\Support\StudentLists;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Reads the body of a Word (.docx) file as plain paragraphs and tables.
 *
 * Only `word/document.xml` is read. The archive is refused when that part is
 * missing, larger than MAX_XML_BYTES uncompressed (zip bombs), or declares a
 * DTD; the XML is parsed without network access or entity substitution.
 */
final class DocxTableReader
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const MAX_XML_BYTES = 20 * 1024 * 1024;

    private const MAX_ENTRIES = 2000;

    /**
     * @return array{paragraphs: list<string>, tables: list<list<list<array{text: string, span: int, continuesAbove: bool}>>>}
     *
     * @throws InvalidArgumentException when the file is not a readable .docx
     */
    public static function read(string $path): array
    {
        $xml = self::documentXml($path);

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);

            if (! $loaded || $document->doctype !== null) {
                throw new InvalidArgumentException('student_lists.errors.not_docx');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);

        $paragraphs = [];
        $tables = [];

        foreach ($xpath->query('/w:document/w:body/*') ?: [] as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if ($element->localName === 'p') {
                $text = self::text($xpath, $element);

                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            } elseif ($element->localName === 'tbl') {
                $tables[] = self::table($xpath, $element);
            }
        }

        return ['paragraphs' => $paragraphs, 'tables' => $tables];
    }

    private static function documentXml(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException('student_lists.errors.not_docx');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('student_lists.errors.not_docx');
        }

        try {
            if ($zip->numFiles > self::MAX_ENTRIES) {
                throw new InvalidArgumentException('student_lists.errors.not_docx');
            }

            $stat = $zip->statName('word/document.xml');

            if ($stat === false || $stat['size'] > self::MAX_XML_BYTES) {
                throw new InvalidArgumentException('student_lists.errors.not_docx');
            }

            $xml = $zip->getFromName('word/document.xml', self::MAX_XML_BYTES);

            if (! is_string($xml) || $xml === '') {
                throw new InvalidArgumentException('student_lists.errors.not_docx');
            }

            return $xml;
        } finally {
            $zip->close();
        }
    }

    /** @return list<list<array{text: string, span: int, continuesAbove: bool}>> */
    private static function table(DOMXPath $xpath, DOMElement $table): array
    {
        $rows = [];

        foreach ($xpath->query('./w:tr', $table) ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($xpath->query('./w:tc', $row) ?: [] as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }

                $span = $xpath->query('./w:tcPr/w:gridSpan', $cell)?->item(0);
                $merge = $xpath->query('./w:tcPr/w:vMerge', $cell)?->item(0);

                $cells[] = [
                    'text' => self::text($xpath, $cell),
                    'span' => $span instanceof DOMElement ? max(1, (int) $span->getAttributeNS(self::WORD_NS, 'val')) : 1,
                    // A vMerge without val="restart" continues the cell above it.
                    'continuesAbove' => $merge instanceof DOMElement && $merge->getAttributeNS(self::WORD_NS, 'val') !== 'restart',
                ];
            }

            $rows[] = $cells;
        }

        return $rows;
    }

    /** Text of an element: runs joined, tabs and breaks as spaces, whitespace collapsed. */
    private static function text(DOMXPath $xpath, DOMElement $element): string
    {
        $parts = [];

        foreach ($xpath->query('.//w:t | .//w:tab | .//w:br | .//w:p', $element) ?: [] as $node) {
            $parts[] = $node instanceof DOMElement && $node->localName === 't' ? $node->textContent : ' ';
        }

        $text = preg_replace('/\s+/u', ' ', implode('', $parts));

        return trim(is_string($text) ? $text : '');
    }
}
