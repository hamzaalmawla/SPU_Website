<x-admin.auth.layout :title="__('admin.auth.two_factor_title')">
    <h2 class="form-heading">{{ __('admin.auth.two_factor_heading') }}</h2>
    <p class="form-copy">{{ __('admin.auth.two_factor_subheading') }}</p>

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.two-factor.verify') }}" novalidate>
        @csrf

        <div class="field">
            <label for="code">{{ __('admin.auth.code') }}</label>
            <input
                id="code"
                name="code"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="{{ __('admin.auth.code_placeholder') }}"
                required
                autofocus
            >
            @error('code')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-row"></div>

        <button class="primary-button" type="submit">{{ __('admin.auth.verify') }}</button>
    </form>

    <p class="security-note">{{ __('admin.auth.two_factor_note') }}</p>
</x-admin.auth.layout>
