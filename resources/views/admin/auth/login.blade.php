<x-admin.auth.layout :title="__('admin.auth.login_title')">
    <h2 class="form-heading">{{ __('admin.auth.login_heading') }}</h2>
    <p class="form-copy">{{ __('admin.auth.login_subheading') }}</p>

    @if ($errors->any())
        <div class="alert" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('admin.login.attempt') }}" novalidate>
        @csrf

        <div class="field">
            <label for="email">{{ __('admin.auth.email') }}</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            @error('email')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="field">
            <label for="password">{{ __('admin.auth.password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            @error('password')
                <p class="field-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="form-row">
            <label class="remember" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                {{ __('admin.auth.remember') }}
            </label>
        </div>

        <button class="primary-button" type="submit">{{ __('admin.auth.sign_in') }}</button>
    </form>

    <p class="security-note">{{ __('admin.auth.security_note') }}</p>
</x-admin.auth.layout>
