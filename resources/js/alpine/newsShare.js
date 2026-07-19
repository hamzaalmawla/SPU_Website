export function createNewsShare() {
    return {
        shareUrl: '',
        copyLabel: '',
        defaultLabel: '',
        copiedLabel: '',

        init() {
            this.shareUrl = this.$el?.dataset?.shareUrl || window.location.href;
            const isArabic = document.documentElement.lang === 'ar';
            this.defaultLabel = isArabic ? 'نسخ الرابط' : 'Copy link';
            this.copiedLabel = isArabic ? 'تم النسخ' : 'Copied';
            this.copyLabel = this.defaultLabel;
        },

        async copy() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.copyLabel = this.copiedLabel;
                window.setTimeout(() => { this.copyLabel = this.defaultLabel; }, 2000);
            } catch {
                this.copyLabel = this.defaultLabel;
            }
        },
    };
}
