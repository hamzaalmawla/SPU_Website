<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Filament\Support\FormIdentifierGenerator;
use PHPUnit\Framework\TestCase;

final class FormIdentifierGeneratorTest extends TestCase
{
    public function test_generates_field_name_from_english_label(): void
    {
        $this->assertSame('full_name', FormIdentifierGenerator::generateFieldName('Full Name'));
        $this->assertSame('phone_number', FormIdentifierGenerator::generateFieldName('Phone Number'));
        $this->assertSame('high_school_score', FormIdentifierGenerator::generateFieldName('High School Score (%)'));
    }

    public function test_prepends_field_prefix_when_starting_with_a_number(): void
    {
        $this->assertSame('field_1st_choice', FormIdentifierGenerator::generateFieldName('1st Choice'));
        $this->assertSame('field_2026_intake', FormIdentifierGenerator::generateFieldName('2026 Intake'));
    }

    public function test_avoids_reserved_keywords(): void
    {
        $this->assertSame('field_website', FormIdentifierGenerator::generateFieldName('Website'));
        $this->assertSame('field_event_id', FormIdentifierGenerator::generateFieldName('Event ID'));
        $this->assertSame('field_form', FormIdentifierGenerator::generateFieldName('Form'));
    }

    public function test_generates_field_name_from_arabic_semantic_dictionary(): void
    {
        $this->assertSame('full_name', FormIdentifierGenerator::generateFieldName(null, 'الاسم الكامل'));
        $this->assertSame('student_id', FormIdentifierGenerator::generateFieldName(null, 'الرقم الجامعي'));
        $this->assertSame('national_id', FormIdentifierGenerator::generateFieldName(null, 'الرقم الوطني'));
        $this->assertSame('email', FormIdentifierGenerator::generateFieldName(null, 'البريد الإلكتروني'));
        $this->assertSame('phone', FormIdentifierGenerator::generateFieldName(null, 'رقم الهاتف'));
        $this->assertSame('birth_date', FormIdentifierGenerator::generateFieldName(null, 'تاريخ الميلاد'));
        $this->assertSame('cv_resume', FormIdentifierGenerator::generateFieldName(null, 'السيرة الذاتية'));
        $this->assertSame('academic_year', FormIdentifierGenerator::generateFieldName(null, 'السنة الدراسية'));
    }

    public function test_handles_arabic_diacritics_and_hamza_normalization(): void
    {
        $this->assertSame('email', FormIdentifierGenerator::generateFieldName(null, 'البريدُ الإلِكْترونيّ'));
        $this->assertSame('full_name', FormIdentifierGenerator::generateFieldName(null, 'الاسمُ الكامِل'));
        $this->assertSame('birth_date', FormIdentifierGenerator::generateFieldName(null, 'تاريخُ المِيلادِ'));
    }

    public function test_transliterates_custom_arabic_phrases(): void
    {
        $result = FormIdentifierGenerator::generateFieldName(null, 'شهادة دورة تدريبية');
        $this->assertNotEmpty($result);
        $this->assertMatchesRegularExpression('/^[a-zA-Z][a-zA-Z0-9_]*$/', $result);
    }

    public function test_deduplicates_field_names(): void
    {
        $existing = ['full_name', 'phone', 'phone_2'];

        $this->assertSame('full_name_2', FormIdentifierGenerator::generateFieldName('Full Name', null, $existing));
        $this->assertSame('phone_3', FormIdentifierGenerator::generateFieldName('Phone', null, $existing));
        $this->assertSame('email', FormIdentifierGenerator::generateFieldName('Email', null, $existing));
    }

    public function test_generates_kebab_case_form_key(): void
    {
        $this->assertSame('open-day-2026', FormIdentifierGenerator::generateFormKey('Open Day 2026'));
        $this->assertSame('student-council-registration', FormIdentifierGenerator::generateFormKey('Student Council Registration!'));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', FormIdentifierGenerator::generateFormKey(null, 'تسجيل اليوم المفتوح'));
    }

    public function test_generates_option_values(): void
    {
        $this->assertSame('computer_science', FormIdentifierGenerator::generateOptionValue('Computer Science'));
        $this->assertSame('yes', FormIdentifierGenerator::generateOptionValue(null, 'نعم'));
        $this->assertSame('no', FormIdentifierGenerator::generateOptionValue(null, 'لا'));
        $this->assertSame('male', FormIdentifierGenerator::generateOptionValue(null, 'ذكر'));
        $this->assertSame('female', FormIdentifierGenerator::generateOptionValue(null, 'أنثى'));
        $this->assertSame('first_year', FormIdentifierGenerator::generateOptionValue(null, 'السنة الأولى'));
    }
}
