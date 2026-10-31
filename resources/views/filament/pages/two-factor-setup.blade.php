<x-filament-panels::page>
    <script src="{{ asset('js/qrious.min.js') }}"></script>

    <x-admin.cms-shell area="security" :locales="[]">
        @if ($twoFactorEnabled)
            <div class="rounded-lg bg-success-50 dark:bg-success-500/10 p-4 ring-1 ring-success-200 dark:ring-success-500/20">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shield-check class="h-5 w-5 text-success-600 dark:text-success-400" />
                    <span class="font-medium text-success-800 dark:text-success-200">
                        {{ __('admin_pages.two_factor.status_enabled') }}
                    </span>
                </div>
            </div>
        @else
            <div class="rounded-lg bg-warning-50 dark:bg-warning-500/10 p-4 ring-1 ring-warning-200 dark:ring-warning-500/20">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-shield-exclamation class="h-5 w-5 text-warning-600 dark:text-warning-400" />
                    <span class="font-medium text-warning-800 dark:text-warning-200">
                        @if ($twoFactorUnconfirmed)
                            {{ __('admin_pages.two_factor.status_incomplete') }}
                        @else
                            {{ __('admin_pages.two_factor.status_not_enabled') }}
                        @endif
                    </span>
                </div>
            </div>
            @if ($this->requiresTwoFactor())
                <p class="mt-3 text-sm text-warning-800 dark:text-warning-200">
                    {{ __('admin_pages.two_factor.confirm_enrollment_before_privileged_access') }}
                </p>
            @endif
        @endif

        {{-- Enrollment Flow --}}
        @if ($showEnrollment)
            <x-filament::section>
                <x-slot name="heading">{{ __('admin_pages.two_factor.set_up_two_factor_authentication') }}</x-slot>
                <x-slot name="description">
                    {{ __('admin_pages.two_factor.scan_the_qr_code') }}
                </x-slot>

                <div class="space-y-4">
                    {{-- Visual QR Code Card --}}
                    <div class="flex flex-col items-center justify-center p-4 bg-white rounded-lg border border-gray-200 dark:border-gray-700 w-fit mx-auto shadow-sm">
                        <div
                            x-data="{
                                qrUrl: '{{ $qrCodeUrl }}',
                                init() {
                                    if (typeof QRious !== 'undefined') {
                                        new QRious({
                                            element: this.$refs.canvas,
                                            value: this.qrUrl,
                                            size: 200,
                                            level: 'M'
                                        });
                                    }
                                }
                            }"
                        >
                            <canvas x-ref="canvas" wire:ignore></canvas>
                        </div>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ __('admin_pages.two_factor.add_this_setup_uri') }}
                        </p>
                        <code class="block max-w-full overflow-x-auto rounded bg-gray-100 dark:bg-gray-800 px-3 py-2 text-sm font-mono select-all">
                            {{ $qrCodeUrl }}
                        </code>
                    </div>

                    {{-- Manual Entry Secret --}}
                    <div>
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('admin_pages.two_factor.or_enter_this_secret_manually') }}
                        </p>
                        <code class="block rounded bg-gray-100 dark:bg-gray-800 px-3 py-2 text-sm font-mono select-all">
                            {{ $secret }}
                        </code>
                    </div>

                    {{-- Confirmation Form --}}
                    <div class="pt-4 border-t dark:border-gray-700">
                        <form wire:submit="confirmEnrollment">
                            {{ $this->form }}

                            <div class="mt-4">
                                <x-filament::button type="submit" color="success">
                                    {{ __('admin_pages.two_factor.confirm_and_enable') }}
                                </x-filament::button>
                            </div>
                        </form>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- Recovery Codes --}}
        @if ($showRecoveryCodes && count($recoveryCodes) > 0)
            <x-filament::section>
                <x-slot name="heading">{{ __('admin_pages.two_factor.recovery_codes') }}</x-slot>
                <x-slot name="description">
                    {{ __('admin_pages.two_factor.recovery_codes_help') }}
                </x-slot>

                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($recoveryCodes as $code)
                            <code class="block rounded bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-center select-all">
                                {{ $code }}
                            </code>
                        @endforeach
                    </div>
                </div>
            </x-filament::section>
        @endif
    </x-admin.cms-shell>
</x-filament-panels::page>
