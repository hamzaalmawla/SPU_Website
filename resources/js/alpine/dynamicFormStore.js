export function registerDynamicFormStore(Alpine) {
    Alpine.store('dynamicForm', {
        activeFormId: null,
        // The form as built under Forms in the admin, sent with the page.
        activeSchema: null,
        locale: document.documentElement.lang || 'ar',
        formData: {},
        files: {},
        errors: {},
        submitted: false,
        submitting: false,
        submitError: '',
        currentStep: 0,
        completedSteps: [],
        context: {},
        isPreview: false,

        get schema() {
            return this.activeFormId ? this.activeSchema : null;
        },

        get isMultiStep() {
            return this.schema?.multiStep === true && Array.isArray(this.schema?.steps);
        },

        get totalSteps() {
            return this.isMultiStep ? this.schema.steps.length : 0;
        },

        get currentStepSchema() {
            return this.isMultiStep ? this.schema.steps[this.currentStep] || null : null;
        },

        get currentStepFields() {
            return this.currentStepSchema?.fields || [];
        },

        open(formId, locale, initialData, context, schema) {
            this.activeFormId = formId;
            this.activeSchema = schema && typeof schema === 'object' ? schema : null;
            this.locale = locale || document.documentElement.lang || 'ar';
            this.formData = {};
            this.files = {};
            this.errors = {};
            this.submitted = false;
            this.submitting = false;
            this.submitError = '';
            this.currentStep = 0;
            this.completedSteps = [];
            this.context = context || {};
            this.isPreview = context?.preview === true;

            const form = this.schema;
            if (!form) return;

            this.fields().forEach((field) => {
                this.formData[field.name] = this.defaultForField(field);
            });

            if (initialData) {
                Object.keys(initialData).forEach((key) => {
                    if (Object.prototype.hasOwnProperty.call(this.formData, key)) {
                        this.formData[key] = initialData[key];
                    }
                });
            }
        },

        close() {
            this.activeFormId = null;
            this.activeSchema = null;
            this.formData = {};
            this.files = {};
            this.errors = {};
            this.submitted = false;
            this.submitting = false;
            this.submitError = '';
            this.currentStep = 0;
            this.completedSteps = [];
            this.context = {};
            this.isPreview = false;
        },

        fields() {
            if (!this.schema) return [];
            if (this.isMultiStep) return this.schema.steps.flatMap((step) => step.fields || []);
            return this.schema.fields || [];
        },

        defaultForField(field) {
            if (field.type === 'checkbox') return false;
            if (field.type === 'select' && field.options?.length) return field.options[0].value || '';
            return '';
        },

        setFieldValue(name, value) {
            this.formData[name] = value;
            delete this.errors[name];
        },

        setFile(name, file) {
            delete this.errors[name];
            const problem = file ? this.fileProblem(this.fields().find((field) => field.name === name), file) : '';

            if (file && !problem) {
                this.files[name] = file;
                this.formData[name] = file.name;
            } else {
                delete this.files[name];
                this.formData[name] = '';
            }

            if (problem) this.errors[name] = problem;
        },

        // The server's rules for the file, checked on selection so a wrong
        // type or size is explained at once rather than after submitting.
        fileProblem(field, file) {
            if (!field) return '';
            const extension = (file.name || '').split('.').pop().toLowerCase();
            const allowed = (field.accept || '').split(',').map((type) => type.trim().replace(/^\./, '').toLowerCase()).filter(Boolean);

            if (allowed.length && !allowed.includes(extension)) return this.message('fileType', { types: allowed.join(', ').toUpperCase() });
            if (field.maxKilobytes && file.size > field.maxKilobytes * 1024) return this.message('fileSize', { size: Math.round(field.maxKilobytes / 1024) });

            return '';
        },

        message(key, values = {}) {
            const ar = this.locale === 'ar';
            const text = {
                required: ar ? 'هذا الحقل مطلوب.' : 'This field is required.',
                email: ar ? 'أدخل بريداً إلكترونياً صالحاً.' : 'Enter a valid email address.',
                fileType: ar ? 'نوع الملف غير مقبول. الأنواع المسموحة: :types.' : 'This file type is not accepted. Allowed: :types.',
                fileSize: ar ? 'حجم الملف أكبر من :size ميغابايت.' : 'The file is larger than :size MB.',
                review: ar ? 'يرجى تصحيح الحقول المشار إليها.' : 'Please correct the highlighted fields.',
                failed: ar ? 'تعذر إرسال النموذج. حاول مرة أخرى.' : 'Unable to submit the form. Please try again.',
            }[key] || '';

            return Object.keys(values).reduce((result, name) => result.replace(`:${name}`, values[name]), text);
        },

        validateFields(fields) {
            this.errors = {};
            let valid = true;

            fields.forEach((field) => {
                const value = this.formData[field.name];
                const textValue = (value || '').toString().trim();

                if (field.type === 'checkbox') {
                    if (field.required && !value) {
                        this.errors[field.name] = this.message('required');
                        valid = false;
                    }
                    return;
                }

                if (field.required && !textValue) {
                    this.errors[field.name] = this.message('required');
                    valid = false;
                }

                if (textValue && field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(textValue)) {
                    this.errors[field.name] = this.message('email');
                    valid = false;
                }
            });

            if (!valid) this.focusFirstError();

            return valid;
        },

        fieldId(name) {
            const formId = (this.activeFormId || 'form').toString().replace(/[^a-zA-Z0-9_-]+/g, '-');
            const fieldName = (name || 'field').toString().replace(/[^a-zA-Z0-9_-]+/g, '-');
            return `dynamic-${formId}-${fieldName}`;
        },

        focusFirstError() {
            const firstName = Object.keys(this.errors)[0];
            if (!firstName) return;

            window.setTimeout(() => document.getElementById(this.fieldId(firstName))?.focus(), 0);
        },

        validateStep(stepIndex) {
            if (!this.isMultiStep) return this.validate();
            const step = this.schema.steps[stepIndex];
            return step ? this.validateFields(step.fields || []) : true;
        },

        validate() {
            return this.validateFields(this.fields());
        },

        nextStep() {
            if (!this.validateStep(this.currentStep)) return;
            if (!this.completedSteps.includes(this.currentStep)) this.completedSteps.push(this.currentStep);
            if (this.currentStep < this.totalSteps - 1) this.currentStep++;
        },

        prevStep() {
            if (this.currentStep > 0) this.currentStep--;
        },

        goToStep(stepIndex) {
            if (stepIndex < 0 || stepIndex >= this.totalSteps) return;
            if (stepIndex <= this.currentStep || this.completedSteps.includes(stepIndex - 1)) this.currentStep = stepIndex;
        },

        async handleSubmit() {
            if (this.isPreview) return;

            if (this.isMultiStep) {
                if (!this.validateStep(this.currentStep)) return;
                if (!this.completedSteps.includes(this.currentStep)) this.completedSteps.push(this.currentStep);
                if (this.currentStep < this.totalSteps - 1) {
                    this.currentStep++;
                    return;
                }
            } else if (!this.validate()) {
                return;
            }

            await this.submit();
        },

        async submit() {
            if (this.isPreview) return;

            this.submitting = true;
            this.submitError = '';
            this.errors = {};

            const body = new FormData();
            Object.keys(this.formData).forEach((key) => {
                if (!Object.prototype.hasOwnProperty.call(this.files, key)) body.append(key, this.formData[key]);
            });
            Object.keys(this.files).forEach((key) => body.append(key, this.files[key]));
            body.append('website', document.querySelector('[data-form-honeypot]')?.value || '');
            if (this.context.eventSource) body.append('event_source', this.context.eventSource);
            if (this.context.eventId) body.append('event_id', this.context.eventId);
            if (this.context.jobId) body.append('job_id', this.context.jobId);
            if (this.context.jobSlug) body.append('job_slug', this.context.jobSlug);

            try {
                const response = await fetch(`/${this.locale}/forms/${this.activeFormId}/submissions`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body,
                });

                if (response.status === 422) {
                    this.showServerErrors((await response.json()).errors || {});
                    return;
                }

                if (!response.ok) throw new Error('Submission failed.');

                this.submitted = true;
            } catch (error) {
                this.submitError = this.message('failed');
            } finally {
                this.submitting = false;
            }
        },

        // The server's own messages: under their field, or at the top when
        // they are not about a field (an event that closed or filled up, a
        // job no longer open). A fixed "required fields" text hid them all.
        showServerErrors(serverErrors) {
            const fieldNames = this.fields().map((field) => field.name);
            const general = [];

            this.errors = {};
            Object.keys(serverErrors).forEach((key) => {
                const text = [].concat(serverErrors[key])[0] || this.message('review');

                if (fieldNames.includes(key)) {
                    this.errors[key] = text;
                } else {
                    general.push(text);
                }
            });

            this.submitError = general.length ? general.join(' ') : this.message('review');

            if (this.isMultiStep) {
                const step = this.schema.steps.findIndex((item) => (item.fields || []).some((field) => this.errors[field.name]));
                if (step >= 0) this.currentStep = step;
            }

            this.focusFirstError();
        },
    });
}
