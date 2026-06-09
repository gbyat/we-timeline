/**
 * Timeline Menu Frontend Script (embedded menus only)
 *
 * @package Webentwicklerin\Timeline
 */

import './timeline-progress.js';

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

        ensureMenuSeparators(menu);

        const hasServerRenderedItems = menuContainer.querySelectorAll('.we-timeline-menu__item').length > 0;
        const decadeSuffix = menu.dataset.decadeSuffix || 's';
        if (hasServerRenderedItems) {
            attachMenuClickHandlers(menuContainer, timelineBlock);
        } else {
            const menuItems = buildMenuItems(timelineItems, granularity, decadeSuffix);
            renderMenu(menuContainer, menuItems, timelineBlock);
        }

        setupStickyMenuTop(menu, timelineBlock);
        setupScrollBehavior(menu, timelineItems);
    });

    /**
     * Keep sticky top menu below WP admin bar and theme header.
     *
     * @param {Element} menu          Menu nav element.
     * @param {Element} timelineBlock Timeline root element.
     */
    function setupStickyMenuTop(menu, timelineBlock) {
        const needsStickyTop =
            menu.classList.contains('we-timeline-menu--top') ||
            menu.classList.contains('we-timeline-menu--sidebar');

        if (!needsStickyTop) {
            return;
        }

        const updateStickyMenuTop = () => {
            let top = 0;
            const adminBar = document.getElementById('wpadminbar');
            if (adminBar) {
                top = Math.max(top, adminBar.getBoundingClientRect().bottom);
            }
            top = Math.max(top, measureStickyHeaderInset(timelineBlock));
            timelineBlock.style.setProperty('--we-timeline-sticky-menu-top', `${top}px`);
        };

        updateStickyMenuTop();
        window.addEventListener('scroll', updateStickyMenuTop, { passive: true });
        window.addEventListener('resize', updateStickyMenuTop, { passive: true });
    }

    /**
     * Attach click handlers to server-rendered menu buttons (data-value, data-type, data-first-id).
     */
    function attachMenuClickHandlers(menuContainer, timelineBlock) {
        menuContainer.querySelectorAll('.we-timeline-menu__item').forEach((btn) => {
            const value = btn.dataset.value;
            const type = btn.dataset.type;
            const firstId = btn.dataset.firstId;
            btn.addEventListener('click', () => {
                const targetId = type === 'item' ? value : firstId;
                if (targetId) {
                    scrollToItem(targetId, timelineBlock);
                }
            });
        });
    }

    /**
     * Parse flexible date strings (year, YYYY-MM, or full date) for menu sorting.
     *
     * @param {string} dateStr Date from data-date.
     * @return {number} Unix timestamp in ms, or NaN.
     */
    /**
     * Navigation label: first heading in the item (H1–H6), with legacy title class fallback.
     *
     * @param {Element} item Timeline item element.
     * @return {string}
     */
    function getItemNavigationTitle(item) {
        const navTarget = getItemScrollTarget(item);
        if (navTarget && navTarget !== item && navTarget.textContent?.trim()) {
            return navTarget.textContent.trim();
        }
        const legacyTitle = item.querySelector('.we-timeline__item-title');
        if (legacyTitle?.textContent?.trim()) {
            return legacyTitle.textContent.trim();
        }
        const heading = item.querySelector('h1, h2, h3, h4, h5, h6');
        return heading?.textContent?.trim() || '';
    }

    /**
     * Resolve the element to scroll to (heading/date landmark, not the whole item container).
     *
     * @param {Element} item Timeline item article.
     * @return {Element}
     */
    function getItemScrollTarget(item) {
        const navTargetId = item.dataset.navTarget;
        if (navTargetId) {
            const escaped = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(navTargetId) : navTargetId;
            const byId = item.querySelector(`#${escaped}`);
            if (byId) {
                return byId;
            }
        }

        const marked = item.querySelector('.we-timeline__nav-target');
        if (marked) {
            return marked;
        }

        const heading = item.querySelector('h1, h2, h3, h4, h5, h6');
        if (heading) {
            return heading;
        }

        return item;
    }

    function parseTimelineDate(dateStr) {
        if (!dateStr) {
            return NaN;
        }
        const trimmed = String(dateStr).trim();
        if (/^\d{4}$/.test(trimmed)) {
            return new Date(`${trimmed}-07-01T00:00:00`).getTime();
        }
        if (/^\d{4}-\d{2}$/.test(trimmed)) {
            return new Date(`${trimmed}-01T00:00:00`).getTime();
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return new Date(`${trimmed}T00:00:00`).getTime();
        }
        const normalized = trimmed.replace(' ', 'T');
        return new Date(normalized).getTime();
    }

    /**
     * Calendar year from a timeline date string, or null when invalid.
     *
     * @param {string} dateStr Raw date value.
     * @return {number|null}
     */
    function getTimelineYearFromDate(dateStr) {
        const timestamp = parseTimelineDate(dateStr);
        if (Number.isNaN(timestamp)) {
            return null;
        }
        const year = new Date(timestamp).getFullYear();
        if (year < 1000 || year > 9999) {
            return null;
        }
        return year;
    }

    /**
     * Build menu items from timeline items.
     */
    function buildMenuItems(timelineItems, granularity, decadeSuffix = 's') {
        // Normalize granularity value
        const normalizedGranularity = (granularity || 'auto').toLowerCase().trim();

        const items = Array.from(timelineItems).map((item) => {
            const date = item.dataset.date;
            const id = item.dataset.id;
            const title = getItemNavigationTitle(item);
            const timestamp = parseTimelineDate(date);
            const year = date ? getTimelineYearFromDate(date) : null;

            return {
                id,
                date,
                title: title || id,
                timestamp: Number.isNaN(timestamp) ? null : timestamp,
                year,
                hasDate: year !== null,
            };
        });

        if (normalizedGranularity === 'items') {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        }

        const datedItems = items.filter((item) => item.hasDate && item.timestamp !== null);
        const undatedItems = items.filter((item) => !item.hasDate || item.timestamp === null);

        if (datedItems.length === 0) {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        }

        let groupedMenu = [];
        if (normalizedGranularity === 'auto') {
            groupedMenu = autoGranularity(datedItems);
        } else {
            groupedMenu = groupByGranularity(datedItems, normalizedGranularity, decadeSuffix);
        }

        const undatedMenu = undatedItems.map((item) => ({
            label: item.title,
            value: item.id,
            type: 'item',
        }));

        return groupedMenu.concat(undatedMenu);
    }

    /**
     * Auto-determine granularity.
     */
    function autoGranularity(items) {
        if (items.length === 0) {
            return [];
        }

        const dates = items.map((item) => item.timestamp).filter((ts) => ts !== null);
        if (dates.length === 0) {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        }

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
        }
        if (years <= 5) {
            return groupByGranularity(items, 'months');
        }
        return groupByGranularity(items, 'years');
    }

    /**
     * Group items by granularity.
     */
    function groupByGranularity(items, granularity, decadeSuffix = 's') {
        if (granularity === 'items') {
            return items.map((item) => ({
                label: item.title,
                value: item.id,
                type: 'item',
            }));
        }

        const groups = {};

        items.forEach((item) => {
            if (!item.hasDate || item.year === null) {
                return;
            }
            let key;

            if (granularity === 'decades') {
                key = Math.floor(item.year / 10) * 10;
            } else if (granularity === 'years') {
                key = item.year;
            } else if (granularity === 'months') {
                const date = new Date(item.date);
                if (Number.isNaN(date.getTime())) {
                    return;
                }
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
                    label = `${decade}${decadeSuffix}`; // e.g., "1920s" or "1920er"
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
    /**
     * Resolve separator character for compact menus.
     *
     * @param {Element} menu Menu nav element.
     * @return {string}
     */
    function getMenuSeparatorChar(menu) {
        if (!menu?.classList.contains('we-timeline-menu--compact')) {
            return '';
        }

        const explicit = (menu.dataset.menuSeparatorChar || '').trim();
        if (explicit) {
            return explicit;
        }

        const mode = menu.dataset.menuSeparators || 'none';
        if (mode === 'pipe') {
            return '|';
        }
        if (mode === 'middot') {
            return '·';
        }
        if (mode === 'hyphen') {
            return '-';
        }
        return '';
    }

    /**
     * Insert separator spans between server-rendered compact menu buttons when missing.
     *
     * @param {Element} menu Menu nav element.
     */
    function ensureMenuSeparators(menu) {
        const separatorChar = getMenuSeparatorChar(menu);
        if (!separatorChar) {
            return;
        }

        const menuContainer = menu.querySelector('.we-timeline-menu__items');
        if (!menuContainer || menuContainer.querySelector('.we-timeline-menu__separator')) {
            return;
        }

        const buttons = Array.from(menuContainer.querySelectorAll('.we-timeline-menu__item'));
        buttons.forEach((button, index) => {
            if (index === 0) {
                return;
            }

            const separator = document.createElement('span');
            separator.className = 'we-timeline-menu__separator';
            separator.setAttribute('aria-hidden', 'true');
            separator.textContent = separatorChar;
            menuContainer.insertBefore(separator, button);
        });
    }

    /**
     * Append a non-interactive separator between compact menu links.
     *
     * @param {Element} menuContainer Menu items wrapper.
     * @param {string}  separatorChar Separator character.
     */
    function appendMenuSeparator(menuContainer, separatorChar) {
        const separator = document.createElement('span');
        separator.className = 'we-timeline-menu__separator';
        separator.setAttribute('aria-hidden', 'true');
        separator.textContent = separatorChar;
        menuContainer.appendChild(separator);
    }

    function renderMenu(menuContainer, menuItems, timelineBlock) {
        if (!menuContainer) {
            return;
        }

        const menu = menuContainer.closest('.we-timeline-menu');
        const separatorChar = getMenuSeparatorChar(menu);

        menuContainer.innerHTML = '';

        menuItems.forEach((item, index) => {
            if (index > 0 && separatorChar) {
                appendMenuSeparator(menuContainer, separatorChar);
            }

            const button = document.createElement('button');
            button.className = 'we-timeline-menu__item';
            button.textContent = item.label;
            button.dataset.value = item.value;
            button.dataset.type = item.type;

            if (item.items) {
                button.addEventListener('click', () => {
                    scrollToFirstItem(item.items, timelineBlock);
                });
            } else {
                button.addEventListener('click', () => {
                    scrollToItem(item.value, timelineBlock);
                });
            }

            menuContainer.appendChild(button);
        });
    }

    /**
     * Scroll to first item in group.
     */
    function scrollToFirstItem(items, timelineBlock) {
        if (items.length === 0) {
            return;
        }
        scrollToItem(items[0].id, timelineBlock);
    }

    /**
     * Read a CSS length custom property (px/rem) from the timeline block.
     *
     * @param {Element} timelineBlock Timeline root element.
     * @param {string}  property      Custom property name.
     * @return {number} Pixels.
     */
    function readTimelineCssLength(timelineBlock, property) {
        const raw = getComputedStyle(timelineBlock).getPropertyValue(property).trim();
        if (!raw) {
            return 0;
        }
        const probe = document.createElement('div');
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        probe.style.width = raw;
        document.body.appendChild(probe);
        const pixels = probe.getBoundingClientRect().width;
        document.body.removeChild(probe);
        return pixels;
    }

    /**
     * Measure current bottom edge of theme header(s) matched by block selector.
     * Re-run on every scroll so shrinking/expanding sticky headers stay accurate.
     *
     * @param {Element} timelineBlock Timeline root element.
     * @return {number}
     */
    function measureStickyHeaderInset(timelineBlock) {
        const selector = (timelineBlock.dataset.stickyHeaderSelector || '').trim();
        if (!selector) {
            return 0;
        }

        let inset = 0;

        try {
            timelineBlock.ownerDocument.querySelectorAll(selector).forEach((element) => {
                const style = getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                const position = style.position;
                const stickTop = parseFloat(style.top) || 0;

                if (rect.height <= 0 || rect.bottom <= 0) {
                    return;
                }

                if (position === 'fixed') {
                    if (rect.top < window.innerHeight) {
                        inset = Math.max(inset, rect.bottom);
                    }
                    return;
                }

                if (position === 'sticky') {
                    if (rect.top <= stickTop + 2) {
                        inset = Math.max(inset, rect.bottom);
                    } else {
                        inset = Math.max(inset, stickTop + rect.height);
                    }
                    return;
                }

                if (rect.top <= stickTop + 2 && rect.bottom > stickTop) {
                    inset = Math.max(inset, rect.bottom);
                }
            });
        } catch (error) {
            return 0;
        }

        return inset;
    }

    /**
     * Viewport Y for scroll anchor: below sticky site header + sticky timeline menu (mobile).
     *
     * @param {Element} timelineBlock Timeline root element.
     * @return {number}
     */
    function getNavAnchorY(timelineBlock) {
        const blockStyles = getComputedStyle(timelineBlock);
        let inset =
            readTimelineCssLength(timelineBlock, '--we-timeline-nav-anchor-min') +
            readTimelineCssLength(timelineBlock, '--we-timeline-nav-anchor-offset');

        const adminBar = document.getElementById('wpadminbar');
        if (adminBar) {
            inset = Math.max(inset, adminBar.getBoundingClientRect().bottom);
        }

        inset = Math.max(inset, measureStickyHeaderInset(timelineBlock));

        const menu = timelineBlock.querySelector('.we-timeline-menu');
        if (menu) {
            const menuStyle = getComputedStyle(menu);
            const menuPosition = menuStyle.position;

            // Sticky menu above items (mobile): reserve its height at the stick position.
            if (menuPosition === 'sticky') {
                const menuRect = menu.getBoundingClientRect();
                const stickTop = parseFloat(menuStyle.top) || 0;
                const reservedBottom =
                    menuRect.top <= stickTop + 2
                        ? menuRect.bottom
                        : stickTop + menuRect.height;
                inset = Math.max(inset, reservedBottom);
            }
        }

        // Optional CSS fallback when no selector is set (static px).
        const headerInset = parseFloat(blockStyles.getPropertyValue('--we-timeline-sticky-header-offset'));
        if (!Number.isNaN(headerInset) && headerInset > 0) {
            inset = Math.max(inset, headerInset);
        }

        return inset;
    }

    /**
     * Nudge scroll so the target sits on the current anchor line.
     *
     * @param {Element} target        Scroll target (heading).
     * @param {Element} timelineBlock Timeline root element.
     * @return {boolean} Whether a scroll adjustment was made.
     */
    function alignScrollTargetToAnchor(target, timelineBlock) {
        const delta = target.getBoundingClientRect().top - getNavAnchorY(timelineBlock);
        if (Math.abs(delta) <= 2) {
            return false;
        }

        window.scrollTo({
            top: Math.max(0, window.scrollY + delta),
            behavior: 'auto',
        });

        return true;
    }

    /**
     * Run callback after programmatic smooth scroll finishes (and header transitions settle).
     *
     * @param {Function} callback Callback.
     */
    function afterScrollSettles(callback) {
        const runWithHeaderTransitionPass = () => {
            callback();
            window.setTimeout(callback, 200);
        };

        if ('onscrollend' in window) {
            window.addEventListener('scrollend', runWithHeaderTransitionPass, { once: true });
            return;
        }

        let timer;
        const onScroll = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => {
                window.removeEventListener('scroll', onScroll);
                runWithHeaderTransitionPass();
            }, 100);
        };

        window.addEventListener('scroll', onScroll);
    }

    /**
     * Scroll to timeline item — align heading with timeline block top (calm page scroll).
     */
    function scrollToItem(itemId, timelineBlock = document) {
        const scope = timelineBlock || document;
        const escapedId = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(itemId) : itemId;
        const item = scope.querySelector(`.we-timeline__item[data-id="${escapedId}"]`);
        if (!item) {
            return;
        }

        const target = getItemScrollTarget(item);
        const anchorY = getNavAnchorY(scope);
        const delta = target.getBoundingClientRect().top - anchorY;

        window.scrollTo({
            top: Math.max(0, window.scrollY + delta),
            behavior: 'smooth',
        });

        afterScrollSettles(() => {
            alignScrollTargetToAnchor(target, scope);
        });

        if (typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
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

        const timelineBlock = menu.closest('.wp-block-we-timeline-timeline');

        // Get menu items to determine their types
        const menuItems = Array.from(menuContainer.querySelectorAll('.we-timeline-menu__item'));
        
        // Find the item whose nav target is closest to the timeline top anchor.
        function findActiveItem() {
            if (!timelineBlock) {
                return null;
            }

            const referencePoint = getNavAnchorY(timelineBlock);
            const viewportHeight = window.innerHeight;

            let closestItem = null;
            let closestDistance = Infinity;
            
            timelineItems.forEach((item) => {
                const target = getItemScrollTarget(item);
                const rect = target.getBoundingClientRect();
                const distance = Math.abs(rect.top - referencePoint);
                
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
                const itemYear = getTimelineYearFromDate(itemDate);
                if (itemYear !== null) {
                    const itemDateObj = new Date(itemDate);
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
            }

            // Update menu active state and aria-current for accessibility
            menuItems.forEach((btn) => {
                btn.classList.remove('is-active');
                btn.removeAttribute('aria-current');
            });
            if (activeMenuItem) {
                activeMenuItem.classList.add('is-active');
                activeMenuItem.setAttribute('aria-current', 'true');
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
