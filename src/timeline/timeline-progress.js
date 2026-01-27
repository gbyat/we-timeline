/**
 * Timeline Progress Line Script
 * Colors the timeline line progressively based on scroll position
 *
 * @package Webentwicklerin\Timeline
 */

(function () {
    // Select all vertical timeline layouts
    const timelineBlocks = document.querySelectorAll(
        '.wp-block-we-timeline-timeline.we-timeline--vertical-alternating, ' +
        '.wp-block-we-timeline-timeline.we-timeline--vertical-left, ' +
        '.wp-block-we-timeline-timeline.we-timeline--vertical-right'
    );

    timelineBlocks.forEach((timeline) => {
        const itemsContainer = timeline.querySelector('.we-timeline__items');
        if (!itemsContainer) {
            return;
        }

        const progressLine = itemsContainer;
        const timelineItems = itemsContainer.querySelectorAll('.we-timeline__item');
        
        if (timelineItems.length === 0) {
            return;
        }

        // Calculate progress line height based on scroll position
        function updateProgressLine() {
            const containerRect = itemsContainer.getBoundingClientRect();
            const containerTop = containerRect.top + window.scrollY;
            const containerBottom = containerRect.bottom + window.scrollY;
            const viewportTop = window.scrollY;
            
            // Calculate how far we've scrolled through the timeline
            // Use viewport center as the reference point for a smoother experience
            const viewportCenter = viewportTop + (window.innerHeight / 3);
            const containerHeight = containerBottom - containerTop;
            
            if (viewportCenter < containerTop) {
                // Before timeline - no progress
                progressLine.style.setProperty('--progress-height', '0%');
                return;
            }
            
            if (viewportCenter > containerBottom) {
                // Past timeline - fill completely
                progressLine.style.setProperty('--progress-height', '100%');
                return;
            }
            
            // Calculate progress percentage
            const scrollProgress = Math.max(0, Math.min(1, (viewportCenter - containerTop) / containerHeight));
            const progressPercent = scrollProgress * 100;

            progressLine.style.setProperty('--progress-height', progressPercent + '%');
        }

        // Update on scroll and resize
        let ticking = false;
        function requestUpdate() {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateProgressLine();
                    ticking = false;
                });
                ticking = true;
            }
        }

        window.addEventListener('scroll', requestUpdate, { passive: true });
        window.addEventListener('resize', requestUpdate, { passive: true });
        
        // Initial update
        updateProgressLine();
    });
})();
