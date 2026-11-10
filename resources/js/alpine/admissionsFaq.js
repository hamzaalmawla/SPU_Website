export function createAdmissionsFaq() {
    return {
        search: '',
        openKey: null,

        init() {
            const first = this.$el.querySelector('[data-faq-item]');
            if (first) {
                this.openKey = this.key(first.dataset.category, first.dataset.index);
            }
        },

        key(category, index) {
            return `${category}:${index}`;
        },

        normalizedSearch() {
            return this.search.trim().toLowerCase();
        },

        itemVisible(element) {
            const query = this.normalizedSearch();
            if (query === '') return true;

            return (element.dataset.search || '').toLowerCase().includes(query);
        },

        categoryVisible(element) {
            return Array.from(element.querySelectorAll('[data-faq-item]')).some((item) => this.itemVisible(item));
        },

        isOpen(element) {
            return this.openKey === this.key(element.dataset.category, element.dataset.index);
        },

        accordionItemClass(element) {
            return this.isOpen(element) ? 'is-open' : '';
        },

        toggleAccordion(event) {
            const button = event.currentTarget;
            const nextKey = this.key(button.dataset.category, button.dataset.index);
            this.openKey = this.openKey === nextKey ? null : nextKey;
        },

        emptyStateVisible() {
            return !Array.from(this.$el.querySelectorAll('[data-faq-item]')).some((item) => this.itemVisible(item));
        },
    };
}
