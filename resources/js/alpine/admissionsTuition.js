export function createAdmissionsTuition() {
    return {
        selectedFaculty: '',
        selectedType: '',

        rowVisible(element) {
            return !this.selectedFaculty || element.dataset.faculty === this.selectedFaculty;
        },

        // Each row carries both fees; the student-type filter shows only that type's fee column.
        feeColumnVisible(type) {
            return !this.selectedType || this.selectedType === type;
        },

        emptyStateVisible() {
            return !Array.from(this.$root.querySelectorAll('[data-tuition-row]')).some((row) => this.rowVisible(row));
        },
    };
}
