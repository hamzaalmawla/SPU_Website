<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Middleware\ShortenUploadedFileNames;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ShortenUploadedFileNamesTest extends TestCase
{
    public function test_a_long_arabic_name_is_cut_so_livewires_temporary_name_fits(): void
    {
        $long = 'الجدول 4 الحدود الدنيا لتغيير القيد - الثانوية العامة الفرع العلمي حسب مصدر الشهادة 2026-2027.md';
        $short = ShortenUploadedFileNames::shortName($long);

        $this->assertStringEndsWith('.md', $short);
        $this->assertStringStartsWith('الجدول 4 الحدود الدنيا', $short);
        $this->assertTrue(mb_check_encoding($short, 'UTF-8'));
        // Livewire: 30 random + "-meta" + base64(name) + "-" + "." + extension.
        $this->assertLessThanOrEqual(255, 30 + 5 + strlen(base64_encode($short)) + 2 + strlen('md'));
        $this->assertSame('دليل القبول.pdf', ShortenUploadedFileNames::shortName('دليل القبول.pdf'));
    }

    public function test_the_upload_request_carries_the_short_name_and_the_same_file(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, 'content');
        $long = str_repeat('إعلان مفاضلة ', 20).'.pdf';

        $request = Request::create('/livewire/upload-file', 'POST', [], [], ['files' => [new UploadedFile($path, $long, 'application/pdf', null, true)]]);

        $seen = null;
        (new ShortenUploadedFileNames)->handle($request, function (Request $request) use (&$seen): Response {
            $seen = $request->file('files')[0];

            return new Response('ok');
        });

        $this->assertInstanceOf(UploadedFile::class, $seen);
        $this->assertSame(ShortenUploadedFileNames::shortName($long), $seen->getClientOriginalName());
        $this->assertNotSame($long, $seen->getClientOriginalName());
        $this->assertSame('content', $seen->getContent());
        @unlink($path);
    }
}
