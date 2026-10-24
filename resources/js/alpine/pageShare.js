export function createPageShare() {
    return {
        url: '',
        title: '',
        copied: false,
        copyLabel: '',
        copiedLabel: '',
        defaultCopyLabel: '',

        init() {
            this.url = this.$el?.dataset?.shareUrl || window.location.href;
            this.title = this.$el?.dataset?.shareTitle || document.title;
            const isArabic = document.documentElement.lang === 'ar';
            this.defaultCopyLabel = isArabic ? 'نسخ الرابط' : 'Copy link';
            this.copiedLabel = isArabic ? 'تم النسخ' : 'Copied';
            this.copyLabel = this.defaultCopyLabel;
        },

        async share() {
            try {
                if (navigator.share) {
                    await navigator.share({ title: this.title, url: this.url });
                    return;
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    return;
                }
                // Fall through to copy on other errors (e.g., share not supported)
            }
            await this.copy();
        },

        async copy() {
            let success = false;

            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(this.url);
                    success = true;
                }
            } catch (err) {
                // Ignore clipboard API errors (e.g., insecure context, permission denied)
            }

            if (!success) {
                try {
                    const textarea = document.createElement('textarea');
                    textarea.value = this.url;
                    textarea.style.position = 'fixed';
                    textarea.style.left = '-9999px';
                    textarea.style.top = '-9999px';
                    document.body.appendChild(textarea);
                    textarea.focus();
                    textarea.select();
                    success = document.execCommand('copy');
                    document.body.removeChild(textarea);
                } catch (e) {
                    // Ignore execCommand errors
                }
            }

            if (success) {
                this.copied = true;
                this.copyLabel = this.copiedLabel;
                window.setTimeout(() => {
                    this.copied = false;
                    this.copyLabel = this.defaultCopyLabel;
                }, 2000);
            }
        },
    };
}
