<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Media\ImageConversionServiceInterface;
use App\Contracts\Media\MediaServiceInterface;
use Illuminate\Console\Command;

final class ConvertMediaImagesCommand extends Command
{
    protected $signature = 'media:convert-webp
        {--user-id= : Required administrator id used for authorization and audit logging}
        {--limit= : Maximum number of images to convert}';

    protected $description = 'Generate WebP derivatives for existing CMS image assets.';

    public function handle(MediaServiceInterface $mediaService, ImageConversionServiceInterface $conversionService): int
    {
        $userId = $this->option('user-id');

        if (! is_numeric($userId) || (int) $userId <= 0) {
            $this->error('Provide a valid --user-id for authorization and audit logging.');

            return self::INVALID;
        }

        if (! $conversionService->isAvailable()) {
            $this->error('WebP conversion is unavailable. Enable the PHP GD extension with WebP support or Imagick.');

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;
        $converted = $mediaService->convertImages((int) $userId, $limit);

        $this->info("Converted {$converted} image assets to WebP.");

        return self::SUCCESS;
    }
}
