<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Contracts\Form\FormDefinitionServiceInterface;
use App\Support\FormLink;
use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

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

    /**
     * The choice between one of our forms and a form on another site, then
     * the form dropdown or, for another site, the field for its address.
     *
     * @param  bool|Closure  $required  Whether a form (or an address) must be given.
     * @return array<int, Component>
     */
    public static function withSource(
        string $idName = 'formId',
        string $sourceName = 'formSource',
        string $urlName = 'formUrl',
        bool|Closure $required = true,
        ?string $label = null,
        ?string $placeholder = null,
    ): array {
        $isExternal = static fn (Get $get): bool => $get($sourceName) === FormLink::EXTERNAL;

        $picker = self::make($idName)
            ->visible(fn (Get $get): bool => ! $isExternal($get))
            ->required(fn (Component $component, Get $get): bool => ! $isExternal($get) && (bool) $component->evaluate($required));

        if ($label !== null) {
            $picker->label($label);
        }

        if ($placeholder !== null) {
            $picker->placeholder($placeholder);
        }

        return [
            Radio::make($sourceName)
                ->label(__('admin.forms.fields.form_source'))
                ->options([
                    FormLink::INTERNAL => __('admin.forms.sources.internal'),
                    FormLink::EXTERNAL => __('admin.forms.sources.external'),
                ])
                ->default(FormLink::INTERNAL)
                ->inline()
                ->live()
                ->afterStateHydrated(function (Radio $component, Get $get, mixed $state) use ($idName, $urlName): void {
                    if (in_array($state, FormLink::SOURCES, true)) {
                        return;
                    }

                    // Saved before the choice existed: a bare web address and no form means another site.
                    $hasUrl = FormLink::sanitizeUrl($get($urlName)) !== null;
                    $component->state($hasUrl && trim((string) $get($idName)) === '' ? FormLink::EXTERNAL : FormLink::INTERNAL);
                })
                ->afterStateUpdated(function (Get $get, Set $set, mixed $state) use ($urlName): void {
                    // An address the site built for its own form is not an external form.
                    if ($state === FormLink::EXTERNAL && FormLink::sanitizeUrl($get($urlName)) === null) {
                        $set($urlName, null);
                    }
                })
                ->columnSpanFull(),
            $picker,
            TextInput::make($urlName)
                ->label(__('admin.forms.fields.external_url'))
                ->helperText(__('admin.forms.help.external_url'))
                ->placeholder('https://')
                ->url()
                ->maxLength(2048)
                ->visible($isExternal)
                ->required(fn (Component $component, Get $get): bool => $isExternal($get) && (bool) $component->evaluate($required))
                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && trim($value) !== '' && FormLink::sanitizeUrl($value) === null) {
                        $fail(__('admin.forms.validation.external_url'));
                    }
                })
                ->columnSpanFull(),
        ];
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
