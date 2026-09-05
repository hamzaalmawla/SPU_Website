<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use App\DTOs\Form\DynamicFormSubmissionDetailDTO;
use App\DTOs\Form\SecureFormSubmissionDownloadDTO;
use Tests\TestCase;

/**
 * Architecture guard tests that enforce structural constraints.
 *
 * These tests prevent architectural drift by asserting that:
 * - Controllers do not import Eloquent models directly
 * - Controllers do not query the database or inject concrete services
 * - Contracts do not leak Eloquent types
 * - Middleware does not query Eloquent or contain domain persistence logic
 * - DTOs remain final readonly constructor-only data carriers unless allowlisted
 * - Support helpers remain DB-free except documented legacy import exceptions
 * - Filament pages/resources do not perform direct business database access
 * - Controllers and Filament do not call Actions directly
 * - Homepage section keys match the documented contract
 * - All service contracts have bindings in AppServiceProvider
 */
final class ArchitectureGuardTest extends TestCase
{
    /**
     * Controllers must not import Eloquent models directly.
     * They should depend on service interfaces from app/Contracts.
     */
    public function test_controllers_do_not_import_models(): void
    {
        $controllerDir = app_path('Http/Controllers');
        $violations = [];

        foreach ($this->phpFilesIn($controllerDir) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Match "use App\Models\..." imports
            if (preg_match_all('/^use\s+App\\\\Models\\\\(\w+)/m', $content, $matches)) {
                foreach ($matches[1] as $model) {
                    $violations[] = basename($file)." imports App\\Models\\{$model}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Controllers must not import Eloquent models directly:\n".implode("\n", $violations)
        );
    }

    public function test_controllers_do_not_query_database_or_inject_concrete_services(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(app_path('Http/Controllers')) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            foreach ($this->databaseUsageViolations($content) as $violation) {
                $violations[] = $this->relativePath($file).' '.$violation;
            }

            if (preg_match('/^use\s+App\\Services\\[^;]+;/m', $content) === 1) {
                $violations[] = $this->relativePath($file).' imports a concrete App\Services class';
            }

            if (preg_match('/(?:app|resolve)\(\s*\\?App\\Services\\[^:]+::class\s*\)/', $content) === 1) {
                $violations[] = $this->relativePath($file).' resolves a concrete App\Services class';
            }
        }

        $this->assertEmpty(
            $violations,
            "Controllers must stay thin and depend on service interfaces only:\n".implode("\n", $violations)
        );
    }

    public function test_contracts_do_not_leak_eloquent_or_untyped_methods(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(app_path('Contracts')) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (preg_match('/^use\s+App\\\\Models\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $this->relativePath($file).' imports App\Models';
            }

            if (preg_match('/^use\s+Illuminate\\\\Database\\\\Eloquent\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $this->relativePath($file).' imports Eloquent types';
            }

            if (preg_match('/\\\\?App\\\\Models\\\\[A-Za-z0-9_\\\\]+/', $content) === 1) {
                $violations[] = $this->relativePath($file).' references App\Models in a signature or PHPDoc';
            }

            if (preg_match('/:\s*(?:\\\\?Illuminate\\\\Database\\\\Eloquent\\\\|\\\\?App\\\\Models\\\\|Model\b|Builder\b)/', $content) === 1) {
                $violations[] = $this->relativePath($file).' has an Eloquent return type';
            }

            if (preg_match('/public\s+function\s+\w+\s*\([^)]*\)\s*;/', $content) === 1) {
                $violations[] = $this->relativePath($file).' has an untyped public method';
            }
        }

        $this->assertEmpty(
            $violations,
            "Contracts must expose DTO/scalar/bool/collection boundaries, not Eloquent:\n".implode("\n", $violations)
        );
    }

    public function test_middleware_does_not_query_eloquent_or_use_models(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(app_path('Http/Middleware')) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (preg_match('/^use\s+App\\\\Models\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $this->relativePath($file).' imports App\Models';
            }

            foreach ($this->databaseUsageViolations($content) as $violation) {
                $violations[] = $this->relativePath($file).' '.$violation;
            }
        }

        $this->assertEmpty(
            $violations,
            "Middleware must not contain Eloquent queries or domain persistence logic:\n".implode("\n", $violations)
        );
    }

    public function test_dtos_are_final_readonly_constructor_only_except_allowlist(): void
    {
        $allowedMethods = [
            'App\\DTOs\\Homepage\\HomepageDTO' => ['findSection'],
        ];

        $violations = [];

        foreach ($this->phpFilesIn(app_path('DTOs')) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $fqcn = $this->fqcnFromFile($content);

            if (preg_match('/final\s+readonly\s+class\s+\w+/', $content) !== 1) {
                $violations[] = $this->relativePath($file).' is not a final readonly class';
            }

            if (preg_match_all('/public\s+function\s+(\w+)\s*\(/', $content, $matches)) {
                foreach ($matches[1] as $method) {
                    if ($method === '__construct') {
                        continue;
                    }

                    if ($fqcn !== null && in_array($method, $allowedMethods[$fqcn] ?? [], true)) {
                        continue;
                    }

                    $violations[] = $this->relativePath($file).' has non-constructor public method '.$method.'()';
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "DTOs must stay final readonly constructor-only data carriers unless allowlisted:\n".implode("\n", $violations)
        );
    }

    public function test_support_helpers_do_not_access_database_except_legacy_import_allowlist(): void
    {
        $allowedLegacyImportFiles = [
            'app/Support/LegacyImport/MigrationLogger.php',
            'app/Support/LegacyImport/OldDatabaseConnection.php',
            'app/Support/LegacyImport/TargetIdResolver.php',
        ];

        $violations = [];

        foreach ($this->phpFilesIn(app_path('Support')) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $relativePath = $this->relativePath($file);
            if (in_array($relativePath, $allowedLegacyImportFiles, true)) {
                continue;
            }

            foreach ($this->databaseUsageViolations($content) as $violation) {
                $violations[] = $relativePath.' '.$violation;
            }

            if (preg_match('/^use\s+App\\\\Models\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $relativePath.' imports App\Models';
            }

            if (preg_match('/^use\s+Illuminate\\\\Database\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $relativePath.' imports Illuminate\Database';
            }
        }

        $this->assertEmpty(
            $violations,
            "Support helpers must be DB-free except documented LegacyImport allowlist:\n".implode("\n", $violations)
        );
    }

    public function test_controllers_and_filament_do_not_call_actions_directly(): void
    {
        $violations = [];
        $files = array_merge(
            $this->phpFilesIn(app_path('Http/Controllers')),
            $this->filamentFiles(),
        );

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            if (preg_match('/^use\s+App\\\\Actions\\\\[^;]+;/m', $content) === 1) {
                $violations[] = $this->relativePath($file).' imports App\Actions';
            }

            if (preg_match('/\\\\?App\\\\Actions\\\\[A-Za-z0-9_\\\\]+/', $content) === 1) {
                $violations[] = $this->relativePath($file).' references App\Actions directly';
            }
        }

        $this->assertEmpty(
            $violations,
            "Controllers and Filament must not call Actions directly:\n".implode("\n", $violations)
        );
    }

    /**
     * Filament pages and resources must not import workflow storage models
     * except when a Filament Resource declares its required $model property.
     */
    public function test_filament_does_not_import_forbidden_models(): void
    {
        $forbiddenModels = [
            'AuditLog',
            'HomepageDraft',
            'MenuItem',
            'PageDraft',
            'PageTranslation',
        ];

        $filamentDirs = [
            app_path('Filament/Pages'),
            app_path('Filament/Resources'),
        ];

        $violations = [];

        foreach ($filamentDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFilesIn($dir) as $file) {
                $content = file_get_contents($file);
                if ($content === false) {
                    continue;
                }

                foreach ($forbiddenModels as $model) {
                    $declaresResourceModel = $this->declaresFilamentModel($content, $model);

                    // Check for "use App\Models\{Model}" imports
                    if (preg_match('/^use\s+App\\\\Models\\\\'.$model.'\b/m', $content)) {
                        if (! $declaresResourceModel) {
                            $violations[] = basename($file)." imports App\\Models\\{$model}";
                        }
                    }

                    // Check for direct static calls like "HomepageDraft::query()"
                    if (preg_match('/\\\\'.$model.'::/', $content) || preg_match('/\b'.$model.'::/', $content)) {
                        // Allow $model property declarations
                        if (! $declaresResourceModel) {
                            $violations[] = basename($file)." uses {$model}:: directly";
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Filament files must not import forbidden models:\n".implode("\n", $violations)
        );
    }

    /**
     * Filament workflow code must use service contracts from app/Contracts.
     * Concrete service imports, service construction, or resolving concrete
     * services from the container would bypass the service boundary contract.
     */
    public function test_filament_uses_service_contracts_not_concrete_services(): void
    {
        $violations = [];

        foreach ($this->filamentFiles() as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $importedConcreteServices = [];

            if (preg_match_all('/^use\s+App\\\\Services\\\\([^;]+);/m', $content, $matches)) {
                foreach ($matches[1] as $service) {
                    $serviceName = preg_replace('/\s+as\s+\w+$/i', '', $service);
                    $serviceName = basename(str_replace('\\', '/', $serviceName ?? $service));

                    if ($serviceName !== '') {
                        $importedConcreteServices[] = $serviceName;
                    }

                    $violations[] = basename($file)." imports concrete service App\\Services\\{$service}";
                }
            }

            if (preg_match_all('/new\s+([A-Z][A-Za-z0-9_]*Service)\s*\(/', $content, $matches)) {
                foreach ($matches[1] as $service) {
                    if (in_array($service, $importedConcreteServices, true)) {
                        $violations[] = basename($file)." instantiates concrete service {$service}";
                    }
                }
            }

            if (preg_match('/new\s+\\\\?App\\\\Services\\\\[A-Z][A-Za-z0-9_]*Service\s*\(/', $content) === 1) {
                $violations[] = basename($file).' instantiates a concrete App\\Services class';
            }

            $serviceClassPattern = '(?:\\\\?App\\\\Services\\\\[A-Z][A-Za-z0-9_]*Service|[A-Z][A-Za-z0-9_]*Service)';

            if (preg_match('/(?:app|resolve)\(\s*'.$serviceClassPattern.'::class\s*\)/', $content) === 1) {
                $violations[] = basename($file).' resolves a concrete service class from the container';
            }

            if (preg_match('/(?:app\(\s*\)|\$this->app)->make\(\s*'.$serviceClassPattern.'::class\s*\)/', $content) === 1) {
                $violations[] = basename($file).' makes a concrete service class from the container';
            }
        }

        $this->assertEmpty(
            $violations,
            "Filament workflow code must depend on service interfaces only:\n".implode("\n", $violations)
        );
    }

    public function test_filament_database_access_is_limited_to_framework_model_rehydration(): void
    {
        $frameworkModelRehydration = [
            'app/Filament/Resources/DirectorateResource/Pages/CreateDirectorate.php' => '/return\s+Directorate::query\(\)->findOrFail\(\$prepared->entityId\);/',
            'app/Filament/Resources/FacultyMemberResource/Pages/CreateFacultyMember.php' => '/return\s+FacultyMember::query\(\)->findOrFail\(\$prepared->entityId\);/',
            'app/Filament/Resources/NewsArticleResource/Pages/CreateNewsArticle.php' => '/return\s+NewsArticle::query\(\)->findOrFail\(\$prepared->articleId\);/',
            'app/Filament/Resources/PartnershipResource/Pages/CreatePartnership.php' => '/return\s+Partnership::query\(\)->findOrFail\(\$prepared->entityId\);/',
            'app/Filament/Resources/PersonResource/Pages/CreatePerson.php' => '/return\s+Person::query\(\)->findOrFail\(\$prepared->entityId\);/',
            'app/Filament/Resources/UserResource/Pages/CreateUser.php' => '/return\s+User::query\(\)->findOrFail\(\$userId\);/',
        ];
        $violations = [];

        foreach ($this->filamentFiles() as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            $relativePath = $this->relativePath($file);
            foreach ($this->directFilamentDatabaseUsageViolations($content) as $violation) {
                if ($violation === 'calls ::query()' && isset($frameworkModelRehydration[$relativePath])) {
                    $queryCount = preg_match_all('/\b[A-Z][A-Za-z0-9_]*::query\s*\(/', $content);
                    if ($queryCount === 1 && preg_match($frameworkModelRehydration[$relativePath], $content) === 1) {
                        continue;
                    }
                }

                $violations[] = $relativePath.' '.$violation;
            }
        }

        $this->assertEmpty(
            $violations,
            "Filament business reads and writes must cross service contracts:\n".implode("\n", $violations)
        );
    }

    public function test_sensitive_service_contract_methods_require_an_actor_and_enforce_policy_before_pii_mapping(): void
    {
        $sensitiveReturnTypes = [
            DynamicFormSubmissionDetailDTO::class,
            SecureFormSubmissionDownloadDTO::class,
        ];
        $violations = [];

        foreach ($this->phpFilesIn(app_path('Contracts')) as $file) {
            $content = file_get_contents($file);
            if ($content === false || ! preg_match('/namespace\s+([\w\\\\]+);/', $content, $namespaceMatch)
                || ! preg_match('/interface\s+(\w+)/', $content, $interfaceMatch)) {
                continue;
            }

            $interface = $namespaceMatch[1].'\\'.$interfaceMatch[1];
            foreach ((new \ReflectionClass($interface))->getMethods() as $method) {
                $returnType = $method->getReturnType();
                if (! $returnType instanceof \ReflectionNamedType || ! in_array($returnType->getName(), $sensitiveReturnTypes, true)) {
                    continue;
                }

                $actor = collect($method->getParameters())
                    ->first(fn (\ReflectionParameter $parameter): bool => $parameter->getName() === 'actorId');
                if (! $actor instanceof \ReflectionParameter
                    || ! $actor->getType() instanceof \ReflectionNamedType
                    || $actor->getType()?->getName() !== 'int') {
                    $violations[] = $interface.'::'.$method->getName().'() does not require int $actorId';
                }
            }
        }

        $service = file_get_contents(app_path('Services/Form/DynamicFormSubmissionReviewService.php'));
        $authorization = is_string($service)
            ? strpos($service, '$this->authorizedActor($actorId, \'view\', $submission);')
            : false;
        $piiMapping = is_string($service) ? strpos($service, 'applicantName:') : false;
        if ($authorization === false || $piiMapping === false || $authorization > $piiMapping) {
            $violations[] = 'DynamicFormSubmissionReviewService::getDetails() does not authorize before mapping PII';
        }

        $this->assertEmpty(
            $violations,
            "Sensitive service methods must require and enforce actor authorization:\n".implode("\n", $violations)
        );
    }

    /**
     * The homepage section keys constant must match the documented 11-key contract.
     */
    public function test_homepage_section_keys_match_architecture_doc(): void
    {
        $expectedKeys = [
            'hero',
            'hero_stats',
            'achievements_highlights',
            'academic_faculties',
            'choose_your_path',
            'university_news',
            'research_studies',
            'events_activities',
            'medical_facilities_services',
            'bottom_stats',
            'footer',
        ];

        $this->assertSame(
            $expectedKeys,
            HomepageSectionServiceInterface::SECTION_KEYS,
            'HomepageSectionServiceInterface::SECTION_KEYS must match the documented 11-section contract'
        );

        $this->assertCount(
            11,
            HomepageSectionServiceInterface::SECTION_KEYS,
            'Homepage must have exactly 11 approved section keys'
        );
    }

    /**
     * All service contracts in app/Contracts must have bindings in AppServiceProvider.
     */
    public function test_all_contracts_have_bindings(): void
    {
        $contractDir = app_path('Contracts');
        $unbound = [];

        foreach ($this->phpFilesIn($contractDir) as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Extract the fully qualified interface name
            if (preg_match('/namespace\s+([\w\\\\]+);/', $content, $nsMatch)
                && preg_match('/interface\s+(\w+)/', $content, $ifMatch)) {
                $fqcn = $nsMatch[1].'\\'.$ifMatch[1];

                // Check if the container can resolve this interface
                if (! $this->app->bound($fqcn)) {
                    $unbound[] = $fqcn;
                }
            }
        }

        $this->assertEmpty(
            $unbound,
            "All contracts must have bindings in AppServiceProvider:\n".implode("\n", $unbound)
        );
    }

    /**
     * The legacy HTTP Kernel file must not exist (Laravel 11+ uses bootstrap/app.php).
     */
    public function test_no_legacy_http_kernel(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Kernel.php'),
            'Legacy app/Http/Kernel.php must not exist in Laravel 11+ projects'
        );
    }

    /**
     * Recursively find all .php files in a directory.
     *
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * @return list<string>
     */
    private function filamentFiles(): array
    {
        return array_merge(
            $this->phpFilesIn(app_path('Filament/Pages')),
            $this->phpFilesIn(app_path('Filament/Resources')),
        );
    }

    private function declaresFilamentModel(string $content, string $model): bool
    {
        return preg_match('/\$model\s*=\s*'.$model.'::class/', $content) === 1;
    }

    /**
     * @return list<string>
     */
    private function databaseUsageViolations(string $content): array
    {
        $patterns = [
            '/^use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/m' => 'imports DB facade',
            '/^use\s+Illuminate\\\\Support\\\\Facades\\\\Schema;/m' => 'imports Schema facade',
            '/\bDB::/' => 'uses DB facade',
            '/\bSchema::/' => 'uses Schema facade',
            '/::query\s*\(/' => 'calls ::query()',
            '/::where\s*\(/' => 'calls ::where()',
            '/::find\s*\(/' => 'calls ::find()',
            '/::create\s*\(/' => 'calls ::create()',
            '/->save\s*\(/' => 'calls ->save()',
        ];

        $violations = [];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $content) === 1) {
                $violations[] = $message;
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private function directFilamentDatabaseUsageViolations(string $content): array
    {
        $patterns = [
            '/^use\s+Illuminate\\\\Support\\\\Facades\\\\DB;/m' => 'imports DB facade',
            '/^use\s+Illuminate\\\\Support\\\\Facades\\\\Schema;/m' => 'imports Schema facade',
            '/\bDB::/' => 'uses DB facade',
            '/\bSchema::/' => 'uses Schema facade',
            '/\b[A-Z][A-Za-z0-9_]*::query\s*\(/' => 'calls ::query()',
            '/\b[A-Z][A-Za-z0-9_]*::(?:where|find|create|updateOrCreate|firstOrCreate)\s*\(/' => 'calls a direct static persistence method',
            '/\$(?:record|this->record)->(?:save|update|delete)\s*\(/' => 'writes a resource record directly',
        ];
        $violations = [];

        foreach ($patterns as $pattern => $message) {
            if (preg_match($pattern, $content) === 1) {
                $violations[] = $message;
            }
        }

        return $violations;
    }

    private function fqcnFromFile(string $content): ?string
    {
        if (! preg_match('/namespace\s+([\w\\\\]+);/', $content, $namespaceMatch)) {
            return null;
        }

        if (! preg_match('/class\s+(\w+)/', $content, $classMatch)) {
            return null;
        }

        return $namespaceMatch[1].'\\'.$classMatch[1];
    }

    private function relativePath(string $file): string
    {
        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $file));
    }
}
