<div x-bind:class="fieldWrapperClass(field)">
    <template x-if="['text', 'email', 'tel', 'number', 'date'].includes(field.type)">
        <div>
            <label x-bind:for="fieldId(field)" class="text-xs font-bold uppercase tracking-wide text-gray-700"><span x-text="label(field)"></span><span x-show="field.required" class="ms-0.5 text-spu-red" aria-hidden="true">*</span></label>
            <input x-bind:id="fieldId(field)" x-bind:name="field.name" x-bind:type="field.type" x-bind:required="field.required" x-bind:aria-required="field.required ? 'true' : null" x-bind:aria-invalid="invalid(field)" x-bind:aria-describedby="describedBy(field)" x-bind:value="$store.dynamicForm.formData[field.name]" x-on:input="setValue(field, $event.target.value)" x-bind:placeholder="placeholder(field)" x-bind:class="inputClass(field)" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-spu-red focus:ring-2 focus:ring-spu-red/10">
            <p x-show="$store.dynamicForm.errors[field.name]" x-bind:id="errorId(field)" class="mt-1 text-xs text-red-500" role="alert" x-text="errorText()"></p>
        </div>
    </template>

    <template x-if="field.type === 'file'">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-gray-700"><span x-text="label(field)"></span><span x-show="field.required" class="ms-0.5 text-spu-red" aria-hidden="true">*</span></p>
            <div class="mt-1 flex w-full items-center justify-center">
                <label x-bind:for="fieldId(field)" class="flex h-32 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed transition-colors" x-bind:class="fileBoxClass(field)">
                    <div class="flex flex-col items-center justify-center pb-6 pt-5">
                        <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                        <p class="mb-1 text-xs text-gray-500"><span x-text="uploadText()"></span></p>
                        <p class="text-[10px] text-gray-400" x-text="allowedText(field)"></p>
                    </div>
                    <input x-bind:id="fieldId(field)" x-bind:name="field.name" type="file" class="sr-only" x-bind:required="field.required" x-bind:aria-required="field.required ? 'true' : null" x-bind:aria-invalid="invalid(field)" x-bind:aria-describedby="describedBy(field)" x-bind:accept="field.accept || '.pdf,.doc,.docx'" x-on:change="setFile(field, $event.target.files[0])">
                </label>
            </div>
            <p x-show="$store.dynamicForm.formData[field.name]" class="mt-2 flex items-center gap-1.5 text-xs text-green-600"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span x-text="$store.dynamicForm.formData[field.name]"></span></p>
            <p x-show="$store.dynamicForm.errors[field.name]" x-bind:id="errorId(field)" class="mt-1 text-xs text-red-500" role="alert" x-text="errorText()"></p>
        </div>
    </template>

    <template x-if="field.type === 'select'">
        <div>
            <label x-bind:for="fieldId(field)" class="text-xs font-bold uppercase tracking-wide text-gray-700"><span x-text="label(field)"></span><span x-show="field.required" class="ms-0.5 text-spu-red" aria-hidden="true">*</span></label>
            <select x-bind:id="fieldId(field)" x-bind:name="field.name" x-bind:required="field.required" x-bind:aria-required="field.required ? 'true' : null" x-bind:aria-invalid="invalid(field)" x-bind:aria-describedby="describedBy(field)" x-on:change="setValue(field, $event.target.value)" x-bind:class="inputClass(field)" class="w-full rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-spu-red focus:ring-2 focus:ring-spu-red/10">
                <template x-for="opt in field.options" x-bind:key="opt.value">
                    <option x-bind:value="opt.value" x-bind:selected="$store.dynamicForm.formData[field.name] === opt.value" x-text="optionLabel(opt)"></option>
                </template>
            </select>
            <p x-show="$store.dynamicForm.errors[field.name]" x-bind:id="errorId(field)" class="mt-1 text-xs text-red-500" role="alert" x-text="errorText()"></p>
        </div>
    </template>

    <template x-if="field.type === 'textarea'">
        <div>
            <label x-bind:for="fieldId(field)" class="text-xs font-bold uppercase tracking-wide text-gray-700"><span x-text="label(field)"></span><span x-show="field.required" class="ms-0.5 text-spu-red" aria-hidden="true">*</span></label>
            <textarea x-bind:id="fieldId(field)" x-bind:name="field.name" x-bind:required="field.required" x-bind:aria-required="field.required ? 'true' : null" x-bind:aria-invalid="invalid(field)" x-bind:aria-describedby="describedBy(field)" x-bind:rows="field.rows || 4" x-bind:value="$store.dynamicForm.formData[field.name]" x-on:input="setValue(field, $event.target.value)" x-bind:placeholder="placeholder(field)" x-bind:class="inputClass(field)" class="w-full resize-none rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm outline-none transition-all focus:border-spu-red focus:ring-2 focus:ring-spu-red/10"></textarea>
            <p x-show="$store.dynamicForm.errors[field.name]" x-bind:id="errorId(field)" class="mt-1 text-xs text-red-500" role="alert" x-text="errorText()"></p>
        </div>
    </template>

    <template x-if="field.type === 'checkbox'">
        <div>
            <div class="flex cursor-pointer items-start gap-3"><input x-bind:id="fieldId(field)" x-bind:name="field.name" type="checkbox" x-bind:required="field.required" x-bind:aria-required="field.required ? 'true' : null" x-bind:aria-invalid="invalid(field)" x-bind:aria-describedby="describedBy(field)" x-bind:checked="$store.dynamicForm.formData[field.name]" x-on:change="setValue(field, $event.target.checked)" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-spu-red focus:ring-spu-red/20"><label x-bind:for="fieldId(field)" class="text-sm text-gray-700"><span x-text="label(field)"></span><span x-show="field.required" class="ms-0.5 text-spu-red" aria-hidden="true">*</span></label></div>
            <p x-show="$store.dynamicForm.errors[field.name]" x-bind:id="errorId(field)" class="mt-1 text-xs text-red-500" role="alert" x-text="errorText()"></p>
        </div>
    </template>
</div>
