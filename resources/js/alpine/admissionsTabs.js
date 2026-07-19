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

        tabIndex(tab) {
            return this.isActive(tab) ? 0 : -1;
        },

        moveTab(event) {
            const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];

            if (!keys.includes(event.key)) return;

            event.preventDefault();
            const tabs = Array.from(event.currentTarget.closest('[role="tablist"]')?.querySelectorAll('[role="tab"]') || []);
            const currentIndex = tabs.indexOf(event.currentTarget);
            const direction = document.documentElement.dir === 'rtl' ? -1 : 1;
            let nextIndex = event.key === 'Home' ? 0 : tabs.length - 1;

            if (event.key === 'ArrowRight') nextIndex = (currentIndex + direction + tabs.length) % tabs.length;
            if (event.key === 'ArrowLeft') nextIndex = (currentIndex - direction + tabs.length) % tabs.length;

            tabs[nextIndex]?.focus();
            tabs[nextIndex]?.click();
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
