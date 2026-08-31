<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use Tests\TestCase;

/**
 * The legacy static trees are mounted under prefixes this application also uses
 * for its own committed assets. /images in particular is both the mount point for
 * the old site's uploads and the home of our own icon set.
 *
 * Shipping the markup/SVG block without a fall-through guard 404'd every one of
 * those icons in production. These tests pin the shape that keeps both true: the
 * legacy trees stay blocked, our own files stay reachable.
 */
final class LegacyStaticPrefixGuardTest extends TestCase
{
    private const MOUNTED_PREFIX_MARKUP_RULE = 'RewriteRule ^(?:downloads/files(?:/.*)?|downloads/files2(?:/.*)?|images(?:/.*)?|pdf(?:/.*)?|cv_bank(?:/.*)?|(?:med|dent|pharm|info|petrol|admin|research|hospital|dent_clinic|alumni|clubs)/images(?:/.*)?)\.(?:html?|xhtml|xml|svgz?)(?:\.|$) - [R=404,L,NC]';

    public function test_markup_block_on_shared_prefixes_only_applies_to_fall_through_requests(): void
    {
        $rules = $this->rules();

        $position = strpos($rules, self::MOUNTED_PREFIX_MARKUP_RULE);
        $this->assertIsInt($position, 'The mounted-prefix markup block is missing from public/.htaccess.');

        // A RewriteCond binds only to the RewriteRule that immediately follows it,
        // so the guard has to sit directly above the rule to have any effect.
        $preceding = trim(substr($rules, 0, $position));
        $lastCondition = trim((string) substr($preceding, (int) strrpos($preceding, "\n")));

        $this->assertSame(
            'RewriteCond %{REQUEST_FILENAME} !-f',
            $lastCondition,
            'The markup block must be guarded by !-f or it 404s this application\'s own committed SVG icons.',
        );
    }

    public function test_executable_extension_block_stays_unconditional(): void
    {
        $rules = $this->rules();

        $position = strpos($rules, 'RewriteRule ^(?:downloads/files(?:/.*)?|downloads/files2(?:/.*)?|images(?:/.*)?|pdf(?:/.*)?|cv_bank(?:/.*)?|(?:med|dent|pharm|info|petrol|admin|research|hospital|dent_clinic|alumni|clubs)/images(?:/.*)?)\.(?:php[0-9]?');
        $this->assertIsInt($position, 'The mounted-prefix executable block is missing from public/.htaccess.');

        $preceding = trim(substr($rules, 0, $position));
        $lastLine = trim((string) substr($preceding, (int) strrpos($preceding, "\n")));

        $this->assertStringNotContainsString(
            'REQUEST_FILENAME} !-f',
            $lastLine,
            'Executable extensions must be refused on these prefixes whether or not a local file exists.',
        );
    }

    public function test_legacy_upload_paths_still_match_the_markup_block(): void
    {
        // Reproduces the rule's pattern so a future edit cannot quietly stop
        // covering the legacy trees it exists to protect.
        $pattern = '#^(?:downloads/files(?:/.*)?|downloads/files2(?:/.*)?|images(?:/.*)?|pdf(?:/.*)?|cv_bank(?:/.*)?|(?:med|dent|pharm|info|petrol|admin|research|hospital|dent_clinic|alumni|clubs)/images(?:/.*)?)\.(?:html?|xhtml|xml|svgz?)(?:\.|$)#i';

        foreach ([
            'images/1494920895_5171123882.svg',
            'downloads/files/legacy-note.html',
            'dent/images/uploaded.svg',
            'cv_bank/anything.xhtml',
        ] as $path) {
            $this->assertSame(1, preg_match($pattern, $path), $path.' must still be covered by the markup block.');
        }

        foreach ([
            'images/slider-3.webp',
            'build/assets/icon-chevron-down-outline.DpdqcSdb.svg',
        ] as $path) {
            $this->assertSame(0, preg_match($pattern, $path), $path.' must not be covered by the markup block.');
        }
    }

    public function test_application_owns_committed_svg_icons_under_the_shared_prefix(): void
    {
        $icons = glob(public_path('images').'/*.svg') ?: [];

        $this->assertNotEmpty(
            $icons,
            'This guard exists to protect committed SVG icons under public/images; none were found.',
        );
    }

    private function rules(): string
    {
        $rules = file_get_contents(public_path('.htaccess'));

        $this->assertIsString($rules);

        return $rules;
    }
}
