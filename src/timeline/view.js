/**
 * Timeline Menu Frontend Script (embedded menus only)
 *
 * @package Webentwicklerin\Timeline
 */

(function () {
    // Process menus embedded in timeline blocks (from showMenu setting).
    const embeddedMenus = document.querySelectorAll('.wp-block-we-timeline-timeline .we-timeline-menu:not(.processed)');

    embeddedMenus.forEach((menu) => {
        menu.classList.add('processed');

        // Get granularity from data attribute
        const granularity = menu.dataset.granularity || 'auto';

        // Find the parent timeline block.
        const timelineBlock = menu.closest('.wp-block-we-timeline-timeline');
        if (!timelineBlock) {
            return;
        }

        // Get timeline items from the same block.
        const timelineItems = timelineBlock.querySelectorAll('.we-timeline__item');
        if (timelineItems.length === 0) {
            return;
        }

        const menuContainer = menu.querySelector('.we-timeline-menu__items');
        if (!menuContainer) {
            return;
        }

        // Build menu items with granularity.
        const menuItems = buildMenuItems(timelineItems, granularity);
        renderMenu(menuContainer, menuItems);

        // Add scroll behavior.
        setupScrollBehavior(menu, timelineItems);
    });

    /**
     * Build menu items from timeline items.
     */
    function buildMenuItems(timelineItems, granularity) {
        // Normalize granularity value
        const normalizedGranularity = (granularity || 'auto').toLowerCase().trim();

        const items = Array.from(timelineItems).map((item) => {
            const date = item.dataset.date;
            const id = item.dataset.id;
            const title = item.querySelector('.we-timeline__item-title')?.textContent || '';

            return {
                id,
                date,
                title,
                timestamp: new Date(date).getTime(),
            };
        });

        if (normalizedGranularity === 'auto') {
            return autoGranularity(items);
        }

        return groupByGranularity(items, normalizedGranularity);
    }

    /**
     * Auto-determine granularity.
     */
    function autoGranularity(items) {
        if (items.length === 0) {
            return [];
        }

        const dates = items.map((item) => item.timestamp);
        const minDate = Math.min(...dates);
        const maxDate = Math.max(...dates);
        const span = maxDate - minDate;
        const years = span / (365 * 24 * 60 * 60 * 1000);

        if (years < 1) {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        } else if (years <= 5) {
            return groupByGranularity(items, 'months');
        } else {
            return groupByGranularity(items, 'years');
        }
    }

    /**
     * Group items by granularity.
     */
    function groupByGranularity(items, granularity) {
        if (granularity === 'items') {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        }

        const groups = {};

        items.forEach((item) => {
            const date = new Date(item.date);
            let key;

            if (granularity === 'decades') {
                const year = date.getFullYear();
                key = Math.floor(year / 10) * 10; // e.g., 1924 -> 1920
            } else if (granularity === 'years') {
                key = date.getFullYear();
            } else if (granularity === 'months') {
                key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
            }

            if (!groups[key]) {
                groups[key] = [];
            }
            groups[key].push(item);
        });

        return Object.keys(groups)
            .sort()
            .map((key) => {
                const date = new Date(groups[key][0].date);
                let label;

                if (granularity === 'decades') {
                    const decade = parseInt(key);
                    label = `${decade}s`; // e.g., "1920s"
                } else if (granularity === 'years') {
                    label = key;
                } else if (granularity === 'months') {
                    label = date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                }

                return {
                    label,
                    value: key,
                    type: granularity.slice(0, -1), // Remove 's' from 'decades'/'years'/'months'
                    items: groups[key],
                };
            });
    }

    /**
     * Render menu.
     */
    function renderMenu(menuContainer, menuItems) {
        if (!menuContainer) {
            return;
        }

        menuContainer.innerHTML = '';

        menuItems.forEach((item) => {
            const button = document.createElement('button');
            button.className = 'we-timeline-menu__item';
            button.textContent = item.label;
            button.dataset.value = item.value;
            button.dataset.type = item.type;

            if (item.items) {
                button.addEventListener('click', () => {
                    scrollToFirstItem(item.items);
                });
            } else {
                button.addEventListener('click', () => {
                    scrollToItem(item.value);
                });
            }

            menuContainer.appendChild(button);
        });
    }

    /**
     * Scroll to first item in group.
     */
    function scrollToFirstItem(items) {
        if (items.length === 0) {
            return;
        }
        scrollToItem(items[0].id);
    }

    /**
     * Scroll to timeline item.
     */
    function scrollToItem(itemId) {
        const item = document.querySelector(`.we-timeline__item[data-id="${itemId}"]`);
        if (item) {
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    /**
     * Setup scroll behavior.
     */
    function setupScrollBehavior(menu, timelineItems) {
        const menuContainer = menu.querySelector('.we-timeline-menu__items');
        if (!menuContainer) {
            return;
        }

        // Get menu items to determine their types
        const menuItems = Array.from(menuContainer.querySelectorAll('.we-timeline-menu__item'));
        
        // Find the item closest to the reference point (1/3 from top of viewport)
        function findActiveItem() {
            const viewportHeight = window.innerHeight;
            const referencePoint = viewportHeight / 3; // 1/3 from top
            
            let closestItem = null;
            let closestDistance = Infinity;
            
            timelineItems.forEach((item) => {
                const rect = item.getBoundingClientRect();
                const itemCenter = rect.top + rect.height / 2;
                const distance = Math.abs(itemCenter - referencePoint);
                
                // Only consider items that are at least partially in viewport
                if (rect.bottom > 0 && rect.top < viewportHeight) {
                    if (distance < closestDistance) {
                        closestDistance = distance;
                        closestItem = item;
                    }
                }
            });
            
            return closestItem;
        }
        
        // Update active states
        function updateActiveStates() {
            const activeItem = findActiveItem();
            
            if (!activeItem) {
                return;
            }
            
            const itemId = activeItem.dataset.id;
            const itemDate = activeItem.dataset.date;
            
            // Mark timeline item as active
            timelineItems.forEach((item) => {
                item.classList.remove('is-active');
            });
            activeItem.classList.add('is-active');

            // Find and highlight corresponding menu item
            let activeMenuItem = null;
            
            // First try to find exact match by item ID
            activeMenuItem = menuContainer.querySelector(`[data-value="${itemId}"]`);
            
            // If not found, try to find by date/group
            if (!activeMenuItem && itemDate) {
                const itemDateObj = new Date(itemDate);
                const itemYear = itemDateObj.getFullYear();
                const itemMonth = itemDateObj.getMonth() + 1;
                const itemDecade = Math.floor(itemYear / 10) * 10;
                
                // Check each menu item to see if it matches
                menuItems.forEach((menuItem) => {
                    const menuType = menuItem.dataset.type;
                    const menuValue = menuItem.dataset.value;
                    
                    if (menuType === 'item' && menuValue === itemId) {
                        activeMenuItem = menuItem;
                    } else if (menuType === 'year' && parseInt(menuValue) === itemYear) {
                        activeMenuItem = menuItem;
                    } else if (menuType === 'month' && menuValue === `${itemYear}-${String(itemMonth).padStart(2, '0')}`) {
                        activeMenuItem = menuItem;
                    } else if (menuType === 'decade' && parseInt(menuValue) === itemDecade) {
                        activeMenuItem = menuItem;
                    }
                });
            }

            // Update menu active state
            menuItems.forEach((btn) => {
                btn.classList.remove('is-active');
            });
            if (activeMenuItem) {
                activeMenuItem.classList.add('is-active');
            }
        }
        
        // Throttled scroll handler
        let ticking = false;
        function onScroll() {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateActiveStates();
                    ticking = false;
                });
                ticking = true;
            }
        }

        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        
        // Initial update
        updateActiveStates();
    }
})();
