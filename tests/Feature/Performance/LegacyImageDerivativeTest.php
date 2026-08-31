<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Contracts\Media\LegacyImageDerivativeServiceInterface;
use App\Support\MediaUrlResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The legacy tree holds original camera JPEGs that were never resized for the
 * web, on a read-only mount. Derivatives are generated offline onto the public
 * disk; if one is missing the page must still render the original.
 */
final class LegacyImageDerivativeTest extends TestCase
{
    use RefreshDatabase;

    private string $sourcePath = 'downloads/files/derivative-fixture.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        MediaUrlResolver::flushLegacyDerivativeManifest();
        $this->clearDerivatives();
    }

    protected function tearDown(): void
    {
        $this->clearDerivatives();
        $this->removeFixture();
        MediaUrlResolver::flushLegacyDerivativeManifest();

        parent::tearDown();
    }

    private function clearDerivatives(): void
    {
        Storage::disk('public')->deleteDirectory(MediaUrlResolver::DERIVATIVE_DIRECTORY);
    }

    private function removeFixture(): void
    {
        $absolute = public_path($this->sourcePath);

        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    /**
     * Write a wide JPEG into the legacy directory so the generator has real
     * pixels to work with.
     */
    private function writeFixture(int $width = 2000, int $height = 1200): void
    {
        $absolute = public_path($this->sourcePath);
        $directory = dirname($absolute);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 10) {
            $colour = imagecolorallocate($image, ($x * 7) % 255, ($x * 3) % 255, ($x * 11) % 255);
            imagefilledrectangle($image, $x, 0, $x + 10, $height, $colour);
        }

        imagejpeg($image, $absolute, 95);
        imagedestroy($image);
    }

    public function test_resolver_falls_back_to_the_original_when_no_derivative_exists(): void
    {
        $this->assertSame(
            '/downloads/files/never-generated.jpg',
            MediaUrlResolver::resolveLegacy('downloads/files/never-generated.jpg'),
        );

        $this->assertNull(MediaUrlResolver::legacySrcset('downloads/files/never-generated.jpg'));
    }

    public function test_resolver_falls_back_to_the_original_when_the_derivative_file_has_gone(): void
    {
        $this->writeFixture();
        app(LegacyImageDerivativeServiceInterface::class)->generate([$this->sourcePath]);

        $this->assertStringStartsWith(
            '/storage/'.MediaUrlResolver::DERIVATIVE_DIRECTORY.'/',
            (string) MediaUrlResolver::resolveLegacy($this->sourcePath),
        );

        // A derivative deleted behind the manifest's back must not produce a
        // broken image; the next generation prunes the entry and the page falls
        // back to the original in the meantime.
        $this->clearDerivatives();
        MediaUrlResolver::flushLegacyDerivativeManifest();
        app(LegacyImageDerivativeServiceInterface::class)->generate([]);

        $this->assertSame(
            '/'.$this->sourcePath,
            MediaUrlResolver::resolveLegacy($this->sourcePath),
        );
    }

    public function test_generation_produces_a_much_smaller_derivative_and_a_responsive_set(): void
    {
        $this->writeFixture();
        $originalBytes = (int) filesize(public_path($this->sourcePath));

        $report = app(LegacyImageDerivativeServiceInterface::class)->generate([$this->sourcePath]);

        $this->assertSame(1, $report->consideredCount);
        $this->assertSame(1, $report->generatedCount);
        $this->assertSame([], $report->failedSources);
        $this->assertSame([], $report->missingSources);

        $resolved = (string) MediaUrlResolver::resolveLegacy($this->sourcePath);
        $this->assertStringEndsWith('.webp', $resolved);

        $derivativeBytes = (int) Storage::disk('public')->size(
            ltrim(str_replace('/storage/', '', $resolved), '/'),
        );
        $this->assertLessThan(
            $originalBytes,
            $derivativeBytes,
            'The derivative must be smaller than the original camera JPEG.',
        );

        $srcset = (string) MediaUrlResolver::legacySrcset($this->sourcePath);
        $this->assertStringContainsString('480w', $srcset);
        $this->assertStringContainsString('960w', $srcset);
        $this->assertStringContainsString('1440w', $srcset);
    }

    public function test_generation_is_idempotent(): void
    {
        $this->writeFixture();
        $service = app(LegacyImageDerivativeServiceInterface::class);

        $first = $service->generate([$this->sourcePath]);
        $manifest = Storage::disk('public')->get(MediaUrlResolver::DERIVATIVE_DIRECTORY.'/manifest.json');

        $second = $service->generate([$this->sourcePath]);

        $this->assertSame(1, $first->generatedCount);
        $this->assertSame(0, $second->generatedCount, 'A re-run must not re-encode existing derivatives.');
        $this->assertSame(1, $second->reusedCount);
        $this->assertSame(
            $manifest,
            Storage::disk('public')->get(MediaUrlResolver::DERIVATIVE_DIRECTORY.'/manifest.json'),
            'A re-run must leave the manifest byte-identical.',
        );
    }

    public function test_the_command_is_idempotent_and_reports_what_it_did(): void
    {
        $this->writeFixture();

        $this->artisan('media:generate-legacy-derivatives', ['--path' => [$this->sourcePath]])
            ->expectsOutputToContain('generated 1')
            ->assertSuccessful();

        $this->artisan('media:generate-legacy-derivatives', ['--path' => [$this->sourcePath]])
            ->expectsOutputToContain('generated 0')
            ->assertSuccessful();
    }

    public function test_a_source_outside_the_legacy_directories_is_never_converted(): void
    {
        $report = app(LegacyImageDerivativeServiceInterface::class)
            ->generate(['images/logo-spu.png', '../etc/passwd', 'downloads/files/notes.txt']);

        $this->assertSame(0, $report->consideredCount);
        $this->assertSame(0, $report->generatedCount);
    }

    public function test_a_missing_source_is_reported_and_leaves_the_original_in_place(): void
    {
        $report = app(LegacyImageDerivativeServiceInterface::class)
            ->generate(['downloads/files/does-not-exist.jpg']);

        $this->assertSame(1, $report->consideredCount);
        $this->assertSame(0, $report->generatedCount);
        $this->assertSame(['downloads/files/does-not-exist.jpg'], $report->missingSources);
        $this->assertSame(
            '/downloads/files/does-not-exist.jpg',
            MediaUrlResolver::resolveLegacy('downloads/files/does-not-exist.jpg'),
        );
    }

    public function test_derivatives_can_be_switched_off_without_touching_the_manifest(): void
    {
        $this->writeFixture();
        app(LegacyImageDerivativeServiceInterface::class)->generate([$this->sourcePath]);

        config(['media.derivatives.enabled' => false]);
        MediaUrlResolver::flushLegacyDerivativeManifest();

        $this->assertSame('/'.$this->sourcePath, MediaUrlResolver::resolveLegacy($this->sourcePath));
        $this->assertNull(MediaUrlResolver::legacySrcset($this->sourcePath));
    }
}
