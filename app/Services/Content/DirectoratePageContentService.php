<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\Content\DirectoratePageContentServiceInterface;
use App\Contracts\Council\CouncilStaffServiceInterface;
use App\Contracts\Form\FormDefinitionServiceInterface;
use App\DTOs\Content\DirectorateBlockDTO;
use App\DTOs\Content\DirectorateManagerDTO;
use App\Support\DirectorateContentBlocks;
use App\Support\DirectorateManagerFields;

final class DirectoratePageContentService implements DirectoratePageContentServiceInterface
{
    public function __construct(
        private readonly CouncilStaffServiceInterface $staffService,
        private readonly FormDefinitionServiceInterface $formDefinitions,
    ) {}

    public function manager(?int $memberId, array $names, array $education, ?string $email, ?string $phone, ?string $photo, string $locale): ?DirectorateManagerDTO
    {
        $locale = $locale === 'en' ? 'en' : 'ar';
        $other = $locale === 'ar' ? 'en' : 'ar';
        $qualifications = DirectorateManagerFields::education($education[$locale] ?? null);
        if ($qualifications === []) {
            $qualifications = DirectorateManagerFields::education($education[$other] ?? null);
        }

        if ($memberId !== null && $memberId > 0) {
            $card = $this->staffService->staffCards([$memberId], $locale)[$memberId] ?? null;
            if ($card !== null) {
                $email = trim((string) $email);

                return new DirectorateManagerDTO(
                    name: $card['name'],
                    education: $qualifications,
                    email: $email !== '' ? $email : ($card['email'] !== '' ? $card['email'] : null),
                    phone: $this->text($phone),
                    photo: DirectorateManagerFields::photo($card['image']) ?? DirectorateManagerFields::photo($photo),
                    rank: $card['rank'] !== '' ? $card['rank'] : null,
                    profileUrl: $card['url'],
                );
            }
        }

        foreach ([$locale, $other] as $candidate) {
            $manager = DirectorateManagerFields::manager($names[$candidate] ?? null, $qualifications, $email, $phone, $photo);
            if ($manager !== null) {
                return $manager;
            }
        }

        return null;
    }

    public function blocks(mixed $blocks, string $locale): array
    {
        return array_values(array_filter(
            DirectorateContentBlocks::localize($blocks, $locale),
            fn (DirectorateBlockDTO $block): bool => $block->formKey === null || $this->formDefinitions->isEnabled($block->formKey),
        ));
    }

    private function text(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
