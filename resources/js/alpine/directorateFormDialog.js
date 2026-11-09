/**
 * A directorate page button that opens one of our forms in a dialog. The form
 * is mounted only while the dialog is open (x-if="open"), so each button gets
 * a fresh form and several forms can share the page's one form store. When the
 * visitor closes the form from its own success message, the dialog closes too.
 */
export function createDirectorateFormDialog() {
    return {
        open: false,
        seen: false,
        formKey: '',

        init() {
            this.formKey = this.$root.dataset.formKey || '';
        },

        show() {
            this.open = true;
            this.$nextTick(() => this.$refs.dialog.showModal());
        },

        hide() {
            if (this.$refs.dialog.open) this.$refs.dialog.close();
        },

        closeOnBackdrop(event) {
            if (event.target === this.$refs.dialog) this.hide();
        },

        onClose() {
            this.open = false;
            this.seen = false;
            this.$store.dynamicForm.close();
        },

        watchForm() {
            if (!this.open) return;

            if (this.$store.dynamicForm.activeFormId === this.formKey) {
                this.seen = true;
            } else if (this.seen) {
                this.seen = false;
                this.hide();
            }
        },
    };
}
