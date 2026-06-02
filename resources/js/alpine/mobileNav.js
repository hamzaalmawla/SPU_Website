export function createMobileNav() {
    return {
        openMenu: null,
        stickyNav: false,
        mobileNav: false,
        searchOpen: false,
        searchQuery: '',
        searchItems: [],

        init() {
            try {
                this.searchItems = JSON.parse(this.$el.dataset.searchItems || '[]');
            } catch {
                this.searchItems = [];
            }

            window.addEventListener('scroll', () => {
                this.stickyNav = window.scrollY > 40;
            }, { passive: true });
        },

        closeAll() {
            this.mobileNav = false;
            this.openMenu = null;
            this.searchOpen = false;
        },

        toggleMobile() {
            this.mobileNav = !this.mobileNav;
            if (!this.mobileNav) this.openMenu = null;
        },

        toggleDropdown(id) {
            this.openMenu = this.openMenu === id ? null : id;
        },

        toggleSearch() {
            this.searchOpen = !this.searchOpen;
            if (this.searchOpen) {
                this.$nextTick(() => this.$refs.siteSearch?.focus());
            }
        },

        openSearch() {
            this.searchOpen = true;
            this.$nextTick(() => this.$refs.siteSearch?.focus());
        },

        get searchResults() {
            const query = this.searchQuery.trim().toLowerCase();

            if (query.length < 2) {
                return [];
            }

            return this.searchItems
                .filter((item) => (item.label || '').toLowerCase().includes(query))
                .slice(0, 6);
        },
    };
}
