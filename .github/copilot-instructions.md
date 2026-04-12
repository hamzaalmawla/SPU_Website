# Copilot Instructions — SPU Website Backend

## Project Stack
Laravel 11, Filament v3, MySQL 8, Redis, Meilisearch, PHP 8.2

## Architecture Rules — Enforce These in All Reviews

### Layer boundaries
- Eloquent models are ONLY allowed inside app/Services/
- app/Contracts/ interfaces must never import Eloquent model types
- app/Http/Controllers/ must never import models or run queries
- app/Filament/ resources must never import models or run queries
- app/DTOs/ classes must have no methods and no logic

### Return type rules
- Interface methods returning a single entity must return a DTO or null
- Interface methods returning multiple entities must return Collection
- Write operations must return bool
- No interface method may return array<string,mixed> for entity data
- No interface method may return an Eloquent model

### Service rules
- Every service must implement its interface from app/Contracts/
- Every service must have a private toDTO() mapping method
- Services must use constructor injection — never new SomeService()
- No static methods on services

### DTO rules
- All DTOs in app/DTOs/ must be final readonly classes
- No methods or logic inside DTOs
- Constructor property promotion only

### Auth rules
- 5 failed login attempts locks the account
- Locked accounts cannot login even with correct password
- faculty_editor role must be scoped to own faculty_id in ALL queries

### Never acceptable
- Business logic in controllers or models
- Hardcoded Arabic or English strings outside lang files
- Direct DB queries outside services
- Eloquent model returned from any service interface method