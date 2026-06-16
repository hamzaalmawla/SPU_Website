<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validates uploaded media files before they enter the CMS media library.
 */
final class MediaFileValidator
{
    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'video/mp4',
        'video/webm',
    ];

    private const MAX_FILE_SIZE_BYTES = 20 * 1024 * 1024;

    private const MIN_FILE_SIZE_BYTES = 1;

    /** @var array<string, list<string>> */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'video/mp4' => ['mp4'],
        'video/webm' => ['webm'],
    ];

    private const MAX_IMAGE_WIDTH = 8000;

    private const MAX_IMAGE_HEIGHT = 8000;

    public function validate(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw ValidationException::withMessages([
                'file' => ["File type '{$mimeType}' is not allowed."],
            ]);
        }

        $this->validateExtension($file, $mimeType);
        $this->validateSize($file);
        $this->validateImageDimensions($file, $mimeType);
    }

    public function primaryExtensionForMime(string $mimeType): string
    {
        $extensions = self::MIME_EXTENSIONS[$mimeType] ?? [];

        if ($extensions === []) {
            throw ValidationException::withMessages([
                'file' => ['The detected file type does not have an approved extension.'],
            ]);
        }

        return $extensions[0];
    }

    private function validateExtension(UploadedFile $file, string $mimeType): void
    {
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = self::MIME_EXTENSIONS[$mimeType] ?? [];

        if ($allowedExtensions === [] || $clientExtension === '' || ! in_array($clientExtension, $allowedExtensions, true)) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file extension does not match its detected file type.'],
            ]);
        }
    }

    private function validateSize(UploadedFile $file): void
    {
        $size = $file->getSize() ?: 0;

        if ($size < self::MIN_FILE_SIZE_BYTES) {
            throw ValidationException::withMessages([
                'file' => ['The uploaded file is empty.'],
            ]);
        }

        if ($size > self::MAX_FILE_SIZE_BYTES) {
            $maxMb = self::MAX_FILE_SIZE_BYTES / (1024 * 1024);

            throw ValidationException::withMessages([
                'file' => ["File size exceeds the maximum allowed size of {$maxMb}MB."],
            ]);
        }
    }

    private function validateImageDimensions(UploadedFile $file, string $mimeType): void
    {
        if (! str_starts_with($mimeType, 'image/') || $mimeType === 'image/svg+xml') {
            return;
        }

        $dimensions = @getimagesize($file->getRealPath());

        if (! is_array($dimensions)) {
            return;
        }

        [$width, $height] = $dimensions;

        if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            throw ValidationException::withMessages([
                'file' => ["Image dimensions ({$width}x{$height}) exceed the maximum allowed (".self::MAX_IMAGE_WIDTH.'x'.self::MAX_IMAGE_HEIGHT.').'],
            ]);
        }
    }
}
