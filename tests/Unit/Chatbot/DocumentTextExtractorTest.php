<?php

declare(strict_types=1);

namespace Tests\Unit\Chatbot;

use App\Support\Chatbot\DocumentTextExtractor;
use App\Support\Chatbot\PdfLayoutText;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\KnowledgeFixtures;
use ZipArchive;

final class DocumentTextExtractorTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    public function test_plain_text_is_cleaned(): void
    {
        $path = $this->file('txt', "\xEF\xBB\xBFالقبول\u{0640}\u{0640} في   الجامعة\r\n\r\n\r\n\r\nسطر\u{200B} ثانٍ");

        $sections = DocumentTextExtractor::extract($path, 'txt');

        $this->assertSame([['page' => null, 'text' => "القبول في الجامعة\n\nسطر ثانٍ"]], $sections);
    }

    public function test_arabic_presentation_forms_from_pdfs_become_ordinary_letters(): void
    {
        // "سلام" written with presentation-form glyphs, as many PDF exporters do.
        $this->assertSame('سلام', DocumentTextExtractor::clean("\u{FEB3}\u{FEFC}\u{FEE1}"));
    }

    public function test_csv_rows_read_as_column_value_pairs(): void
    {
        $path = $this->file('csv', "Faculty,Annual fee\nPharmacy,\"4,500,000\"\nDentistry,5000000\n");

        $text = DocumentTextExtractor::extract($path, 'csv')[0]['text'];

        $this->assertStringContainsString('| Faculty | Annual fee |', $text);
        $this->assertStringContainsString('Faculty: Pharmacy ; Annual fee: 4,500,000', $text);
        $this->assertStringContainsString('Faculty: Dentistry ; Annual fee: 5000000', $text);
    }

    public function test_docx_keeps_headings_paragraphs_and_tables_in_order(): void
    {
        $path = $this->docx(
            '<w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Admission</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t xml:space="preserve">Apply </w:t></w:r><w:r><w:t>online.</w:t></w:r></w:p>'
            .'<w:tbl><w:tr><w:tc><w:p><w:r><w:t>Faculty</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Minimum</w:t></w:r></w:p></w:tc></w:tr>'
            .'<w:tr><w:tc><w:p><w:r><w:t>Medicine</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>230</w:t></w:r></w:p></w:tc></w:tr></w:tbl>'
            .'<w:p><w:r><w:t>Ends here.</w:t></w:r></w:p>'
        );

        $text = DocumentTextExtractor::extract($path, 'docx')[0]['text'];

        $this->assertSame("# Admission\nApply online.\n| Faculty | Minimum |\nFaculty: Medicine ; Minimum: 230\n\nEnds here.", $text);
    }

    public function test_pdf_text_is_read_page_by_page(): void
    {
        $path = $this->file('pdf', $this->pdf(['Admission opens in July.', 'Tuition is paid per semester.']));

        $sections = DocumentTextExtractor::extract($path, 'pdf');

        $this->assertCount(2, $sections);
        $this->assertSame(1, $sections[0]['page']);
        $this->assertStringContainsString('Admission opens in July.', $sections[0]['text']);
        $this->assertSame(2, $sections[1]['page']);
        $this->assertStringContainsString('Tuition is paid per semester.', $sections[1]['text']);
    }

    public function test_arabic_drawn_in_visual_order_is_read_in_reading_order_with_cells(): void
    {
        // Excel's "Save as PDF" draws Arabic glyph by glyph from left to
        // right: in stream order every word is backwards and cells run on.
        $path = $this->file('pdf', KnowledgeFixtures::visualOrderArabicPdf([['الجدول 2026', '80'], ['طب الجامعة', 'الخاصة']]));

        $text = DocumentTextExtractor::extract($path, 'pdf')[0]['text'];

        $this->assertSame("الجدول 2026 | 80\nطب الجامعة | الخاصة", $text);
    }

    public function test_the_reading_order_score_tells_reversed_arabic_apart(): void
    {
        $this->assertGreaterThan(0, PdfLayoutText::arabicOrderScore('الجامعة السورية الخاصة في ريف دمشق'));
        $this->assertLessThan(0, PdfLayoutText::arabicOrderScore('ةعماجلا ةيروسلا ةصاخلا يف فير قشمد'));
        $this->assertSame(0, PdfLayoutText::arabicOrderScore('Admission opens in July.'));
    }

    public function test_scanned_pdf_pages_are_reported_with_their_page_images(): void
    {
        $path = $this->file('pdf', KnowledgeFixtures::scannedPdf(3));

        $read = DocumentTextExtractor::read($path, 'pdf');

        $this->assertSame([1, 2, 3], $read['scans']);
        // The scanner app's watermark is not kept as text.
        $this->assertSame(['', '', ''], array_column($read['sections'], 'text'));

        $images = DocumentTextExtractor::pageImages($path, 'pdf', [2, 3]);
        $this->assertSame([2, 3], array_keys($images));
        $this->assertSame('image/jpeg', $images[2][0]->mime);
        $this->assertSame("\xFF\xD8", substr($images[2][0]->bytes, 0, 2));

        // extract() keeps refusing a file with nothing but scans.
        $this->expectExceptionMessage('admin_chatbot.errors.no_text_pdf');
        DocumentTextExtractor::extract($path, 'pdf');
    }

    public function test_a_text_pdf_has_no_scans_and_an_image_upload_is_one(): void
    {
        $this->assertSame([], DocumentTextExtractor::read($this->file('pdf', $this->pdf(['Admission opens in July.'])), 'pdf')['scans']);

        $photo = $this->file('jpg', KnowledgeFixtures::jpeg());
        $this->assertSame(['sections' => [['page' => 1, 'text' => '']], 'scans' => [1]], DocumentTextExtractor::read($photo, 'jpg'));
        $this->assertSame('image/jpeg', DocumentTextExtractor::pageImages($photo, 'jpg', [1])[1][0]->mime);

        $this->expectExceptionMessage('admin_chatbot.errors.image_unreadable');
        DocumentTextExtractor::read($this->file('png', 'not an image'), 'png');
    }

    public function test_unreadable_files_fail_with_a_translation_key(): void
    {
        foreach ([['pdf', 'not a pdf', 'admin_chatbot.errors.pdf_unreadable'], ['docx', 'not a zip', 'admin_chatbot.errors.docx_unreadable'], ['txt', '   ', 'admin_chatbot.errors.no_text'], ['exe', 'MZ', 'admin_chatbot.errors.unsupported_type']] as [$extension, $content, $key]) {
            try {
                DocumentTextExtractor::extract($this->file($extension, $content), $extension);
                $this->fail("{$extension} should not be accepted");
            } catch (InvalidArgumentException $exception) {
                $this->assertSame($key, $exception->getMessage());
            }
        }
    }

    private function file(string $extension, string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chatbot').'.'.$extension;
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    private function docx(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chatbot').'.docx';
        $this->files[] = $path;
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'.$body.'</w:body></w:document>');
        $zip->close();

        return $path;
    }

    /**
     * A minimal valid PDF with one line of Helvetica text per page.
     *
     * @param  list<string>  $pages
     */
    private function pdf(array $pages): string
    {
        $objects = [];
        $pageIds = [];
        $next = 4;
        foreach ($pages as $text) {
            $stream = 'BT /F1 18 Tf 72 720 Td ('.addcslashes($text, '()\\').') Tj ET';
            $objects[$next] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents '.($next + 1).' 0 R /Resources << /Font << /F1 3 0 R >> >> >>';
            $objects[$next + 1] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $pageIds[] = $next;
            $next += 2;
        }
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(static fn (int $id): string => $id.' 0 R', $pageIds)).'] /Count '.count($pageIds).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
