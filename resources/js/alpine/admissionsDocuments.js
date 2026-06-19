export function createAdmissionsDocuments() {
    return {
        activeTab: 'checklist',
        activeSubTab: 'freshman',

        init() {
            this.activeTab = this.$el.dataset.activeTab || 'checklist';
            this.activeSubTab = this.$el.dataset.activeSubTab || 'freshman';
        },

        isTab(tab) {
            return this.activeTab === tab;
        },

        setTab(event) {
            this.activeTab = event.currentTarget.dataset.tab;

            if (this.activeTab === 'checklist') {
                this.activeSubTab = this.$el.querySelector('[data-sub-tab]')?.dataset.subTab || 'freshman';
            }
        },

        tabButtonClass(element) {
            return this.isTab(element.dataset.tab)
                ? 'border-b-2 border-spu-red text-spu-red'
                : 'border-b-2 border-transparent';
        },

        isSubTab(tab) {
            return this.activeSubTab === tab;
        },

        setSubTab(event) {
            this.activeSubTab = event.currentTarget.dataset.subTab;
        },

        subTabButtonClass(element) {
            return this.isSubTab(element.dataset.subTab)
                ? 'bg-spu-blue text-white'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200';
        },
    };
}
