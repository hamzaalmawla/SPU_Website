export function createNewsShare() {
    return {
        shareUrl: '',
        shareTitle: '',
        copyLabel: '',
        defaultLabel: '',
        copiedLabel: '',
        copied: false,
        canShare: false,

        init() {
            this.shareUrl = this.$el?.dataset?.shareUrl || window.location.href;
            this.shareTitle = this.$el?.dataset?.shareTitle || document.title;
            const isArabic = document.documentElement.lang === 'ar';
            this.defaultLabel = isArabic ? 'نسخ الرابط' : 'Copy link';
            this.copiedLabel = isArabic ? 'تم النسخ' : 'Copied';
            this.copyLabel = this.defaultLabel;
            this.canShare = typeof navigator.share === 'function';
        },

        copyWithField() {
            // For browsers without the clipboard API (plain HTTP) or that refuse it.
            const field = document.createElement('textarea');
            field.value = this.shareUrl;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();

            if (!copied) {
                throw new Error('copy failed');
            }
        },

        async copy() {
            try {
                try {
                    await navigator.clipboard.writeText(this.shareUrl);
                } catch {
                    this.copyWithField();
                }
                this.copied = true;
                this.copyLabel = this.copiedLabel;
                window.setTimeout(() => {
                    this.copied = false;
                    this.copyLabel = this.defaultLabel;
                }, 2000);
            } catch {
                // Copying is blocked: show the link so it can be copied by hand.
                this.copyLabel = this.defaultLabel;
                window.prompt(this.defaultLabel, this.shareUrl);
            }
        },

        copyClass() {
            return this.copied ? 'is-copied' : '';
        },

        async share() {
            try {
                await navigator.share({ title: this.shareTitle, url: this.shareUrl });
            } catch {
                // Cancelled by the visitor.
            }
        },
    };
}
