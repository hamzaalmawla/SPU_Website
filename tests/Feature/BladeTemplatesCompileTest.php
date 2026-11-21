<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Compiles every Blade template and fails on the first one PHP cannot parse.
 *
 * WHY THIS EXISTS
 *
 * A `@php ... @endphp` block was added to a template that already opened a
 * single-expression `@php(...)` higher up. Blade extracts raw PHP blocks before
 * it compiles anything else, and its pattern runs from the first opener in the
 * file to the first closer — so the new block closed the OLD one, and forty
 * lines of markup between them were emitted as raw PHP. The homepage 500'd with
 * "syntax error, unexpected token endforeach".
 *
 * The suite did catch it: 54 tests failed. Not one of them said why. The cause
 * was found by reading storage/logs/laravel.log, which is not a step anyone
 * repeats, and a template that renders on no tested route would not have failed
 * anything at all.
 *
 * This compiles the templates and nothing else. It does not render them, so it
 * needs no data and cannot be flaky — it answers one question, which is whether
 * what we shipped is parseable.
 */
final class BladeTemplatesCompileTest extends TestCase
{
    public function test_every_blade_template_compiles(): void
    {
        $broken = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $compiled = Blade::compileString((string) file_get_contents($file->getPathname()));

            // php -l equivalent, in-process: the compiler emits PHP, and this
            // asks PHP whether it would parse. The leading close tag matches
            // how a compiled view begins in inline-HTML context.
            $error = null;

            try {
                // @phpstan-ignore-next-line — eval is the point: nothing runs.
                eval('return true; ?>'.$compiled);
            } catch (\ParseError $e) {
                $error = $e->getMessage();
            }

            if ($error !== null) {
                $broken[] = str_replace(base_path().'/', '', $file->getPathname()).': '.$error;
            }
        }

        $this->assertSame([], $broken, "Blade templates that do not compile:\n".implode("\n", $broken));
    }
}
