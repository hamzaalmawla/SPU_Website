import dayjs from 'dayjs';
import { horizontalKeyAction, observeReducedMotion } from '../utils/motionDirection.js';

const CALENDAR_GRID_DAYS = 35;

function getCurrentLocale() {
    return document.documentElement.lang || 'ar';
}

const MONTHS = {
    en: ['January','February','March','April','May','June','July','August','September','October','November','December'],
    ar: ['كانون الثاني','شباط','آذار','نيسان','أيار','حزيران','تموز','آب','أيلول','تشرين الأول','تشرين الثاني','كانون الأول'],
};

function normalizeEvent(event, index) {
    const parsed = dayjs(event.startsAt ?? event.date);
    if (!parsed.isValid()) return null;

    return {
        id: event.id ?? `event-${index + 1}`,
        type: event.timeLabel ?? event.type ?? 'Event',
        title: event.title ?? 'Untitled Event',
        description: event.summary ?? event.description ?? '',
        image: event.image ?? event.imageUrl ?? '/images/slider-1.webp',
        link: event.url ?? event.link ?? '#',
        dateKey: parsed.format('YYYY-MM-DD'),
        dateText: parsed.format('MMM D, YYYY'),
    };
}

export function createCalendarApp() {
    return {
        rawEvents: [],
        viewDate: dayjs().startOf('month'),
        selectedDate: dayjs().format('YYYY-MM-DD'),
        activeEventIndex: 0,
        carouselInterval: null,
        reducedMotion: false,
        _removeMotionObserver: null,

        init() {
            let incoming = [];

            try {
                const parsed = JSON.parse(this.$el.dataset.events || '[]');
                incoming = Array.isArray(parsed) ? parsed : [];
            } catch {
                incoming = [];
            }

            this.setEvents(incoming);
            this._removeMotionObserver = observeReducedMotion((reduced) => {
                this.reducedMotion = reduced;
                reduced ? this.stopCarousel() : this.startCarousel();
            });
        },

        destroy() {
            this.stopCarousel();
            this._removeMotionObserver?.();
        },

        setEvents(events = []) {
            this.rawEvents = events
                .map((e, i) => normalizeEvent(e, i))
                .filter(Boolean)
                .sort((a, b) => a.dateKey.localeCompare(b.dateKey));

            const today = dayjs().format('YYYY-MM-DD');
            const initialDate = this.eventsByDate[today]?.length > 0
                ? today
                : this.rawEvents.find((event) => event.dateKey >= today)?.dateKey
                    ?? this.rawEvents[0]?.dateKey
                    ?? today;
            this.selectedDate = initialDate;
            this.viewDate = dayjs(initialDate).startOf('month');
            this.activeEventIndex = 0;
        },

        get eventsByDate() {
            return this.rawEvents.reduce((acc, event) => {
                if (!acc[event.dateKey]) acc[event.dateKey] = [];
                acc[event.dateKey].push(event);
                return acc;
            }, {});
        },

        get selectedDateEvents() {
            return this.eventsByDate[this.selectedDate] || [];
        },

        get selectedEvent() {
            return this.selectedDateEvents[this.activeEventIndex] || null;
        },

        get selectedYear() {
            return this.viewDate.format('YYYY');
        },

        get selectedDateLabel() {
            const date = dayjs(this.selectedDate);
            const lang = getCurrentLocale();
            if (lang === 'ar') {
                return `${MONTHS.ar[date.month()]} ${date.date()}, ${date.year()}`;
            }
            return date.format('MMM D, YYYY');
        },

        get monthLabel() {
            const lang = getCurrentLocale();
            return (MONTHS[lang] || MONTHS.en)[this.viewDate.month()];
        },

        get noEventsLabel() {
            return getCurrentLocale() === 'ar' ? 'لا توجد فعاليات في هذا التاريخ.' : 'No events on this date.';
        },

        get calendarDays() {
            const grouped = this.eventsByDate;
            const gridStart = this.viewDate.startOf('month').startOf('week');
            const today = dayjs().format('YYYY-MM-DD');

            return Array.from({ length: CALENDAR_GRID_DAYS }, (_, i) => {
                const day = gridStart.add(i, 'day');
                const dateKey = day.format('YYYY-MM-DD');
                const isCurrentMonth = day.isSame(this.viewDate, 'month');

                return {
                    date: dateKey,
                    dayNumber: String(day.date()),
                    isCurrentMonth,
                    isToday: dateKey === today,
                    hasEvent: isCurrentMonth && (grouped[dateKey] || []).length > 0,
                };
            });
        },

        selectDate(date) {
            this.selectedDate = date;
            this.activeEventIndex = 0;
            if (!dayjs(date).isSame(this.viewDate, 'month')) {
                this.viewDate = dayjs(date).startOf('month');
            }
            this.startCarousel();
        },

        selectEvent(index) {
            this.activeEventIndex = index;
            this.startCarousel();
        },

        changeMonth(step) {
            const next = this.viewDate.add(step, 'month').startOf('month');
            const first = this.rawEvents.find((e) => dayjs(e.dateKey).isSame(next, 'month'));
            this.viewDate = next;
            this.selectedDate = first?.dateKey ?? next.format('YYYY-MM-DD');
            this.activeEventIndex = 0;
        },

        prevMonth() { this.changeMonth(-1); },
        nextMonth() { this.changeMonth(1); },

        selectedEventImage() {
            return this.selectedEvent?.image || '';
        },

        selectedEventAlt() {
            return this.selectedEvent?.title || '';
        },

        selectedEventLink() {
            return this.selectedEvent?.link || '#';
        },

        eventKey(eventItem, index) {
            return eventItem.id || index;
        },

        eventDotClass(index) {
            return this.activeEventIndex === index ? 'w-[24px] bg-[#27316d]' : 'w-[8px] bg-[#d1d5de]';
        },

        isEventActive(index) {
            return this.activeEventIndex === index;
        },

        eventDotLabel(index) {
            const label = this.$el.dataset.eventLabel || 'View event';
            return `${label} ${index + 1} / ${this.selectedDateEvents.length}`;
        },

        dayLabel(day) {
            return new Intl.DateTimeFormat(getCurrentLocale(), { dateStyle: 'long' })
                .format(dayjs(day.date).toDate());
        },

        isDateSelected(day) {
            return this.selectedDate === day.date;
        },

        dayButtonClass(day) {
            const selectedClass = this.selectedDate === day.date ? 'bg-[#27316d]' : 'bg-transparent hover:bg-[#f5f7fc]';
            const todayClass = day.isToday && this.selectedDate !== day.date ? 'border-2 border-[#27316d]/30' : '';

            return [selectedClass, todayClass].filter(Boolean).join(' ');
        },

        dayNumberClass(day) {
            let colorClass = 'text-[#d0d0d0]';

            if (this.selectedDate === day.date) {
                colorClass = 'text-white';
            } else if (day.isCurrentMonth) {
                colorClass = 'text-[#111111]';
            }

            const todayClass = day.isToday && this.selectedDate !== day.date ? 'text-[#c63030]' : '';

            return [colorClass, todayClass].filter(Boolean).join(' ');
        },

        showEventMarker(day) {
            return day.hasEvent && this.selectedDate !== day.date;
        },

        startCarousel() {
            this.stopCarousel();

            if (this.reducedMotion || this.selectedDateEvents.length <= 1 || this.hasActiveInteraction()) return;

            this.carouselInterval = setInterval(() => {
                this.activeEventIndex = (this.activeEventIndex + 1) % this.selectedDateEvents.length;
            }, 5000);
        },

        stopCarousel() {
            if (this.carouselInterval) {
                clearInterval(this.carouselInterval);
                this.carouselInterval = null;
            }
        },

        hasActiveInteraction() {
            const hovered = typeof this.$el.matches === 'function' && this.$el.matches(':hover');
            const focused = typeof document !== 'undefined' && this.$el.contains(document.activeElement);

            return hovered || focused;
        },

        resumeCarousel(event) {
            if (event?.relatedTarget && this.$el.contains(event.relatedTarget)) return;
            this.startCarousel();
        },

        moveEvent(action) {
            const count = this.selectedDateEvents.length;
            if (count <= 1) return;

            this.stopCarousel();
            const step = action === 'next' ? 1 : -1;
            this.activeEventIndex = (this.activeEventIndex + step + count) % count;
        },

        handleCarouselKey(event) {
            const action = horizontalKeyAction(event, this.$el);
            if (!action) return;

            event.preventDefault();
            this.moveEvent(action);
        },
    };
}
