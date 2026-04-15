# STYLEGUIDE.md

## PHP Standards

- PHP 8.2+
- `declare(strict_types=1)` required in every PHP file
- Readonly DTOs for all entity data transfer
- No mixed return types on public service methods unless absolutely unavoidable

---

## Naming

- Interfaces: `SomethingServiceInterface` in `app/Contracts/`
- Services: `SomethingService` in `app/Services/`
- Placeholder services: `SomethingServicePlaceholder` in `app/Services/Placeholders/`
- Controllers: `SomethingController`
- Requests: `SomethingRequest`
- DTOs: `EntityNameDTO` in `app/DTOs/`
- Policies: `EntityNamePolicy`

---

## Return Type Standards

| Scenario | Return Type |
|---|---|
| Single entity found | `EntityDTO` |
| Single entity not found | `null` |
| Multiple entities | `Collection` with PHPDoc DTO type |
| Paginated results | `LengthAwarePaginator` |
| Write operation success/failure | `bool` |
| Composite homepage / navigation / builder payload | `array` |
| Never | `array<string,mixed>` for entity data |
| Never | Eloquent model from any interface method |

---

## DTO Structure

```php
<?php
declare(strict_types=1);

namespace App\DTOs;

final readonly class PageDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $locale,
        public string $title,
        public string $status,
        public bool $isEnabled,
    ) {}
}
```

Rules:
- `final readonly class` always
- No methods
- No logic
- Constructor property promotion only
- No defaults unless truly optional
- Located in `app/DTOs/` only

---

## Service toDTO() Convention

Every service implementation maps model to DTO via a private method when entity mapping is needed:

```php
private function toDTO(Page $model, string $locale): PageDTO
{
    $translation = $model->translation($locale);

    return new PageDTO(
        id: $model->id,
        slug: $model->slug,
        locale: $locale,
        title: $translation?->title ?? '',
        status: $model->status,
        isEnabled: (bool) $model->is_enabled,
    );
}
```

Rules:
- One clear mapping path per service
- Always use named arguments in DTO constructors
- Resolve locale-specific translation through model helper methods or relationship access
- Do not scatter mapping logic across controllers, views, or random helper classes

---

## Method Order in Classes

1. Public methods
2. Protected methods
3. Private methods

Within each group:
- constructor first
- then alphabetical where practical

---

## Formatting

- One responsibility per method
- Prefer small, focused methods
- Clear, descriptive variable naming
- No vague abbreviations
- Prefer early returns over deeply nested conditionals
- Keep validation and transformation logic readable

---

## PHPDoc Standards

Required on:
- all interface files
- all interface methods
- all DTO classes
- all public service methods
- collection returns must specify DTO type, for example:
  - `@return Collection<PageDTO>`

Optional / not required on:
- obvious private mapping methods
- simple model scopes
- trivial private helper methods with self-explanatory names

---

## Exceptions

- Use descriptive exception messages
- No `"Something went wrong"` messages
- No silent failures except the honeypot case where explicitly required
- Use `RuntimeException` with TODO note only for intentional short-lived stubs
- Never swallow exceptions inside the service layer unless converting them deliberately to a known domain outcome

---

## Consistency Rule

All services follow the same structural pattern:
- constructor injection
- public interface methods
- protected helpers only if truly needed
- private mapping / helper methods last

All DTOs follow the same structural pattern:
- `final readonly class`
- constructor property promotion only
- no methods
- no logic

If one service handles DTO mapping a certain way, all services should follow the same pattern.
