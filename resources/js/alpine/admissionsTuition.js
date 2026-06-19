export function createAdmissionsTuition() {
    return {
        selectedFaculty: '',
        selectedType: '',

        rowVisible(element) {
            const facultyMatch = !this.selectedFaculty || element.dataset.faculty === this.selectedFaculty;
            const typeMatch = !this.selectedType || element.dataset.type === this.selectedType;

            return facultyMatch && typeMatch;
        },

        emptyStateVisible() {
            return !Array.from(this.$el.querySelectorAll('[data-tuition-row]')).some((row) => this.rowVisible(row));
        },
    };
}
