/**
 * Virtual scrolling for hospital/dental clinic schedules
 * Improves performance by only rendering visible schedule items
 */
export function createVirtualizedSchedule(scheduleItems = []) {
    return {
        items: scheduleItems,
        scrollTop: 0,
        itemHeight: 64, // Height in pixels of each schedule item (with spacing)
        containerHeight: 384, // Height of scrollable container (h-96)
        
        init() {
            // Ensure we have valid data
            if (!Array.isArray(this.items)) {
                this.items = [];
            }
        },
        
        handleScroll() {
            if (this.$refs.container) {
                this.scrollTop = this.$refs.container.scrollTop;
            }
        },
        
        visibleSlots() {
            if (this.items.length === 0) {
                return [];
            }
            
            // Calculate which items should be visible based on scroll position
            const startIndex = Math.floor(this.scrollTop / this.itemHeight);
            const endIndex = Math.ceil((this.scrollTop + this.containerHeight) / this.itemHeight);
            
            // Add buffer for smooth scrolling (show 1 item before and after visible area)
            const bufferedStart = Math.max(0, startIndex - 1);
            const bufferedEnd = Math.min(this.items.length, endIndex + 1);
            
            return this.items.slice(bufferedStart, bufferedEnd).map((item, idx) => {
                // Normalize the item structure for consistent rendering
                return {
                    day: item.day || item.dayEn || item.dayAr || '',
                    dayEn: item.dayEn || item.day || '',
                    dayAr: item.dayAr || item.day || '',
                    time: item.time || item.timeEn || item.timeAr || '',
                    timeEn: item.timeEn || item.time || '',
                    timeAr: item.timeAr || item.time || '',
                    isEmergency: Boolean(item.isEmergency),
                };
            });
        },
    };
}
