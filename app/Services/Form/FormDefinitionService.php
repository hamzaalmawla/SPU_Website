<?php

declare(strict_types=1);

namespace App\Services\Form;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Contracts\Shared\AuditServiceInterface;
use App\Contracts\Shared\CacheServiceInterface;
use App\DTOs\Form\FormDefinitionDTO;
use App\Enums\FormSubmissionInbox;
use App\Models\Cms\CmsDraft;
use App\Models\Cms\CmsTargetContent;
use App\Models\Form\DynamicFormSubmission;
use App\Models\Form\FormDefinition;
use App\Models\News\NewsArticle;
use App\Models\News\NewsArticleEvent;
use App\Models\User\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class FormDefinitionService implements FormDefinitionServiceInterface
{
    /**
     * Pages that show a fixed place's form, the payload key that picks it and
     * the form they fall back to when none is picked.
     */
    public const PAGE_FORMS = [
        'admissions.how-to-apply' => ['setting' => 'applicationFormId', 'default' => 'admissions-application'],
        'campus_life.jobs' => ['setting' => 'applicationFormId', 'default' => 'job-application'],
        'e_services.suggestions-complaints' => ['setting' => 'formId', 'default' => 'suggestions-complaints'],
    ];

    /** Request inputs the submission endpoint uses for itself, so no field may take them. */
    private const RESERVED_FIELD_NAMES = ['event_source', 'event_id', 'job_id', 'job_slug', 'website', 'form', '_token', '_context'];

    /** @var Collection<string, FormDefinitionDTO>|null */
    private ?Collection $loaded = null;

    public function __construct(
        private readonly AuditServiceInterface $auditService,
        private readonly CacheServiceInterface $cacheService,
    ) {}

    public function all(): Collection
    {
        return $this->loaded ??= FormDefinition::query()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (FormDefinition $form): array => [(string) $form->key => $this->toDto($form)]);
    }

    public function find(string $key): ?FormDefinitionDTO
    {
        return $this->all()->get($key);
    }

    public function findById(int $id): ?FormDefinitionDTO
    {
        return $this->all()->first(fn (FormDefinitionDTO $form): bool => $form->id === $id);
    }

    public function options(string $locale): array
    {
        return $this->all()
            ->filter(fn (FormDefinitionDTO $form): bool => $form->isEnabled)
            ->map(fn (FormDefinitionDTO $form): string => $locale === 'ar' ? $form->nameAr : $form->nameEn)
            ->all();
    }

    public function isEnabled(string $key): bool
    {
        return $this->find($key)?->isEnabled === true;
    }

    public function enabledKeys(): array
    {
        return array_keys($this->options('en'));
    }

    public function publicSchema(string $key): ?array
    {
        $form = $this->find($key);

        if (! $form instanceof FormDefinitionDTO || ! $form->isEnabled) {
            return null;
        }

        $steps = array_map(fn (array $step): array => [
            'titleAr' => $step['titleAr'],
            'titleEn' => $step['titleEn'],
            'fields' => array_map(fn (array $field): array => $this->publicField($field), $step['fields']),
        ], $form->steps);

        $schema = [
            'titleAr' => $form->nameAr,
            'titleEn' => $form->nameEn,
            'submitLabelAr' => $form->submitLabelAr !== '' ? $form->submitLabelAr : 'إرسال',
            'submitLabelEn' => $form->submitLabelEn !== '' ? $form->submitLabelEn : 'Submit',
        ];

        return $form->multiStep
            ? $schema + ['multiStep' => true, 'steps' => $steps]
            : $schema + ['fields' => $steps[0]['fields'] ?? []];
    }

    public function inboxFor(string $key): ?FormSubmissionInbox
    {
        return $this->find($key)?->inbox;
    }

    public function keysForInbox(FormSubmissionInbox $inbox): array
    {
        return $this->all()
            ->filter(fn (FormDefinitionDTO $form): bool => $form->inbox === $inbox)
            ->keys()
            ->values()
            ->all();
    }

    public function fieldLabel(string $key, string $field, string $locale): ?string
    {
        foreach ($this->find($key)?->fields ?? [] as $definition) {
            if ($definition['name'] === $field) {
                $label = $locale === 'ar' ? $definition['labelAr'] : $definition['labelEn'];

                return $label !== '' ? $label : null;
            }
        }

        return null;
    }

    public function submissionCount(string $key): int
    {
        return DynamicFormSubmission::query()->where('form_id', $key)->count();
    }

    public function usage(string $key, string $locale): array
    {
        $usage = [];
        $submissions = $this->submissionCount($key);

        if ($submissions > 0) {
            $usage[] = trans_choice('admin.forms.usage.submissions', $submissions, ['count' => $submissions], $locale);
        }

        $targets = [];

        foreach ([CmsTargetContent::class, CmsDraft::class] as $model) {
            $model::query()
                ->where(function ($query) use ($key): void {
                    $query->where('payload_json', 'like', '%"formId":"'.$key.'"%')
                        ->orWhere('payload_json', 'like', '%FormId":"'.$key.'"%');
                })
                ->pluck('target_key')
                ->each(function (string $target) use (&$targets): void {
                    $targets[$target] = true;
                });
        }

        // Published event articles keep their form on the event row.
        NewsArticleEvent::query()
            ->where('form_key', $key)
            ->pluck('news_article_id')
            ->each(function (int $articleId) use (&$targets): void {
                $targets['entity.news-article.'.$articleId] = true;
            });

        // A fixed place that has not picked a form shows its default one.
        foreach (self::PAGE_FORMS as $target => $page) {
            if ($page['default'] !== $key || isset($targets[$target])) {
                continue;
            }

            $published = CmsTargetContent::query()->where('target_key', $target)->value('payload_json');
            $payload = is_string($published) ? $published : json_encode($published);

            if (! is_string($payload) || ! str_contains($payload, '"'.$page['setting'].'":"')) {
                $targets[$target] = true;
            }
        }

        foreach (array_keys($targets) as $target) {
            if (preg_match('/^entity\.news-article\.([1-9][0-9]*)$/', $target, $matches) === 1) {
                $usage[] = __('admin.forms.usage.page', ['page' => $this->articleTitle((int) $matches[1], $locale)], $locale);

                continue;
            }

            $labelKey = 'admin.cms.targets.'.$target;
            $label = __($labelKey, [], $locale);
            $usage[] = __('admin.forms.usage.page', ['page' => $label !== $labelKey ? $label : $target], $locale);
        }

        return $usage;
    }

    private function articleTitle(int $articleId, string $locale): string
    {
        $article = NewsArticle::query()->with('translations')->find($articleId);
        $title = $article?->translations->firstWhere('locale', $locale)?->title
            ?? $article?->translations->firstWhere('locale', 'ar')?->title;

        return filled($title) ? trim((string) $title) : ($article?->slug ?? 'entity.news-article.'.$articleId);
    }

    public function save(?int $id, array $data, int $userId): FormDefinitionDTO
    {
        $existing = $id !== null ? FormDefinition::query()->findOrFail($id) : null;
        $this->authorize($existing instanceof FormDefinition ? 'update' : 'create', $existing ?? FormDefinition::class, $userId);

        $attributes = $this->validated($data, $existing);
        $form = $existing ?? new FormDefinition(['key' => $attributes['key'], 'created_by' => $userId]);
        $form->fill([
            'name_ar' => $attributes['name_ar'],
            'name_en' => $attributes['name_en'],
            'inbox' => $attributes['inbox'],
            'schema_json' => $attributes['schema_json'],
            'is_enabled' => $attributes['is_enabled'],
            'updated_by' => $userId,
        ])->save();

        $this->audit($existing instanceof FormDefinition ? 'form_definition.updated' : 'form_definition.created', $form, $userId);

        return $this->toDto($form);
    }

    public function setEnabled(int $id, bool $enabled, int $userId): bool
    {
        $form = FormDefinition::query()->findOrFail($id);
        $this->authorize('update', $form, $userId);
        $form->forceFill(['is_enabled' => $enabled, 'updated_by' => $userId])->save();
        $this->audit($enabled ? 'form_definition.enabled' : 'form_definition.disabled', $form, $userId);

        return true;
    }

    public function delete(int $id, int $userId): bool
    {
        $form = FormDefinition::query()->findOrFail($id);
        $this->authorize('delete', $form, $userId);
        $usage = $this->usage((string) $form->key, app()->getLocale() === 'en' ? 'en' : 'ar');

        if ($usage !== []) {
            throw ValidationException::withMessages(['form' => [__('admin.forms.errors.in_use', ['places' => implode('، ', $usage)])]]);
        }

        $this->audit('form_definition.deleted', $form, $userId);
        $form->delete();
        $this->loaded = null;

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{key: string, name_ar: string, name_en: string, inbox: string, schema_json: array<string, mixed>, is_enabled: bool}
     */
    private function validated(array $data, ?FormDefinition $existing): array
    {
        $errors = [];
        $key = $existing instanceof FormDefinition ? (string) $existing->key : trim((string) ($data['key'] ?? ''));

        if (! $existing instanceof FormDefinition) {
            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) || strlen($key) > 80) {
                $errors['key'][] = __('admin.forms.errors.key_format');
            } elseif (FormDefinition::query()->where('key', $key)->exists()) {
                $errors['key'][] = __('admin.forms.errors.key_taken');
            }
        }

        $nameAr = trim((string) ($data['nameAr'] ?? ''));
        $nameEn = trim((string) ($data['nameEn'] ?? ''));

        foreach (['nameAr' => $nameAr, 'nameEn' => $nameEn] as $field => $value) {
            if ($value === '' || mb_strlen($value) > 255) {
                $errors[$field][] = __('admin.forms.errors.name_required');
            }
        }

        $inbox = FormSubmissionInbox::tryFrom((string) ($data['inbox'] ?? ''));

        if (! $inbox instanceof FormSubmissionInbox) {
            $errors['inbox'][] = __('admin.forms.errors.inbox_required');
        }

        $multiStep = (bool) ($data['multiStep'] ?? false);
        $steps = array_values(array_filter(is_array($data['steps'] ?? null) ? $data['steps'] : [], 'is_array'));

        if (! $multiStep) {
            $steps = [['titleAr' => '', 'titleEn' => '', 'fields' => array_merge(...array_map(static fn (array $step): array => array_values(array_filter(is_array($step['fields'] ?? null) ? $step['fields'] : [], 'is_array')), $steps ?: [[]]))]];
        }

        $names = [];
        $cleanSteps = [];

        foreach ($steps as $stepIndex => $step) {
            $titleAr = trim((string) ($step['titleAr'] ?? ''));
            $titleEn = trim((string) ($step['titleEn'] ?? ''));

            if ($multiStep && ($titleAr === '' || $titleEn === '')) {
                $errors['steps'][] = __('admin.forms.errors.step_title', ['step' => $stepIndex + 1]);
            }

            $fields = [];

            foreach (array_values(array_filter(is_array($step['fields'] ?? null) ? $step['fields'] : [], 'is_array')) as $field) {
                [$cleanField, $fieldErrors] = $this->validatedField($field);
                $name = $cleanField['name'];

                if ($name !== '' && in_array($name, $names, true)) {
                    $fieldErrors[] = __('admin.forms.errors.field_name_taken', ['name' => $name]);
                }

                $names[] = $name;
                $fields[] = $cleanField;

                foreach ($fieldErrors as $message) {
                    $errors['steps'][] = $message;
                }
            }

            if ($fields === []) {
                $errors['steps'][] = __('admin.forms.errors.step_empty', ['step' => $stepIndex + 1]);
            }

            $cleanSteps[] = ['titleAr' => $multiStep ? $titleAr : '', 'titleEn' => $multiStep ? $titleEn : '', 'fields' => $fields];
        }

        if ($cleanSteps === []) {
            $errors['steps'][] = __('admin.forms.errors.no_fields');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(static fn (array $messages): array => array_values(array_unique($messages)), $errors));
        }

        return [
            'key' => $key,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'inbox' => $inbox->value,
            'schema_json' => [
                'multiStep' => $multiStep,
                'submitLabelAr' => trim((string) ($data['submitLabelAr'] ?? '')),
                'submitLabelEn' => trim((string) ($data['submitLabelEn'] ?? '')),
                'steps' => $cleanSteps,
            ],
            'is_enabled' => (bool) ($data['isEnabled'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validatedField(array $field): array
    {
        $errors = [];
        $name = trim((string) ($field['name'] ?? ''));
        $type = (string) ($field['type'] ?? '');
        $labelAr = trim((string) ($field['labelAr'] ?? ''));
        $labelEn = trim((string) ($field['labelEn'] ?? ''));
        $shown = $labelEn !== '' ? $labelEn : ($name !== '' ? $name : '?');

        if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,59}$/', $name) || in_array($name, self::RESERVED_FIELD_NAMES, true)) {
            $errors[] = __('admin.forms.errors.field_name_format', ['name' => $name !== '' ? $name : $shown]);
        }

        if (! in_array($type, self::FIELD_TYPES, true)) {
            $errors[] = __('admin.forms.errors.field_type', ['name' => $shown]);
        }

        if ($labelAr === '' || $labelEn === '') {
            $errors[] = __('admin.forms.errors.field_label', ['name' => $shown]);
        }

        $clean = [
            'name' => $name,
            'type' => $type,
            'labelAr' => $labelAr,
            'labelEn' => $labelEn,
            'placeholderAr' => trim((string) ($field['placeholderAr'] ?? '')),
            'placeholderEn' => trim((string) ($field['placeholderEn'] ?? '')),
            'required' => (bool) ($field['required'] ?? false),
        ];

        if ($type === 'select') {
            $options = [];

            foreach (array_values(array_filter(is_array($field['options'] ?? null) ? $field['options'] : [], 'is_array')) as $option) {
                $value = trim((string) ($option['value'] ?? ''));
                $optionAr = trim((string) ($option['labelAr'] ?? ''));
                $optionEn = trim((string) ($option['labelEn'] ?? ''));

                if ($value === '' || $optionAr === '' || $optionEn === '' || isset($options[$value]) || str_contains($value, ',')) {
                    $errors[] = __('admin.forms.errors.option', ['name' => $shown]);

                    continue;
                }

                $options[$value] = ['value' => $value, 'labelAr' => $optionAr, 'labelEn' => $optionEn];
            }

            if ($options === []) {
                $errors[] = __('admin.forms.errors.options_required', ['name' => $shown]);
            }

            $clean['options'] = array_values($options);
        }

        if ($type === 'file') {
            $types = array_values(array_intersect(self::FILE_TYPES, array_map(
                static fn (mixed $type): string => strtolower(ltrim(trim((string) $type), '.')),
                is_array($field['fileTypes'] ?? null) ? $field['fileTypes'] : explode(',', (string) ($field['accept'] ?? '')),
            )));
            $maxKilobytes = (int) round(((float) ($field['maxMegabytes'] ?? (((int) ($field['maxKilobytes'] ?? 5120)) / 1024))) * 1024);

            if ($types === []) {
                $errors[] = __('admin.forms.errors.file_types', ['name' => $shown]);
            }

            if ($maxKilobytes < 1 || $maxKilobytes > 10240) {
                $errors[] = __('admin.forms.errors.file_size', ['name' => $shown]);
            }

            $clean['accept'] = implode(',', array_map(static fn (string $type): string => '.'.$type, $types));
            $clean['maxKilobytes'] = $maxKilobytes;
        }

        if (in_array($type, ['text', 'textarea'], true) && is_numeric($field['maxLength'] ?? null) && (int) $field['maxLength'] > 0) {
            $clean['maxLength'] = min((int) $field['maxLength'], $type === 'textarea' ? 10000 : 1000);
        }

        return [$clean, $errors];
    }

    /** @param array<string, mixed> $field @return array<string, mixed> */
    private function publicField(array $field): array
    {
        // A choice list opens on an empty "choose" entry, so a required one
        // is not answered by whatever happens to be listed first.
        if ($field['type'] === 'select') {
            $field['options'] = array_merge(
                [['value' => '', 'labelAr' => 'اختر…', 'labelEn' => 'Choose…']],
                $field['options'] ?? [],
            );
        }

        return $field;
    }

    private function toDto(FormDefinition $form): FormDefinitionDTO
    {
        $schema = is_array($form->schema_json) ? $form->schema_json : [];

        $steps = array_values(array_map(static fn (array $step): array => [
            'titleAr' => (string) ($step['titleAr'] ?? ''),
            'titleEn' => (string) ($step['titleEn'] ?? ''),
            'fields' => array_values(array_filter(is_array($step['fields'] ?? null) ? $step['fields'] : [], 'is_array')),
        ], array_filter(is_array($schema['steps'] ?? null) ? $schema['steps'] : [], 'is_array')));

        return new FormDefinitionDTO(
            id: (int) $form->getKey(),
            key: (string) $form->key,
            nameAr: (string) $form->name_ar,
            nameEn: (string) $form->name_en,
            inbox: FormSubmissionInbox::tryFrom((string) $form->inbox) ?? FormSubmissionInbox::SUGGESTIONS,
            isEnabled: (bool) $form->is_enabled,
            multiStep: (bool) ($schema['multiStep'] ?? false),
            submitLabelAr: (string) ($schema['submitLabelAr'] ?? ''),
            submitLabelEn: (string) ($schema['submitLabelEn'] ?? ''),
            steps: $steps,
            fields: array_merge([], ...array_column($steps, 'fields')),
        );
    }

    private function authorize(string $ability, FormDefinition|string $subject, int $userId): void
    {
        $user = User::query()->find($userId);

        if (! $user instanceof User || Gate::forUser($user)->denies($ability, $subject)) {
            throw new AuthorizationException('You may not manage forms.');
        }
    }

    private function audit(string $action, FormDefinition $form, int $userId): void
    {
        $this->loaded = null;
        // Pages that show the form are cached with its fields and on/off state.
        $this->cacheService->flushAll();
        $this->auditService->log($action, $userId, FormDefinition::class, (int) $form->getKey(), ['key' => $form->key]);
    }
}
