import { getFormSchema } from '../data/dynamicForms.js';

export function registerDynamicFormStore(Alpine) {
    Alpine.store('dynamicForm', {
        activeFormId: null,
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

        get schema() {
            return this.activeFormId ? getFormSchema(this.activeFormId) : null;
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

        open(formId, locale, initialData, context) {
            this.activeFormId = formId;
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
            this.formData = {};
            this.files = {};
            this.errors = {};
            this.submitted = false;
            this.submitting = false;
            this.submitError = '';
            this.currentStep = 0;
            this.completedSteps = [];
            this.context = {};
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
            if (file) {
                this.files[name] = file;
                this.formData[name] = file.name;
            } else {
                delete this.files[name];
                this.formData[name] = '';
            }
            delete this.errors[name];
        },

        validateFields(fields) {
            this.errors = {};
            let valid = true;

            fields.forEach((field) => {
                const value = this.formData[field.name];
                const textValue = (value || '').toString().trim();

                if (field.type === 'checkbox') {
                    if (field.required && !value) {
                        this.errors[field.name] = true;
                        valid = false;
                    }
                    return;
                }

                if (field.required && !textValue) {
                    this.errors[field.name] = true;
                    valid = false;
                }

                if (textValue && field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(textValue)) {
                    this.errors[field.name] = true;
                    valid = false;
                }
            });

            return valid;
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
            this.submitting = true;
            this.submitError = '';
            this.errors = {};

            const body = new FormData();
            Object.keys(this.formData).forEach((key) => {
                if (!Object.prototype.hasOwnProperty.call(this.files, key)) body.append(key, this.formData[key]);
            });
            Object.keys(this.files).forEach((key) => body.append(key, this.files[key]));
            if (this.context.eventSource) body.append('event_source', this.context.eventSource);
            if (this.context.eventId) body.append('event_id', this.context.eventId);

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
                    const payload = await response.json();
                    this.errors = Object.fromEntries(Object.keys(payload.errors || {}).map((key) => [key, true]));
                    this.submitError = this.locale === 'ar' ? 'يرجى مراجعة الحقول المطلوبة.' : 'Please review the required fields.';
                    return;
                }

                if (!response.ok) throw new Error('Submission failed.');

                this.submitted = true;
            } catch (error) {
                this.submitError = this.locale === 'ar' ? 'تعذر إرسال النموذج. حاول مرة أخرى.' : 'Unable to submit the form. Please try again.';
            } finally {
                this.submitting = false;
            }
        },
    });
}
