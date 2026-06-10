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

        const desktopMenuHtml = menuContainer.innerHTML;

        setupStickyMenuTop(menu, timelineBlock);
        updateScrollMarginTop(timelineBlock);
        const scrollBehavior = setupScrollBehavior(menu, timelineItems);

        setupMobileMenuMode({
            menu,
            timelineBlock,
            timelineItems,
            menuContainer,
            granularity,
            decadeSuffix,
            desktopMenuHtml,
            onMenuRebuild: () => {
                updateScrollMarginTop(timelineBlock);
                scrollBehavior.refresh();
            },
        });
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

        let stickyTopRaf = 0;

        const updateStickyMenuTop = () => {
            let top = 0;
            const adminBar = document.getElementById('wpadminbar');
            if (adminBar) {
                top = Math.max(top, adminBar.getBoundingClientRect().bottom);
            }
            top = Math.max(top, measureStickyHeaderInset(timelineBlock));
            // Ceil avoids a 1px gap when theme headers animate height with subpixel values.
            timelineBlock.style.setProperty('--we-timeline-sticky-menu-top', `${Math.ceil(top)}px`);
            updateScrollMarginTop(timelineBlock);
        };

        const scheduleStickyMenuTopUpdate = () => {
            if (stickyTopRaf) {
                return;
            }
            stickyTopRaf = window.requestAnimationFrame(() => {
                stickyTopRaf = 0;
                updateStickyMenuTop();
            });
        };

        updateStickyMenuTop();
        window.addEventListener('scroll', scheduleStickyMenuTopUpdate, { passive: true });
        window.addEventListener('resize', scheduleStickyMenuTopUpdate, { passive: true });

        // Shrinking sticky headers often animate via CSS after scroll stops; scroll events alone miss that.
        observeStickyHeaderSizeChanges(timelineBlock, scheduleStickyMenuTopUpdate);
    }

    /**
     * Re-measure sticky menu offset when the theme header resizes (CSS transitions, class toggles).
     *
     * @param {Element}  timelineBlock Timeline root element.
     * @param {Function} callback      Update handler.
     */
    function observeStickyHeaderSizeChanges(timelineBlock, callback) {
        const selector = (timelineBlock.dataset.stickyHeaderSelector || '').trim();
        const doc = timelineBlock.ownerDocument;

        const observeElement = (element) => {
            if (!element || element.dataset.weTimelineStickyObserve === '1') {
                return;
            }
            element.dataset.weTimelineStickyObserve = '1';

            if (typeof ResizeObserver !== 'undefined') {
                const observer = new ResizeObserver(callback);
                observer.observe(element);
            }

            element.addEventListener('transitionrun', callback);
            element.addEventListener('transitionend', callback);
            element.addEventListener('animationend', callback);
        };

        const adminBar = doc.getElementById('wpadminbar');
        observeElement(adminBar);

        if (!selector) {
            return;
        }

        try {
            doc.querySelectorAll(selector).forEach(observeElement);
        } catch (error) {
            // Invalid selector — ignore.
        }
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
            return Date.parse(`${trimmed}-07-01T00:00:00Z`);
        }
        if (/^\d{4}-\d{2}$/.test(trimmed)) {
            return Date.parse(`${trimmed}-01T00:00:00Z`);
        }
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return Date.parse(`${trimmed}T00:00:00Z`);
        }
        const normalized = trimmed.replace(' ', 'T');
        return Date.parse(normalized);
    }

    /**
     * Calendar year from a timeline date string, or null when invalid.
     *
     * @param {string} dateStr Raw date value.
     * @return {number|null}
     */
    function getTimelineYearFromDate(dateStr) {
        const trimmed = String(dateStr || '').trim();
        const yearMatch = trimmed.match(/^(\d{4})/);
        if (yearMatch) {
            const year = parseInt(yearMatch[1], 10);
            if (year >= 1000 && year <= 9999) {
                return year;
            }
        }

        const timestamp = parseTimelineDate(dateStr);
        if (Number.isNaN(timestamp)) {
            return null;
        }

        const year = new Date(timestamp).getUTCFullYear();
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

        return groupedMenu;
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

            if (item.items && item.items.length > 0) {
                button.dataset.firstId = item.items[0].id;
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
     * Reserve vertical space for a sticky timeline menu (top bar or mobile sidebar).
     * Uses stick position + full height so scroll targets stay below the menu after scroll.
     *
     * @param {Element} menu          Menu nav element.
     * @param {Element} timelineBlock Timeline root element.
     * @return {number}
     */
    function getStickyTimelineMenuInset(menu, timelineBlock) {
        if (!menu) {
            return 0;
        }

        const position = getComputedStyle(menu).position;

        // Fixed sidebar (desktop): does not cover the top of the content column.
        if (position === 'fixed') {
            return 0;
        }

        if (position !== 'sticky') {
            return 0;
        }

        const stickTop = readTimelineCssLength(timelineBlock, '--we-timeline-sticky-menu-top');
        const menuHeight = menu.offsetHeight || menu.getBoundingClientRect().height;

        return stickTop + menuHeight;
    }

    /**
     * Sync scroll-margin-top for items/headings with the live anchor inset.
     *
     * @param {Element} timelineBlock Timeline root element.
     */
    function updateScrollMarginTop(timelineBlock) {
        if (!timelineBlock) {
            return;
        }

        timelineBlock.style.setProperty('--we-timeline-scroll-margin-top', `${getNavAnchorY(timelineBlock)}px`);
    }

    /**
     * Viewport Y for scroll anchor: below sticky site header + sticky timeline menu + gap.
     *
     * @param {Element} timelineBlock Timeline root element.
     * @return {number}
     */
    function getNavAnchorY(timelineBlock) {
        const blockStyles = getComputedStyle(timelineBlock);
        let inset = readTimelineCssLength(timelineBlock, '--we-timeline-nav-anchor-offset');

        const adminBar = document.getElementById('wpadminbar');
        if (adminBar) {
            inset = Math.max(inset, adminBar.getBoundingClientRect().bottom);
        }

        inset = Math.max(inset, measureStickyHeaderInset(timelineBlock));

        const menu = timelineBlock.querySelector('.we-timeline-menu');
        if (menu) {
            inset = Math.max(inset, getStickyTimelineMenuInset(menu, timelineBlock));
        }

        // Optional CSS fallback when no selector is set (static px).
        const headerInset = parseFloat(blockStyles.getPropertyValue('--we-timeline-sticky-header-offset'));
        if (!Number.isNaN(headerInset) && headerInset > 0) {
            inset = Math.max(inset, headerInset);
        }

        return inset;
    }

    /**
     * Item top edge for scroll positioning (keeps padding/date above the heading visible).
     *
     * @param {Element} item Timeline item article.
     * @return {Element}
     */
    function getItemScrollAnchor(item) {
        return item;
    }

    /**
     * Nudge scroll so the item top sits on the current anchor line.
     *
     * @param {Element} item          Timeline item article.
     * @param {Element} timelineBlock Timeline root element.
     * @return {boolean} Whether a scroll adjustment was made.
     */
    function alignScrollTargetToAnchor(item, timelineBlock) {
        const delta = getItemScrollAnchor(item).getBoundingClientRect().top - getNavAnchorY(timelineBlock);
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
     * Scroll to timeline item — item top below sticky header/menu; heading remains focus target.
     */
    function scrollToItem(itemId, timelineBlock = document) {
        const scope = timelineBlock || document;
        const escapedId = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(itemId) : itemId;
        const item = scope.querySelector(`.we-timeline__item[data-id="${escapedId}"]`);
        if (!item) {
            return;
        }

        const focusTarget = getItemScrollTarget(item);
        const scrollAnchor = getItemScrollAnchor(item);
        const anchorY = getNavAnchorY(scope);
        const delta = scrollAnchor.getBoundingClientRect().top - anchorY;

        window.scrollTo({
            top: Math.max(0, window.scrollY + delta),
            behavior: 'smooth',
        });

        afterScrollSettles(() => {
            alignScrollTargetToAnchor(item, scope);
        });

        if (typeof focusTarget.focus === 'function') {
            focusTarget.focus({ preventScroll: true });
        }
    }

    /**
     * Clamp mobile menu breakpoint from data attribute.
     *
     * @param {Element} menu Menu nav element.
     * @return {number}
     */
    function getMenuMobileBreakpoint(menu) {
        const parsed = parseInt(menu.dataset.menuMobileBreakpoint, 10);
        if (Number.isNaN(parsed)) {
            return 768;
        }
        return Math.min(1200, Math.max(480, parsed));
    }

    /**
     * Build per-item menu entries with shortened labels for small screens.
     *
     * @param {NodeListOf<Element>} timelineItems Timeline items.
     * @param {string}              labelFormat    year | year-title | title-truncate.
     * @param {number}              maxLength      Max label length before ellipsis.
     * @return {Array<{label: string, value: string, type: string}>}
     */
    function buildShortLabelMenuItems(timelineItems, labelFormat, maxLength = 28) {
        const format = (labelFormat || 'year').toLowerCase();

        return Array.from(timelineItems).map((item) => {
            const title = getItemNavigationTitle(item) || item.dataset.id || '';
            const year = item.dataset.date ? getTimelineYearFromDate(item.dataset.date) : null;
            const truncated = title.length > maxLength ? `${title.slice(0, maxLength - 1)}…` : title;
            let label = truncated;

            if (format === 'year' && year !== null) {
                label = String(year);
            } else if (format === 'year-title' && year !== null) {
                label = `${year} – ${truncated}`;
            }

            return {
                label,
                value: item.dataset.id,
                type: 'item',
            };
        });
    }

    /**
     * Collect scroll targets from rendered menu buttons.
     *
     * @param {Element} menuContainer Menu items wrapper.
     * @return {Array<{label: string, scrollId: string, type: string, value: string}>}
     */
    function collectMenuEntries(menuContainer) {
        return Array.from(menuContainer.querySelectorAll('.we-timeline-menu__item')).map((button) => {
            const type = button.dataset.type || 'item';
            const value = button.dataset.value || '';
            const scrollId = type === 'item' ? value : button.dataset.firstId || value;

            return {
                label: button.textContent.trim(),
                scrollId,
                type,
                value,
            };
        });
    }

    /**
     * Render a single select control for collapsed mobile menu mode.
     *
     * @param {Element} menuContainer Menu items wrapper.
     * @param {Element} menu          Menu nav element.
     * @param {Array}   entries       Menu entries from collectMenuEntries.
     * @param {Element} timelineBlock Timeline root element.
     */
    function renderCollapsedSelect(menuContainer, menu, entries, timelineBlock) {
        menu.classList.add('we-timeline-menu--collapsed');

        const wrapper = document.createElement('div');
        wrapper.className = 'we-timeline-menu__collapsed';

        const select = document.createElement('select');
        select.className = 'we-timeline-menu__select';
        select.setAttribute('aria-label', menu.getAttribute('aria-label') || 'Jump to timeline periods');

        entries.forEach((entry) => {
            const option = document.createElement('option');
            option.value = entry.scrollId;
            option.textContent = entry.label;
            option.dataset.menuType = entry.type;
            option.dataset.menuValue = entry.value;
            select.appendChild(option);
        });

        select.addEventListener('change', () => {
            if (select.value) {
                scrollToItem(select.value, timelineBlock);
            }
        });

        wrapper.appendChild(select);
        menuContainer.appendChild(wrapper);
    }

    /**
     * Restore desktop menu markup and re-bind handlers.
     *
     * @param {Object} state Mobile menu state bag.
     */
    function restoreDesktopMenu(state) {
        const { menu, menuContainer, timelineBlock, desktopMenuHtml } = state;

        menu.classList.remove(
            'we-timeline-menu--mobile-scroll',
            'we-timeline-menu--collapsed',
            'we-timeline-menu--mobile-hidden',
            'we-timeline-menu--mobile-active'
        );
        menu.removeAttribute('hidden');
        menuContainer.innerHTML = desktopMenuHtml;

        ensureMenuSeparators(menu);

        const buttons = menuContainer.querySelectorAll('.we-timeline-menu__item');
        if (buttons.length > 0) {
            attachMenuClickHandlers(menuContainer, timelineBlock);
        }

        state.onMenuRebuild();
    }

    /**
     * Apply configured mobile menu override below the block breakpoint.
     *
     * @param {Object} state Mobile menu state bag.
     */
    function applyMobileMenu(state) {
        const {
            menu,
            timelineBlock,
            timelineItems,
            menuContainer,
            decadeSuffix,
            onMenuRebuild,
        } = state;

        const mode = (menu.dataset.menuMobileMode || 'inherit').toLowerCase();
        if (mode === 'inherit') {
            return;
        }

        menu.classList.add('we-timeline-menu--mobile-active');
        menu.classList.remove('we-timeline-menu--mobile-scroll', 'we-timeline-menu--collapsed', 'we-timeline-menu--mobile-hidden');
        menu.removeAttribute('hidden');

        if (mode === 'hidden') {
            menu.classList.add('we-timeline-menu--mobile-hidden');
            menu.setAttribute('hidden', '');
            onMenuRebuild();
            return;
        }

        if (mode === 'scroll') {
            menu.classList.add('we-timeline-menu--mobile-scroll');
            onMenuRebuild();
            return;
        }

        menuContainer.innerHTML = '';

        if (mode === 'granularity') {
            const mobileGranularity = menu.dataset.menuGranularityMobile || 'decades';
            const menuItems = buildMenuItems(timelineItems, mobileGranularity, decadeSuffix);
            renderMenu(menuContainer, menuItems, timelineBlock);
            ensureMenuSeparators(menu);
            onMenuRebuild();
            return;
        }

        if (mode === 'short-labels') {
            const labelFormat = menu.dataset.menuMobileLabelFormat || 'year';
            const menuItems = buildShortLabelMenuItems(timelineItems, labelFormat);
            renderMenu(menuContainer, menuItems, timelineBlock);
            ensureMenuSeparators(menu);
            onMenuRebuild();
            return;
        }

        if (mode === 'collapsed') {
            const temp = document.createElement('div');
            temp.innerHTML = state.desktopMenuHtml;
            const entries = collectMenuEntries(temp);

            if (entries.length === 0) {
                menuContainer.innerHTML = state.desktopMenuHtml;
                ensureMenuSeparators(menu);
                attachMenuClickHandlers(menuContainer, timelineBlock);
            } else {
                renderCollapsedSelect(menuContainer, menu, entries, timelineBlock);
            }

            onMenuRebuild();
        }
    }

    /**
     * Toggle mobile viewport class and apply/restore mobile menu modes on resize.
     *
     * @param {Object} state Mobile menu state bag.
     */
    function setupMobileMenuMode(state) {
        const { menu, timelineBlock } = state;
        const breakpoint = getMenuMobileBreakpoint(menu);
        timelineBlock.style.setProperty('--we-timeline-mobile-breakpoint', `${breakpoint}px`);

        const getMediaQuery = () => window.matchMedia(`(max-width: ${breakpoint}px)`);

        const syncMobileViewport = () => {
            const mediaQuery = getMediaQuery();
            if (mediaQuery.matches) {
                timelineBlock.classList.add('we-timeline--mobile-viewport');
            } else {
                timelineBlock.classList.remove('we-timeline--mobile-viewport');
            }
        };

        const syncMobileMenu = () => {
            syncMobileViewport();
            const mode = (menu.dataset.menuMobileMode || 'inherit').toLowerCase();
            const mediaQuery = getMediaQuery();

            if (!mediaQuery.matches || mode === 'inherit') {
                if (menu.classList.contains('we-timeline-menu--mobile-active')) {
                    restoreDesktopMenu(state);
                }
                return;
            }

            applyMobileMenu(state);
        };

        syncMobileMenu();

        const mediaQuery = getMediaQuery();
        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', syncMobileMenu);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(syncMobileMenu);
        }

        window.addEventListener('resize', syncMobileViewport, { passive: true });
    }

    /**
     * Setup scroll behavior.
     *
     * @param {Element} menu          Menu nav element.
     * @param {NodeListOf<Element>} timelineItems Timeline items.
     * @return {{refresh: Function}}
     */
    function setupScrollBehavior(menu, timelineItems) {
        const menuContainer = menu.querySelector('.we-timeline-menu__items');
        if (!menuContainer) {
            return { refresh: () => {} };
        }

        const timelineBlock = menu.closest('.wp-block-we-timeline-timeline');

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
                const rect = getItemScrollAnchor(item).getBoundingClientRect();
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

            const collapsedSelect = menu.classList.contains('we-timeline-menu--collapsed')
                ? menuContainer.querySelector('.we-timeline-menu__select')
                : null;

            if (collapsedSelect) {
                let matchedValue = itemId;

                if (itemDate) {
                    const itemYear = getTimelineYearFromDate(itemDate);
                    if (itemYear !== null) {
                        const itemDateObj = new Date(itemDate);
                        const itemMonth = itemDateObj.getMonth() + 1;
                        const itemDecade = Math.floor(itemYear / 10) * 10;

                        Array.from(collapsedSelect.options).some((option) => {
                            const menuType = option.dataset.menuType;
                            const menuValue = option.dataset.menuValue;

                            if (menuType === 'item' && menuValue === itemId) {
                                matchedValue = option.value;
                                return true;
                            }
                            if (menuType === 'year' && parseInt(menuValue, 10) === itemYear) {
                                matchedValue = option.value;
                                return true;
                            }
                            if (menuType === 'month' && menuValue === `${itemYear}-${String(itemMonth).padStart(2, '0')}`) {
                                matchedValue = option.value;
                                return true;
                            }
                            if (menuType === 'decade' && parseInt(menuValue, 10) === itemDecade) {
                                matchedValue = option.value;
                                return true;
                            }
                            return false;
                        });
                    }
                }

                if (collapsedSelect.value !== matchedValue) {
                    collapsedSelect.value = matchedValue;
                }
                return;
            }

            const menuItems = Array.from(menuContainer.querySelectorAll('.we-timeline-menu__item'));

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

                    menuItems.forEach((menuItem) => {
                        const menuType = menuItem.dataset.type;
                        const menuValue = menuItem.dataset.value;

                        if (menuType === 'item' && menuValue === itemId) {
                            activeMenuItem = menuItem;
                        } else if (menuType === 'year' && parseInt(menuValue, 10) === itemYear) {
                            activeMenuItem = menuItem;
                        } else if (menuType === 'month' && menuValue === `${itemYear}-${String(itemMonth).padStart(2, '0')}`) {
                            activeMenuItem = menuItem;
                        } else if (menuType === 'decade' && parseInt(menuValue, 10) === itemDecade) {
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

        return {
            refresh: updateActiveStates,
        };
    }
})();
