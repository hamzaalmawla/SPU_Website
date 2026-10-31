<?php

declare(strict_types=1);

namespace Tests\Feature\PX05;

use Tests\TestCase;

/**
 * Files the old site served from its document root that must still answer at
 * the same address once spu.edu.sy points at this application. Every one of
 * them fails silently: nothing errors, the domain just loses verified
 * ownership in a link index, or every browser tab shows a blank icon.
 */
final class LegacyRootFilesTest extends TestCase
{
    /**
     * Ahrefs and Majestic are the two link indexes Webometrics reads for its
     * Visibility score, and Google Search Console holds the domain's indexing
     * history. Their ownership tokens lived in the old document root.
     */
    public function test_site_ownership_tokens_are_shipped_with_their_exact_content(): void
    {
        $tokens = [
            'ahrefs_25e0c9c40296ede7e6fc702b2149a8899fd6e3143a911f1760382f1252c4fdbf' => 'ahrefs-site-verification_25e0c9c40296ede7e6fc702b2149a8899fd6e3143a911f1760382f1252c4fdbf',
            'MJ12_d49e8fe5-1b5e-484c-a0ed-f8934f995cec.txt' => 'This is a Majestic verification file.',
            'google31572f5049a7f3ed.html' => 'google-site-verification: google31572f5049a7f3ed.html',
            'google95c0894432b8be23.html' => 'google-site-verification: google95c0894432b8be23.html',
        ];

        foreach ($tokens as $file => $content) {
            $path = public_path($file);
            $this->assertFileExists($path, $file.' must stay in public/.');
            $this->assertSame($content, trim((string) file_get_contents($path)), $file.' content must match the token the index issued.');
        }
    }

    public function test_the_deploy_publishes_root_files_into_the_docroot(): void
    {
        $script = (string) file_get_contents(base_path('deploy/v2-staging/cpanel-deploy.sh'));

        foreach (['favicon.ico', 'apple-touch-icon.png', 'google*.html', 'MJ12_*.txt', 'ahrefs_*'] as $pattern) {
            $this->assertStringContainsString($pattern, $script, 'cpanel-deploy.sh must publish '.$pattern.'; only build/ and SVGs reach the docroot otherwise.');
        }
    }

    /**
     * /forms and /Medicine are live PHP applications in the old docroot. They
     * must keep answering at the same address after cutover, so the deploy
     * mounts them - outside _legacy/, where PHP is refused.
     */
    public function test_the_deploy_keeps_the_live_legacy_applications_mounted(): void
    {
        $script = (string) file_get_contents(base_path('deploy/v2-staging/cpanel-deploy.sh'));

        $this->assertStringContainsString('ensure_app_mount "${LEGACY_ROOT}/forms"    "${WEB}/forms"', $script);
        $this->assertStringContainsString('ensure_app_mount "${LEGACY_ROOT}/Medicine" "${WEB}/Medicine"', $script);
        $this->assertStringNotContainsString('"${WEB}/_legacy/forms"', $script);
    }

    /** It was a zero-byte file, which browsers and search engines show as no icon. */
    public function test_favicon_is_a_real_icon(): void
    {
        $bytes = (string) file_get_contents(public_path('favicon.ico'));

        $this->assertGreaterThan(1000, strlen($bytes));
        $this->assertSame("\x00\x00\x01\x00", substr($bytes, 0, 4), 'favicon.ico must be an ICO container.');
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_fckeditor_uploads_are_served_in_place_and_never_executed(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));

        $mount = strpos($rules, 'RewriteRule ^FCKBIH/(.*)$ /_legacy/FCKBIH/$1 [L]');
        $this->assertIsInt($mount, 'The /FCKBIH/ mount is missing from public/.htaccess.');

        foreach (['php[0-9]?', 'html?|xhtml|xml|svgz?', '(?:.*/)?\.[^/]+$'] as $blocked) {
            $guard = strpos($rules, 'RewriteRule ^FCKBIH/'.(str_starts_with($blocked, '(?:') ? $blocked : '.*\.(?:'.$blocked));
            $this->assertIsInt($guard, 'An /FCKBIH/ block ('.$blocked.') is missing.');
            $this->assertLessThan($mount, $guard, 'Every /FCKBIH/ block must run before the mount rewrite.');
        }
    }

    public function test_touch_icon_variants_resolve_without_matching_the_icon_itself(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));
        $this->assertSame(1, preg_match('#RewriteRule \^(apple-touch-icon\S+) /apple-touch-icon\.png#', $rules, $match));

        $pattern = '#'.$match[1].'#i';

        foreach (['apple-touch-icon-precomposed.png', 'apple-touch-icon-120x120.png', 'apple-touch-icon-152x152-precomposed.png'] as $variant) {
            $this->assertSame(1, preg_match($pattern, $variant), $variant.' must resolve to the touch icon.');
        }

        // A rule matching its own target would rewrite in a loop.
        $this->assertSame(0, preg_match($pattern, 'apple-touch-icon.png'));
    }

    /**
     * /med/?lang=2 is how the old site linked its subsite roots. The rule that
     * hands them to the application must run before the trailing-slash
     * redirect, or each one costs an extra hop. /admin/ stays out: the CMS.
     */
    public function test_legacy_subsite_roots_skip_the_trailing_slash_redirect(): void
    {
        $rules = (string) file_get_contents(public_path('.htaccess'));

        $root = strpos($rules, 'RewriteRule ^(?:med|dent|pharm|info|petrol|research|hospital|dent_clinic|alumni|clubs|members)/$ app.php [L]');
        $slash = strpos($rules, '# Redirect Trailing Slashes If Not A Folder');

        $this->assertIsInt($root);
        $this->assertIsInt($slash);
        $this->assertLessThan($slash, $root);
    }

    /** The _legacy media is symlinked from the old docroot; Apache must follow it. */
    public function test_legacy_symlinks_are_followed(): void
    {
        $this->assertStringContainsString('Options +FollowSymLinks', (string) file_get_contents(public_path('.htaccess')));
    }
}
