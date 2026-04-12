# STYLEGUIDE.md

## PHP Standards

- PHP 8.2+
- declare(strict_types=1) required in every file
- Readonly DTOs for all entity data transfer
- No mixed return types on public service methods

---

## Naming

- Interfaces: SomethingServiceInterface (in app/Contracts/)
- Services: SomethingService (in app/Services/)
- Placeholder services: SomethingServicePlaceholder (in app/Services/Placeholders/)
- Controllers: SomethingController
- Requests: SomethingRequest
- DTOs: EntityNameDTO (in app/DTOs/)
- Policies: EntityNamePolicy

---

## Return Type Standards

| Scenario | Return Type |
|---|---|
| Single entity found | EntityDTO |
| Single entity not found | null |
| Multiple entities | Collection (PHPDoc: Collection<EntityDTO>) |
| Paginated results | LengthAwarePaginator |
| Write operation success/failure | bool |
| Composite view data (homepage) | array |
| Never | array<string,mixed> for entity data |
| Never | Eloquent model from any interface method |

---

## DTO Structure

```php
<?php
declare(strict_types=1);

namespace App\DTOs;

final readonly class ExampleDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $nameAr,
        public string $nameEn,
        public bool $isActive,
    ) {}
}
```

Rules:
- final readonly class — always
- No methods, no logic
- No defaults unless truly optional
- Constructor property promotion only
- Located in app/DTOs/ exclusively

---

## Service toDTO() Convention

Every service implementation maps Model to DTO via a private method:

```php
private function toDTO(Faculty $model): FacultyDTO
{
    return new FacultyDTO(
        id: $model->id,
        slug: $model->slug,
        nameAr: $model->translate('name', 'ar'),
        nameEn: $model->translate('name', 'en'),
        isActive: (bool) $model->is_active,
    );
}
```

Rules:
- One toDTO() per service — no mapping logic elsewhere
- Always use named arguments in DTO constructors
- Fetch translations via translate() helper, not raw DB

---

## Method Order in Classes

1. Public methods
2. Protected methods
3. Private methods

Within each group: constructors first, then alphabetical.

---

## Formatting

- One responsibility per method
- Prefer small, focused methods
- Clear, descriptive variable naming
- No abbreviations unless universally understood (e.g. $id, $dto)

---

## PHPDoc Standards

Required on:
- All interface files (class-level)
- All interface methods
- All DTO classes
- All service class public methods
- Collection returns must specify type: @return Collection<FacultyDTO>

Not required on:
- Private toDTO() mapping methods (self-documenting)
- Simple model scopes

---

## Exceptions

- Descriptive messages — no 'Something went wrong'
- No silent failures except the honeypot case (ContactController)
- RuntimeException with TODO note for stubbed methods in P03
- Never swallow exceptions in service layer

---

## Consistency Rule

- All services follow identical structural pattern:
  constructor injection → public interface methods → private toDTO()
- All DTOs follow identical structural pattern:
  final readonly class → constructor property promotion only
- If one service does it a certain way → all services do it that way
