<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Form\FormDefinitionServiceInterface;
use Filament\Forms\Components\Select;

/**
 * The dropdown a page uses to pick one of the forms built under Forms. A form
 * that was switched off after being picked stays listed (marked) so the page
 * still shows what it holds.
 */
final class FormPicker
{
    public static function make(string $name = 'formId'): Select
    {
        return Select::make($name)
            ->options(fn (?string $state): array => self::options($state))
            ->helperText(__('admin.forms.help.select_form'));
    }

    /** @return array<string, string> */
    public static function options(?string $current = null): array
    {
        $service = app(FormDefinitionServiceInterface::class);
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $options = $service->options($locale);
        $current = trim((string) $current);

        if ($current !== '' && ! isset($options[$current])) {
            $form = $service->find($current);
            $options[$current] = __('admin.forms.disabled_option', ['name' => $form === null ? $current : ($locale === 'ar' ? $form->nameAr : $form->nameEn)]);
        }

        return $options;
    }
}
