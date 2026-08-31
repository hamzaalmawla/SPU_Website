<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Contracts\Media\ImageConversionServiceInterface;
use App\DTOs\Media\WebpConversionResultDTO;
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
    private function dimensions(string $contents): array
    {
        $dimensions = @getimagesizefromstring($contents);

        return is_array($dimensions) ? [(int) $dimensions[0], (int) $dimensions[1]] : [0, 0];
    }
}
