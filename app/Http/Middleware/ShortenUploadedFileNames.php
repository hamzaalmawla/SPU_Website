<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps Livewire's temporary upload file names under the 255-byte limit of
 * the host's file system.
 *
 * Livewire names a temporary upload "<30 random chars>-meta<base64 of the
 * original name>-.<ext>". Base64 grows a name by a third, and Arabic letters
 * are two bytes each, so a ministry file called "الجدول 4 الحدود الدنيا
 * لتغيير القيد ... 2026-2027.md" (about 95 letters) gives a 259-byte name:
 * the file is never written and saving the form fails with a server error.
 * Here an over-long original name is cut (whole characters, extension kept)
 * before Livewire sees it; the upload then works and the name only matters
 * as a default title an editor can rename.
 *
 * Runs only on Livewire's upload route (config/livewire.php middleware).
 */
final class ShortenUploadedFileNames
{
    /** File-system limit, minus a margin. */
    private const MAX_STORED_BYTES = 250;

    /** "<30 random>" + "-meta" + "-" + "." around the encoded name. */
    private const LIVEWIRE_OVERHEAD = 37;

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->files->all() as $key => $files) {
            $request->files->set($key, $this->shorten($files));
        }

        return $next($request);
    }

    private function shorten(mixed $files): mixed
    {
        if (is_array($files)) {
            return array_map(fn (mixed $file): mixed => $this->shorten($file), $files);
        }

        if (! $files instanceof SymfonyUploadedFile) {
            return $files;
        }

        $name = $files->getClientOriginalName();
        $short = self::shortName($name);
        if ($short === $name) {
            return $files;
        }

        return new UploadedFile($files->getPathname(), $short, $files->getClientMimeType(), $files->getError(), true);
    }

    /**
     * The longest prefix of the name (whole UTF-8 characters, extension
     * kept) whose Livewire temporary file name fits the limit.
     */
    public static function shortName(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $extension = strlen($extension) <= 10 ? $extension : '';
        $budget = self::MAX_STORED_BYTES - self::LIVEWIRE_OVERHEAD - strlen($extension);
        // base64 turns every 3 bytes into 4 characters.
        $maxNameBytes = intdiv($budget, 4) * 3;

        if (strlen($name) <= $maxNameBytes) {
            return $name;
        }

        $suffix = $extension !== '' ? '.'.$extension : '';
        $base = $extension !== '' ? substr($name, 0, -strlen($suffix)) : $name;
        $base = rtrim(mb_strcut($base, 0, max(1, $maxNameBytes - strlen($suffix))), " -_.\u{00A0}");

        return $base.$suffix;
    }
}
