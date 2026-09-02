// Must match `--breakpoint-nav` in resources/css/app.css. These two had
// drifted apart (1440 here, 1480 there), so between those widths the script
// treated the bar as desktop while the stylesheet was still showing mobile.
export const NAV_DESKTOP_BREAKPOINT = 1280;

export function createMobileNav() {
    return {
        openMenu: null,
        stickyNav: false,
        mobileNav: false,
        searchOpen: false,
        searchQuery: '',
        searchItems: [],
        searchAllLabel: '',
        scrollHandler: null,
        resizeHandler: null,

        init() {
            try {
                this.searchItems = JSON.parse(this.$el.dataset.searchItems || '[]');
            } catch {
                this.searchItems = [];
            }

            this.searchAllLabel = this.$el.dataset.searchAllLabel || '';

            this.stickyNav = window.scrollY > 16;
            this.scrollHandler = () => {
                this.stickyNav = window.scrollY > 16;
            };
            this.resizeHandler = () => {
                if (window.innerWidth >= NAV_DESKTOP_BREAKPOINT) {
                    this.closeAll();
                }
            };

            window.addEventListener('scroll', this.scrollHandler, { passive: true });
            window.addEventListener('resize', this.resizeHandler, { passive: true });
        },

        destroy() {
            window.removeEventListener('scroll', this.scrollHandler);
            window.removeEventListener('resize', this.resizeHandler);
            this.setPageScrollLocked(false);
        },

        closeAll() {
            this.closeMobile();
            this.openMenu = null;
            this.searchOpen = false;
        },

        closeForOutsideClick() {
            this.openMenu = null;
            this.searchOpen = false;

            if (window.innerWidth < NAV_DESKTOP_BREAKPOINT) {
                this.closeMobile();
            }
        },

        shellClass() {
            return this.stickyNav ? 'site-nav-shell--sticky' : '';
        },

        setPageScrollLocked(locked) {
            globalThis.document?.documentElement?.classList.toggle('site-navigation-open', locked);
        },

        closeMobile() {
            this.mobileNav = false;
            this.setPageScrollLocked(false);
        },

        openDropdown(id) {
            this.openMenu = id;
        },

        openDropdownForFocus(id) {
            this.openMenu = id;
        },

        closeDropdownForFocus(event) {
            if (!event.currentTarget.contains(event.relatedTarget)) {
                this.openMenu = null;
            }
        },

        closeDropdown(event) {
            if (!event?.currentTarget?.contains(document.activeElement)) {
                this.openMenu = null;
            }
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

        // Label for the "see every result" submit button. The localized string
        // arrives from the server with a placeholder so no copy lives in JS.
        allSearchResultsLabel() {
            return this.searchAllLabel.replace('__QUERY__', this.searchQuery.trim());
        },

        toggleMobile() {
            this.mobileNav = !this.mobileNav;
            this.searchOpen = false;
            this.openMenu = null;
            this.setPageScrollLocked(this.mobileNav);

            if (this.mobileNav) {
                this.$nextTick(() => this.$el.querySelector('#site-mobile-navigation a[href], #site-mobile-navigation button')?.focus());
            }
        },

        toggleDropdown(id) {
            this.openMenu = this.openMenu === id ? null : id;
        },

        handleEscape() {
            if (this.searchOpen) {
                this.searchOpen = false;
                this.searchQuery = '';
                this.$nextTick(() => this.$refs.searchToggle?.focus());
                return;
            }

            if (this.openMenu !== null) {
                const id = this.openMenu;
                this.openMenu = null;
                const scope = this.mobileNav ? '#site-mobile-navigation ' : 'nav ';
                this.$nextTick(() => this.$el.querySelector(`${scope}[data-dropdown-trigger="${id}"]`)?.focus());
                return;
            }

            if (this.mobileNav) {
                this.closeMobile();
                this.$nextTick(() => this.$refs.mobileToggle?.focus());
            }
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
