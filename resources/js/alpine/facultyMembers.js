// Faculty members page: search, a group (leadership, heads, council, staff) and a
// department narrow the cards the server already rendered in rank order.
const GROUPS = ['leadership', 'head', 'council', 'staff'];

function normalize(value) {
    return String(value || '')
        .toLowerCase()
        .normalize('NFKD')
        .replace(/[̀-ًͯ-ٰٟـ]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/\s+/g, ' ')
        .trim();
}

export function createFacultyMembers() {
    return {
        query: '',
        group: '',
        department: '',
        cards: [],
        version: 0,

        init() {
            this.cards = Array.from(this.$el.querySelectorAll('[data-member-card]')).map((card) => ({
                group: card.dataset.group || 'staff',
                department: card.dataset.department || '',
                text: normalize(card.dataset.search),
            }));

            const params = new URL(window.location.href).searchParams;
            this.query = params.get('q') || '';
            this.group = GROUPS.includes(params.get('group')) ? params.get('group') : '';
            const department = params.get('department') || '';
            this.department = this.cards.some((card) => card.department === department) ? department : '';

            this.$watch('query', () => this.sync());
            this.$watch('group', () => this.sync());
            this.$watch('department', () => this.sync());
        },

        matches(card, ignoreGroup = false) {
            if (!ignoreGroup && this.group !== '' && card.group !== this.group) {
                return false;
            }

            if (this.department !== '' && card.department !== this.department) {
                return false;
            }

            const terms = normalize(this.query).split(' ').filter(Boolean);

            return terms.every((term) => card.text.includes(term));
        },

        cardVisible(el) {
            const index = Number.parseInt(el.dataset.index || '-1', 10);
            const card = this.cards[index];

            return card ? this.matches(card) : true;
        },

        groupVisible(el) {
            const group = el.dataset.groupSection;

            return this.cards.some((card) => card.group === group && this.matches(card));
        },

        // Classes, not x-show: nested x-show (a group and its cards) can drop a card's hide.
        cardClass(el) {
            return this.cardVisible(el) ? '' : 'fm-hidden';
        },

        groupClass(el) {
            return this.groupVisible(el) ? '' : 'fm-hidden';
        },

        visibleCount() {
            return this.cards.filter((card) => this.matches(card)).length;
        },

        groupCount(el) {
            const group = el.dataset.group || '';

            return this.cards.filter((card) => (group === '' || card.group === group) && this.matches(card, true)).length;
        },

        noResults() {
            return this.cards.length > 0 && this.visibleCount() === 0;
        },

        hasFilters() {
            return this.query !== '' || this.group !== '' || this.department !== '';
        },

        statusText() {
            const total = this.cards.length;
            const shown = this.visibleCount();
            const isArabic = document.documentElement.lang === 'ar';

            if (shown === total) {
                return isArabic ? `${total} عضو هيئة تدريسية` : `${total} faculty members`;
            }

            return isArabic ? `${shown} من ${total} عضو هيئة تدريسية` : `Showing ${shown} of ${total} faculty members`;
        },

        selectGroup(event) {
            this.group = event.currentTarget.dataset.group || '';
        },

        chipClass(el) {
            return (el.dataset.group || '') === this.group ? 'is-active' : '';
        },

        chipPressed(el) {
            return (el.dataset.group || '') === this.group ? 'true' : 'false';
        },

        reset() {
            this.query = '';
            this.group = '';
            this.department = '';
        },

        sync() {
            const url = new URL(window.location.href);
            const values = { q: this.query.trim(), group: this.group, department: this.department };

            Object.entries(values).forEach(([key, value]) => {
                if (value === '') {
                    url.searchParams.delete(key);
                } else {
                    url.searchParams.set(key, value);
                }
            });

            window.history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);
        },
    };
}
