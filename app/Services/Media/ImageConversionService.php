<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Media\ImageConversionServiceInterface;
use App\DTOs\Media\WebpConversionResultDTO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

final class ImageConversionService implements ImageConversionServiceInterface
{
    public function isAvailable(): bool
    {
        return $this->driverName() !== null;
    }

    public function convert(string $diskName, string $sourcePath, string $mimeType): ?WebpConversionResultDTO
    {
        if (! in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return null;
        }

        if ($mimeType === 'image/webp') {
            $source = $this->sourceContents($diskName, $sourcePath);

            if ($source === null) {
                return null;
            }

            [$contents] = $source;
            [$width, $height] = $this->dimensions($contents);

            return new WebpConversionResultDTO($sourcePath, strlen($contents), $width, $height);
        }

        $driver = $this->driverName();

        if ($driver === null) {
            return null;
        }

        try {
            $source = $this->sourceContents($diskName, $sourcePath);

            if ($source === null) {
                return null;
            }

            $manager = $driver === 'imagick' ? ImageManager::imagick() : ImageManager::gd();
            [$contents, $isPublicPath] = $source;
            [$sourceWidth, $sourceHeight] = $this->dimensions($contents);

            // Decoding is not catchable when it runs out of memory: PHP dies with
            // a fatal error and the upload fails. The host's limit is 128 MB and
            // a phone photo decodes to 50 MB or more, an 8000x8000 image to 256 MB.
            if (! $this->hasMemoryToDecode($sourceWidth, $sourceHeight)) {
                Log::info('WebP conversion skipped: image too large for the memory limit.', [
                    'width' => $sourceWidth,
                    'height' => $sourceHeight,
                    'memory_limit' => ini_get('memory_limit'),
                ]);

                return null;
            }

            $image = $manager->read($contents);
            $destinationPath = $this->webpPath($sourcePath);
            $encoded = $image->encode(new WebpEncoder(
                quality: (int) config('media.webp.quality', 82),
                strip: true,
            ));

            if (! $this->writeDestination($diskName, $destinationPath, $encoded->toString(), $isPublicPath)) {
                return null;
            }

            return new WebpConversionResultDTO(
                path: $destinationPath,
                sizeBytes: $encoded->size(),
                width: $image->width(),
                height: $image->height(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function convertToDisk(
        string $sourceAbsolutePath,
        string $destinationDisk,
        string $destinationPath,
        ?int $maxWidth = null,
    ): ?WebpConversionResultDTO {
        $driver = $this->driverName();

        if ($driver === null || ! is_file($sourceAbsolutePath) || ! is_readable($sourceAbsolutePath)) {
            return null;
        }

        try {
            $contents = file_get_contents($sourceAbsolutePath);

            if (! is_string($contents) || $contents === '') {
                return null;
            }

            $manager = $driver === 'imagick' ? ImageManager::imagick() : ImageManager::gd();
            $image = $manager->read($contents);

            if ($maxWidth !== null && $maxWidth > 0 && $image->width() > $maxWidth) {
                $image = $image->scaleDown(width: $maxWidth);
            }

            $encoded = $image->encode(new WebpEncoder(
                quality: (int) config('media.webp.quality', 82),
                strip: true,
            ));

            if (! Storage::disk($destinationDisk)->put($destinationPath, $encoded->toString())) {
                return null;
            }

            return new WebpConversionResultDTO(
                path: $destinationPath,
                sizeBytes: $encoded->size(),
                width: $image->width(),
                height: $image->height(),
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function driverName(): ?string
    {
        $configured = (string) config('media.webp.driver', 'auto');

        if ($configured === 'gd' && function_exists('imagewebp')) {
            return 'gd';
        }

        if ($configured === 'imagick' && extension_loaded('imagick')) {
            return 'imagick';
        }

        if ($configured === 'auto') {
            if (function_exists('imagewebp')) {
                return 'gd';
            }

            if (extension_loaded('imagick')) {
                return 'imagick';
            }
        }

        return null;
    }

    private function webpPath(string $sourcePath): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);

        if ($extension === '') {
            return $sourcePath.'.webp';
        }

        return substr($sourcePath, 0, -(strlen($extension) + 1)).'.webp';
    }

    /** @return array{0: string, 1: bool}|null */
    private function sourceContents(string $diskName, string $sourcePath): ?array
    {
        $disk = Storage::disk($diskName);

        if ($disk->exists($sourcePath)) {
            return [$disk->get($sourcePath), false];
        }

        $publicPath = public_path(ltrim($sourcePath, '/'));

        if (str_starts_with($sourcePath, '/') && is_file($publicPath)) {
            $contents = file_get_contents($publicPath);

            return is_string($contents) ? [$contents, true] : null;
        }

        return null;
    }

    private function writeDestination(string $diskName, string $destinationPath, string $contents, bool $isPublicPath): bool
    {
        if (! $isPublicPath) {
            return Storage::disk($diskName)->put($destinationPath, $contents);
        }

        $fullPath = public_path(ltrim($destinationPath, '/'));
        $directory = dirname($fullPath);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return false;
        }

        return file_put_contents($fullPath, $contents) !== false;
    }

    /** @return array{0: int, 1: int} */
    /**
     * Whether decoding (and re-encoding) an image of this size fits in memory,
     * raising the limit for this request up to 1 GB when the host allows it.
     */
    private function hasMemoryToDecode(int $width, int $height): bool
    {
        if ($width <= 0 || $height <= 0) {
            return true;
        }

        // Four truecolour copies and some headroom: the decoded image, the two
        // that turning a sideways phone photo upright makes (EXIF auto-
        // orientation), and the encoder's working copy. Counting only two
        // ran approvals of a staff photo out of memory.
        $needed = (int) ($width * $height * 4 * 4) + 16 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));

        if ($limit < 0) {
            return true;
        }

        if (memory_get_usage(true) + $needed <= $limit) {
            return true;
        }

        $wanted = min(memory_get_usage(true) + $needed + 32 * 1024 * 1024, 1024 * 1024 * 1024);

        if ($wanted > $limit && @ini_set('memory_limit', (string) $wanted) !== false) {
            $limit = $this->bytes((string) ini_get('memory_limit'));
        }

        return memory_get_usage(true) + $needed <= $limit;
    }

    private function bytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function dimensions(string $contents): array
    {
        $dimensions = @getimagesizefromstring($contents);

        return is_array($dimensions) ? [(int) $dimensions[0], (int) $dimensions[1]] : [0, 0];
    }
}
