<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\HomepageSectionServiceInterface;
use App\Filament\Support\HomepageFormSchema;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HomepageFormSchema.
 *
 * Validates Requirement 19.1: extracted section field builders produce expected field schemas.
 */
final class HomepageFormSchemaTest extends TestCase
{
    // ---------------------------------------------------------------
    // fieldsForSection() returns non-empty Component arrays for all 11 keys
    // ---------------------------------------------------------------

    #[Test]
    #[DataProvider('allSectionKeysProvider')]
    public function fields_for_section_returns_non_empty_component_array(string $sectionKey): void
    {
        $fields = HomepageFormSchema::fieldsForSection($sectionKey, 'data');

        $this->assertNotEmpty($fields, "fieldsForSection('{$sectionKey}') should return a non-empty array");

        foreach ($fields as $index => $field) {
            $this->assertInstanceOf(
                Component::class,
                $field,
                "fieldsForSection('{$sectionKey}')[{$index}] should be a Filament Component instance"
            );
        }
    }

    public static function allSectionKeysProvider(): array
    {
        return array_combine(
            HomepageSectionServiceInterface::SECTION_KEYS,
            array_map(
                static fn (string $key) => [$key],
                HomepageSectionServiceInterface::SECTION_KEYS,
            ),
        );
    }

    // ---------------------------------------------------------------
    // heroFields() returns expected field types
    // ---------------------------------------------------------------

    #[Test]
    public function hero_fields_returns_expected_field_types(): void
    {
        $fields = HomepageFormSchema::heroFields('data');

        // heroFields returns two Section components: "Hero Content" and "Call to Action"
        $this->assertCount(2, $fields);
        $this->assertInstanceOf(Section::class, $fields[0]);
        $this->assertInstanceOf(Section::class, $fields[1]);

        // Flatten all child components from both sections
        $childTypes = $this->flattenChildTypes($fields);

        $this->assertContains(FileUpload::class, $childTypes, 'heroFields should contain a FileUpload component');
        $this->assertContains(TextInput::class, $childTypes, 'heroFields should contain TextInput components');
        $this->assertContains(Textarea::class, $childTypes, 'heroFields should contain a Textarea component');
    }

    // ---------------------------------------------------------------
    // footerFields() returns expected repeater structures
    // ---------------------------------------------------------------

    #[Test]
    public function footer_fields_returns_expected_repeater_structures(): void
    {
        $fields = HomepageFormSchema::footerFields('data');

        // footerFields returns: Section (Brand & Contact), Repeater (Social Links),
        // Repeater (Contact Links), Repeater (Navigation Groups), Repeater (Legal Links), Section (Copyright)
        $this->assertCount(6, $fields);

        $this->assertInstanceOf(Section::class, $fields[0], 'First element should be Brand & Contact Section');
        $this->assertInstanceOf(Repeater::class, $fields[1], 'Second element should be Social Links Repeater');
        $this->assertInstanceOf(Repeater::class, $fields[2], 'Third element should be Contact Links Repeater');
        $this->assertInstanceOf(Repeater::class, $fields[3], 'Fourth element should be Navigation Groups Repeater');
        $this->assertInstanceOf(Repeater::class, $fields[4], 'Fifth element should be Legal Links Repeater');
        $this->assertInstanceOf(Section::class, $fields[5], 'Sixth element should be Copyright Section');
    }

    // ---------------------------------------------------------------
    // fieldsForSection() with unknown key throws UnhandledMatchError
    // ---------------------------------------------------------------

    #[Test]
    public function fields_for_section_throws_on_unknown_key(): void
    {
        $this->expectException(\UnhandledMatchError::class);

        HomepageFormSchema::fieldsForSection('nonexistent_section', 'data');
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Collect the class names of all child components nested inside Section wrappers.
     *
     * @param  array<int, Component>  $fields
     * @return array<int, class-string>
     */
    private function flattenChildTypes(array $fields): array
    {
        $types = [];

        foreach ($fields as $field) {
            if ($field instanceof Section) {
                foreach ($field->getChildComponents() as $child) {
                    $types[] = $child::class;
                }
            } else {
                $types[] = $field::class;
            }
        }

        return $types;
    }
}
