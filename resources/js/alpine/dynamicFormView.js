export function createDynamicFormShell() {
    return {
        init() {
            this.$store.dynamicForm.open(this.$el.dataset.formId, this.$el.dataset.locale, null, {
                eventSource: this.$el.dataset.eventSource || '',
                eventId: this.$el.dataset.eventId || '',
            });
        },
    };
}

export function createDynamicFormView() {
    return {
        get isAr() { return document.documentElement.lang === 'ar'; },
        get store() { return this.$store.dynamicForm; },
        get currentStepLabel() { return this.isAr ? 'الخطوة ' : 'Step '; },
        label(field) { return this.isAr ? field.labelAr : field.labelEn; },
        placeholder(field) { return this.isAr ? field.placeholderAr || '' : field.placeholderEn || ''; },
        optionLabel(option) { return this.isAr ? option.labelAr : option.labelEn; },
        stepTitle(step) { return this.isAr ? step.titleAr : step.titleEn; },
        stepButtonClass(idx) {
            if (idx === this.store.currentStep) return 'border-spu-red bg-spu-red text-white shadow-lg shadow-spu-red/30';
            return this.store.completedSteps.includes(idx) ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 bg-white text-gray-400';
        },
        stepTextClass(idx) {
            if (idx === this.store.currentStep) return 'text-spu-red';
            return this.store.completedSteps.includes(idx) ? 'text-green-600' : 'text-gray-400';
        },
        connectorClass(idx) { return this.store.completedSteps.includes(idx) ? 'bg-green-500' : 'bg-gray-200'; },
        fieldWrapperClass(field) { return field.type === 'textarea' || field.type === 'checkbox' ? 'sm:col-span-2' : ''; },
        inputClass(field) { return this.store.errors[field.name] ? 'border-red-400 focus:border-red-500 focus:ring-red-100' : ''; },
        fileBoxClass(field) { return this.store.errors[field.name] ? 'border-red-400 bg-red-50' : 'border-gray-300 bg-gray-50 hover:border-spu-red hover:bg-spu-red/5'; },
        errorText() { return this.isAr ? 'هذا الحقل مطلوب' : 'This field is required'; },
        successTitle() { return this.isAr ? 'تم الإرسال بنجاح!' : 'Submitted Successfully!'; },
        successText() { return this.isAr ? 'شكراً لك. تم استلام طلبك بنجاح.' : 'Thank you. Your application has been received successfully.'; },
        closeText() { return this.isAr ? 'إغلاق' : 'Close'; },
        previousText() { return this.isAr ? 'السابق' : 'Previous'; },
        nextText() { return this.isAr ? 'التالي' : 'Next'; },
        submitText() { return this.isAr ? this.store.schema?.submitLabelAr || 'إرسال' : this.store.schema?.submitLabelEn || 'Submit'; },
        submittingText() { return this.isAr ? 'جاري الإرسال...' : 'Submitting...'; },
        uploadText() { return this.isAr ? 'اضغط للرفع' : 'Click to upload'; },
        allowedText(field) { return field.accept ? 'Allowed: ' + field.accept : 'PDF, DOC, DOCX'; },
        setValue(field, value) { this.store.setFieldValue(field.name, value); },
        setFile(field, file) { this.store.setFile(field.name, file); },
    };
}
