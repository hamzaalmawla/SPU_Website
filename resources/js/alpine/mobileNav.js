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

        closeForOutsideClick() {
            this.openMenu = null;
            this.searchOpen = false;

            if (window.innerWidth < 1536) {
                this.mobileNav = false;
            }
        },

        headerClass() {
            return this.stickyNav ? 'fixed inset-x-0 top-0 z-50 w-full pt-3' : '';
        },

        shellClass() {
            return this.stickyNav ? 'site-nav-shell--sticky' : '';
        },

        openDropdown(id) {
            this.openMenu = id;
        },

        closeDropdown() {
            this.openMenu = null;
        },

        isDropdownOpen(id) {
            return this.openMenu === id;
        },

        mobileToggleIcon() {
            return this.mobileNav ? '/images/icon-close-outline.svg' : '/images/icon-bars-outline.svg';
        },

        mobileChevronClass(id) {
            return this.isDropdownOpen(id) ? 'rotate-180' : '';
        },

        closeSearchResult() {
            this.searchOpen = false;
            this.searchQuery = '';
        },

        searchResultKey(item) {
            return `${item.url}${item.label}`;
        },

        needsLongerSearchQuery() {
            return this.searchQuery.length > 0 && this.searchQuery.length < 2;
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
