const queryTabMap = {
    'admission-checklist': 'checklist',
    'university-documents': 'granted',
    'study-system': 'studySystem',
    'academic-warnings': 'warnings',
};

const tabQueryMap = Object.fromEntries(
    Object.entries(queryTabMap).map(([query, tab]) => [tab, query]),
);

export function createAdmissionsDocuments() {
    return {
        activeTab: 'checklist',
        activeSubTab: 'freshman',

        init() {
            const requestedTab = new URL(window.location.href).searchParams.get('tab');

            this.activeTab = queryTabMap[requestedTab]
                || this.$el.dataset.activeTab
                || 'checklist';
            this.activeSubTab = this.$el.dataset.activeSubTab || 'freshman';
            this.updateLanguageLinks();
        },

        isTab(tab) {
            return this.activeTab === tab;
        },

        setTab(event) {
            this.activeTab = event.currentTarget.dataset.tab;

            if (this.activeTab === 'checklist') {
                this.activeSubTab = this.$el.querySelector('[data-sub-tab]')?.dataset.subTab || 'freshman';
            }

            this.updateUrl();
            this.updateLanguageLinks();
        },

        tabIndex(tab) {
            return this.isTab(tab) ? 0 : -1;
        },

        moveTab(event) {
            const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];

            if (!keys.includes(event.key)) {
                return;
            }

            event.preventDefault();

            const tabs = Array.from(this.$el.querySelectorAll('[data-documents-tablist] [data-tab]'));
            const currentIndex = tabs.indexOf(event.currentTarget);
            let nextIndex = event.key === 'Home' ? 0 : tabs.length - 1;

            if (event.key === 'ArrowRight') {
                nextIndex = (currentIndex + 1) % tabs.length;
            } else if (event.key === 'ArrowLeft') {
                nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
            }

            tabs[nextIndex]?.focus();
            tabs[nextIndex]?.click();
        },

        updateUrl() {
            const queryTab = tabQueryMap[this.activeTab];

            if (!queryTab) {
                return;
            }

            const url = new URL(window.location.href);
            url.searchParams.set('tab', queryTab);
            window.history.replaceState({}, '', url);
        },

        updateLanguageLinks() {
            const queryTab = tabQueryMap[this.activeTab];

            if (!queryTab) {
                return;
            }

            document.querySelectorAll('[data-language-switch]').forEach((link) => {
                const url = new URL(link.href, window.location.origin);
                url.searchParams.set('tab', queryTab);
                link.href = `${url.pathname}${url.search}${url.hash}`;
            });
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
