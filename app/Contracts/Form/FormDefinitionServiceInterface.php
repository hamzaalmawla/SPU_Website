<?php

declare(strict_types=1);

namespace App\Contracts\Form;

use App\DTOs\Form\FormDefinitionDTO;
use App\Enums\FormSubmissionInbox;
use Illuminate\Support\Collection;

interface FormDefinitionServiceInterface
{
    public const FIELD_TYPES = ['text', 'email', 'tel', 'number', 'date', 'select', 'textarea', 'checkbox', 'file'];

    public const FILE_TYPES = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];

    /** @return Collection<int, FormDefinitionDTO> */
    public function all(): Collection;

    public function find(string $key): ?FormDefinitionDTO;

    public function findById(int $id): ?FormDefinitionDTO;

    /** @return array<string, string> enabled forms by key, named in the given locale, for a "choose a form" dropdown */
    public function options(string $locale): array;

    public function isEnabled(string $key): bool;

    /** @return list<string> */
    public function enabledKeys(): array;

    /** The form as the public form script renders it, or null when it does not exist or is switched off. */
    public function publicSchema(string $key): ?array;

    public function inboxFor(string $key): ?FormSubmissionInbox;

    /** @return list<string> */
    public function keysForInbox(FormSubmissionInbox $inbox): array;

    public function fieldLabel(string $key, string $field, string $locale): ?string;

    public function submissionCount(string $key): int;

    /** @return list<string> where the form is in use, in words, in the given locale; empty when it is not */
    public function usage(string $key, string $locale): array;

    /** @param array<string, mixed> $data */
    public function save(?int $id, array $data, int $userId): FormDefinitionDTO;

    public function setEnabled(int $id, bool $enabled, int $userId): bool;

    public function delete(int $id, int $userId): bool;
}
