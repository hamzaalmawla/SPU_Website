export function createMobileNav() {
    return {
        openMenu: null,
        stickyNav: false,
        mobileNav: false,

        init() {
            window.addEventListener('scroll', () => {
                this.stickyNav = window.scrollY > 40;
            }, { passive: true });
        },

        closeAll() {
            this.mobileNav = false;
            this.openMenu = null;
        },

        toggleMobile() {
            this.mobileNav = !this.mobileNav;
            if (!this.mobileNav) this.openMenu = null;
        },

        toggleDropdown(id) {
            this.openMenu = this.openMenu === id ? null : id;
        },
    };
}
