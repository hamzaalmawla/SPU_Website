<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Homepage\HomepageSectionServiceInterface;
use Tests\TestCase;

/**
 * Architecture guard tests that enforce structural constraints.
 *
 * These tests prevent architectural drift by asserting that:
 * - Controllers do not import Eloquent models directly
 * - Filament pages/resources do not import forbidden models
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
                    $violations[] = basename($file) . " imports App\\Models\\{$model}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Controllers must not import Eloquent models directly:\n" . implode("\n", $violations)
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
                    if (preg_match('/^use\s+App\\\\Models\\\\' . $model . '\b/m', $content)) {
                        if (! $declaresResourceModel) {
                            $violations[] = basename($file) . " imports App\\Models\\{$model}";
                        }
                    }

                    // Check for direct static calls like "HomepageDraft::query()"
                    if (preg_match('/\\\\' . $model . '::/', $content) || preg_match('/\b' . $model . '::/', $content)) {
                        // Allow $model property declarations
                        if (! $declaresResourceModel) {
                            $violations[] = basename($file) . " uses {$model}:: directly";
                        }
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Filament files must not import forbidden models:\n" . implode("\n", $violations)
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

                    $violations[] = basename($file) . " imports concrete service App\\Services\\{$service}";
                }
            }

            if (preg_match_all('/new\s+([A-Z][A-Za-z0-9_]*Service)\s*\(/', $content, $matches)) {
                foreach ($matches[1] as $service) {
                    if (in_array($service, $importedConcreteServices, true)) {
                        $violations[] = basename($file) . " instantiates concrete service {$service}";
                    }
                }
            }

            if (preg_match('/new\s+\\\\?App\\\\Services\\\\[A-Z][A-Za-z0-9_]*Service\s*\(/', $content) === 1) {
                $violations[] = basename($file) . ' instantiates a concrete App\\Services class';
            }

            $serviceClassPattern = '(?:\\\\?App\\\\Services\\\\[A-Z][A-Za-z0-9_]*Service|[A-Z][A-Za-z0-9_]*Service)';

            if (preg_match('/(?:app|resolve)\(\s*' . $serviceClassPattern . '::class\s*\)/', $content) === 1) {
                $violations[] = basename($file) . ' resolves a concrete service class from the container';
            }

            if (preg_match('/(?:app\(\s*\)|\$this->app)->make\(\s*' . $serviceClassPattern . '::class\s*\)/', $content) === 1) {
                $violations[] = basename($file) . ' makes a concrete service class from the container';
            }
        }

        $this->assertEmpty(
            $violations,
            "Filament workflow code must depend on service interfaces only:\n" . implode("\n", $violations)
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
            'academic_faculties',
            'achievements_highlights',
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
                $fqcn = $nsMatch[1] . '\\' . $ifMatch[1];

                // Check if the container can resolve this interface
                if (! $this->app->bound($fqcn)) {
                    $unbound[] = $fqcn;
                }
            }
        }

        $this->assertEmpty(
            $unbound,
            "All contracts must have bindings in AppServiceProvider:\n" . implode("\n", $unbound)
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
        return preg_match('/\$model\s*=\s*' . $model . '::class/', $content) === 1;
    }
}
