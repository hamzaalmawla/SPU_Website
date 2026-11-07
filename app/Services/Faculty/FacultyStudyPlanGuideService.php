<?php

declare(strict_types=1);

namespace App\Services\Faculty;

use App\Contracts\Faculty\FacultyStudyPlanGuideServiceInterface;

final class FacultyStudyPlanGuideService implements FacultyStudyPlanGuideServiceInterface
{
    /** @var list<string> */
    private const ILLUSTRATED_STEPS = ['card', 'columns', 'lines', 'highlight', 'electives', 'views'];

    /** @var array<string, mixed>|null */
    private ?array $defaults = null;

    public function withDefaults(array $payload, string $locale): array
    {
        $locale = $locale === 'ar' ? 'ar' : 'en';
        $defaults = $this->defaults();
        $defaultGuide = is_array($defaults['guide'][$locale] ?? null) ? $defaults['guide'][$locale] : [];
        $guide = is_array($payload['guide'] ?? null) ? $payload['guide'] : [];

        foreach (['title', 'legendHint'] as $key) {
            $guide[$key] = array_key_exists($key, $guide) ? trim((string) $guide[$key]) : (string) ($defaultGuide[$key] ?? '');
        }

        $steps = array_key_exists('steps', $guide) && is_array($guide['steps']) ? $guide['steps'] : (array) ($defaultGuide['steps'] ?? []);
        $guide['steps'] = array_values(array_filter(array_map(static fn (mixed $step): ?array => is_array($step) ? [
            'id' => trim((string) ($step['id'] ?? '')),
            'title' => trim((string) ($step['title'] ?? '')),
            'text' => trim((string) ($step['text'] ?? '')),
        ] : null, $steps), static fn (?array $step): bool => $step !== null && ($step['title'] !== '' || $step['text'] !== '')));
        $payload['guide'] = $guide;

        $descriptions = is_array($defaults['legendDescriptions'][$locale] ?? null) ? $defaults['legendDescriptions'][$locale] : [];

        if (is_array($payload['legend'] ?? null)) {
            $payload['legend'] = array_values(array_map(static function (mixed $item) use ($descriptions): mixed {
                if (is_array($item) && ! array_key_exists('description', $item)) {
                    $item['description'] = (string) ($descriptions[(string) ($item['id'] ?? '')] ?? '');
                }

                return $item;
            }, $payload['legend']));
        }

        return $payload;
    }

    public function illustratedStepIds(): array
    {
        return self::ILLUSTRATED_STEPS;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        if ($this->defaults === null) {
            $path = database_path('seeders/Data/study_plan_guide.json');
            $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
            $this->defaults = is_array($decoded) ? $decoded : [];
        }

        return $this->defaults;
    }
}
