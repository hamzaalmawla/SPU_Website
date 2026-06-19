export function createAdmissionsTabs() {
    return {
        activeTab: null,

        init() {
            this.activeTab = this.$el.dataset.activeTab || this.$el.querySelector('[data-tab]')?.dataset.tab || null;
        },

        isActive(tab) {
            return this.activeTab === tab;
        },

        setActiveTab(event) {
            this.activeTab = event.currentTarget.dataset.tab;
        },

        pillButtonClass(element) {
            return this.isActive(element.dataset.tab)
                ? 'bg-spu-red text-white'
                : 'text-slate-600 hover:text-spu-red';
        },

        underlineButtonClass(element) {
            return this.isActive(element.dataset.tab)
                ? 'border-b-2 border-spu-red text-spu-red'
                : 'border-b-2 border-transparent';
        },
    };
}
