<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\HomepageSectionServiceInterface;
use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (HomepageSectionServiceInterface::SECTION_KEYS as $index => $key) {
            HomepageSection::query()->updateOrCreate(
                ['key' => $key],
                [
                    'type' => $this->sectionType($key),
                    'sort_order' => $index + 1,
                    'is_enabled' => $key !== 'bottom_stats',
                    'schema_version' => 1,
                    'config_json' => [
                        'approved_key' => $key,
                        'supports_preview' => true,
                    ],
                ]
            );
        }
    }

    private function sectionType(string $key): string
    {
        return match ($key) {
            'hero' => 'hero',
            'hero_stats', 'bottom_stats' => 'stats',
            'footer' => 'footer',
            default => 'listing',
        };
    }
}
