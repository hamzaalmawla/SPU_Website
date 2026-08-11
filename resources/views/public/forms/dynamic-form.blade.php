<div x-data="dynamicFormView()" x-cloak>
    <template x-if="$store.dynamicForm.schema">
    <div>
    <input type="text" name="website" value="" data-form-honeypot tabindex="-1" autocomplete="off" class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-label="Website">
    <div x-show="$store.dynamicForm.isPreview" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800" x-text="$store.dynamicForm.isPreview ? previewText() : ''"></div>
    <div x-show="$store.dynamicForm.submitted" class="rounded-2xl border border-green-200 bg-green-50 p-8 text-center">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
            <svg class="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-xl font-bold text-green-800" x-text="successTitle()"></h3>
        <p class="mt-2 text-sm text-green-600" x-text="successText()"></p>
        <button type="button" x-on:click="$store.dynamicForm.close()" class="mt-6 inline-flex items-center gap-2 rounded-lg bg-green-600 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-green-700" x-text="closeText()"></button>
    </div>

    <template x-if="$store.dynamicForm.submitError">
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700" x-text="$store.dynamicForm.submitError"></div>
    </template>

    <fieldset x-bind:disabled="$store.dynamicForm.isPreview" x-show="!$store.dynamicForm.submitted && $store.dynamicForm.isMultiStep">
        <div class="mb-8">
             <div class="flex min-w-max items-center justify-between overflow-x-auto pb-2">
                <template x-for="(step, idx) in $store.dynamicForm.schema.steps" x-bind:key="idx">
                     <div class="flex min-w-[3.25rem] flex-1 items-center">
                        <button type="button" x-on:click="$store.dynamicForm.goToStep(idx)" class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 text-xs font-bold transition-all" x-bind:class="stepButtonClass(idx)">
                            <template x-if="$store.dynamicForm.completedSteps.includes(idx) && idx !== $store.dynamicForm.currentStep">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </template>
                            <template x-if="!$store.dynamicForm.completedSteps.includes(idx) || idx === $store.dynamicForm.currentStep">
                                <span x-text="idx + 1"></span>
                            </template>
                        </button>
                        <span class="ms-2 hidden text-[11px] font-bold md:block" x-bind:class="stepTextClass(idx)" x-text="stepTitle(step)"></span>
                        <div x-show="idx < ($store.dynamicForm.schema.steps.length - 1)" class="mx-2 hidden h-0.5 flex-1 md:block" x-bind:class="connectorClass(idx)"></div>
                    </div>
                </template>
            </div>
            <div class="mt-4 text-center md:hidden">
                <p class="text-sm font-bold text-spu-blue" x-text="stepTitle($store.dynamicForm.currentStepSchema)"></p>
                <p class="mt-1 text-xs text-gray-500"><span x-text="$store.dynamicForm.currentStep + 1"></span> / <span x-text="$store.dynamicForm.totalSteps"></span></p>
            </div>
        </div>

        <div class="mb-6 hidden md:block">
            <h3 class="text-lg font-bold text-spu-blue" x-text="stepTitle($store.dynamicForm.currentStepSchema)"></h3>
            <p class="mt-1 text-xs text-gray-500"><span x-text="currentStepLabel"></span><span x-text="$store.dynamicForm.currentStep + 1"></span><span> / </span><span x-text="$store.dynamicForm.totalSteps"></span></p>
        </div>

        <form x-on:submit.prevent="$store.dynamicForm.handleSubmit()" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <template x-for="field in $store.dynamicForm.currentStepFields" x-bind:key="field.name">
                    @include('public.forms.partials.dynamic-field')
                </template>
            </div>

             <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <button type="button" x-show="$store.dynamicForm.currentStep > 0" x-on:click="$store.dynamicForm.prevStep()" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 transition hover:bg-gray-50">
                    <svg class="h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    <span x-text="previousText()"></span>
                </button>
                <div x-show="$store.dynamicForm.currentStep === 0"></div>

                <button type="submit" x-bind:disabled="$store.dynamicForm.submitting" class="inline-flex items-center gap-2 rounded-lg bg-spu-red px-6 py-3 text-sm font-bold text-white transition-all hover:bg-spu-red/90 hover:shadow-lg active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60">
                    <span x-show="$store.dynamicForm.currentStep < $store.dynamicForm.totalSteps - 1 && !$store.dynamicForm.submitting"><span x-text="nextText()"></span><svg class="ms-1 inline-block h-4 w-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></span>
                    <span x-show="$store.dynamicForm.currentStep === $store.dynamicForm.totalSteps - 1 && !$store.dynamicForm.submitting" x-text="submitText()"></span>
                    <span x-show="$store.dynamicForm.submitting" class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span x-text="submittingText()"></span></span>
                </button>
            </div>
        </form>
    </fieldset>

    <fieldset x-bind:disabled="$store.dynamicForm.isPreview" x-show="!$store.dynamicForm.submitted && !$store.dynamicForm.isMultiStep">
        <form x-on:submit.prevent="$store.dynamicForm.handleSubmit()" class="space-y-5">
            <template x-for="field in $store.dynamicForm.schema.fields" x-bind:key="field.name">
                @include('public.forms.partials.dynamic-field')
            </template>

            <button type="submit" x-bind:disabled="$store.dynamicForm.submitting" class="w-full rounded-lg bg-spu-red px-8 py-4 font-bold text-white transition-all hover:bg-spu-red/90 hover:shadow-lg active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60">
                <span x-show="!$store.dynamicForm.submitting" x-text="submitText()"></span>
                <span x-show="$store.dynamicForm.submitting" class="inline-flex items-center gap-2"><svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span x-text="submittingText()"></span></span>
            </button>
        </form>
    </fieldset>
    </div>
    </template>
</div>
