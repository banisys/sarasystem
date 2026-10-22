<div class="fi-nav-search" x-show="$store.sidebar.isOpen" x-transition:enter="fi-transition-enter" x-transition:enter-start="fi-transition-enter-start" x-transition:enter-end="fi-transition-enter-end">
    <div class="fi-nav-search-row">
        <div class="fi-nav-search-input-ctn">
            <svg class="fi-nav-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>

            <input
                type="search"
                id="fi-nav-search-input"
                class="fi-nav-search-input"
                placeholder="جستجو در منو..."
                autocomplete="off"
            />
        </div>

        <button
            type="button"
            id="fi-nav-collapse-all-btn"
            class="fi-nav-collapse-all-btn"
            title="جمع کردن همه منوها"
            aria-label="جمع کردن همه منوها"
        >
            {{-- VS Code's "Collapse Folders in Explorer" (codicon collapse-all): only ever collapses. --}}
            <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <path d="M9 9H4v1h5V9z" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5 3l1-1h7l1 1v7l-1 1h-2v2l-1 1H3l-1-1V6l1-1h2V3zm1 2h4l1 1v4h2V3H6v2zm4 1H3v7h7V6z" />
            </svg>
        </button>
    </div>

    <div class="fi-nav-search-history" id="fi-nav-search-history"></div>
</div>

<style>
    .fi-nav-search {
        padding-block: 0.5rem;
    }

    .fi-nav-search-row {
        display: flex;
        align-items: center;
        gap: 0.375rem;
    }

    .fi-nav-search-input-ctn {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        min-width: 0;
    }

    .fi-nav-collapse-all-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        width: 2.25rem;
        height: 2.25rem;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        color: rgb(107 114 128);
        cursor: pointer;
    }

    .fi-nav-collapse-all-btn:hover {
        color: rgb(17 24 39);
        border-color: rgb(209 213 219);
    }

    .fi-nav-collapse-all-btn svg {
        width: 1rem;
        height: 1rem;
    }

    :is(.dark .fi-nav-collapse-all-btn) {
        border-color: rgb(55 65 81);
        background-color: rgb(31 41 55);
        color: rgb(156 163 175);
    }

    :is(.dark .fi-nav-collapse-all-btn:hover) {
        color: rgb(243 244 246);
        border-color: rgb(75 85 99);
    }

    .fi-nav-search-icon {
        position: absolute;
        inset-inline-start: 0.625rem;
        width: 1rem;
        height: 1rem;
        color: rgb(156 163 175);
        pointer-events: none;
    }

    .fi-nav-search-input {
        width: 100%;
        border-radius: 0.5rem;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        padding-inline-start: 2.25rem;
        padding-inline-end: 0.75rem;
        padding-block: 0.5rem;
        font-size: 0.875rem;
        color: rgb(17 24 39);
    }

    .fi-nav-search-input:focus {
        outline: none;
        border-color: rgb(99 102 241);
        box-shadow: 0 0 0 1px rgb(99 102 241);
    }

    :is(.dark .fi-nav-search-input) {
        border-color: rgb(55 65 81);
        background-color: rgb(31 41 55);
        color: rgb(243 244 246);
    }

    .fi-sidebar-nav-groups .fi-nav-search-hidden {
        display: none !important;
    }

    .fi-sidebar-nav-groups .fi-nav-search-force-open {
        display: block !important;
    }

    .fi-nav-search-history {
        display: flex;
        flex-wrap: wrap;
        gap: 0.375rem;
        margin-top: 0.5rem;
    }

    .fi-nav-search-history:empty,
    .fi-nav-search-history.fi-nav-search-history-empty {
        display: none;
    }

    .fi-nav-search-history-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        max-width: 100%;
        border-radius: 9999px;
        border: 1px solid rgb(229 231 235);
        background-color: rgb(249 250 251);
        padding-inline-start: 0.625rem;
        padding-inline-end: 0.25rem;
        padding-block: 0.125rem;
        font-size: 0.75rem;
        color: rgb(107 114 128);
    }

    .fi-nav-search-history-label {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding-block: 0.125rem;
        color: inherit;
        cursor: pointer;
    }

    .fi-nav-search-history-remove {
        display: inline-flex;
        flex-shrink: 0;
        align-items: center;
        justify-content: center;
        width: 1rem;
        height: 1rem;
        border-radius: 9999px;
        color: inherit;
        opacity: 0.6;
        cursor: pointer;
    }

    .fi-nav-search-history-remove:hover {
        opacity: 1;
        color: rgb(220 38 38);
        background-color: rgb(229 231 235);
    }

    .fi-nav-search-history-remove svg {
        width: 0.75rem;
        height: 0.75rem;
    }

    :is(.dark .fi-nav-search-history-remove:hover) {
        color: rgb(248 113 113);
        background-color: rgb(75 85 99);
    }

    .fi-nav-search-history-chip:hover {
        color: rgb(17 24 39);
        border-color: rgb(209 213 219);
        background-color: rgb(243 244 246);
    }

    :is(.dark .fi-nav-search-history-chip) {
        border-color: rgb(55 65 81);
        background-color: rgb(31 41 55);
        color: rgb(156 163 175);
    }

    :is(.dark .fi-nav-search-history-chip:hover) {
        color: rgb(243 244 246);
        border-color: rgb(75 85 99);
        background-color: rgb(55 65 81);
    }
</style>

<script>
    (function () {
        function normalize(text) {
            return (text || '')
                .toLowerCase()
                .replace(/[ي]/g, 'ی') // ي -> ی
                .replace(/[ك]/g, 'ک') // ك -> ک
                .trim();
        }

        function filterNav(query) {
            const nav = document.querySelector('.fi-sidebar-nav-groups');

            if (!nav) {
                return;
            }

            const normalizedQuery = normalize(query);

            const groups = nav.querySelectorAll(':scope > .fi-sidebar-group');

            if (normalizedQuery === '') {
                nav.querySelectorAll('.fi-nav-search-hidden').forEach((el) => el.classList.remove('fi-nav-search-hidden'));
                nav.querySelectorAll('.fi-nav-search-force-open').forEach((el) => el.classList.remove('fi-nav-search-force-open'));

                return;
            }

            groups.forEach((group) => {
                const groupLabelEl = group.querySelector(':scope > .fi-sidebar-group-btn > .fi-sidebar-group-label');
                const groupLabelMatches = groupLabelEl && normalize(groupLabelEl.textContent).includes(normalizedQuery);

                const items = group.querySelectorAll('.fi-sidebar-item');
                let groupHasMatch = groupLabelMatches;

                items.forEach((item) => {
                    const labelEl = item.querySelector(':scope > .fi-sidebar-item-btn > .fi-sidebar-item-label');
                    const matches = groupLabelMatches || (labelEl && normalize(labelEl.textContent).includes(normalizedQuery));

                    item.classList.toggle('fi-nav-search-hidden', !matches);

                    if (matches) {
                        groupHasMatch = true;

                        let parentSubList = item.parentElement;

                        while (parentSubList && parentSubList !== group) {
                            if (parentSubList.tagName === 'UL') {
                                parentSubList.classList.add('fi-nav-search-force-open');
                            }

                            if (parentSubList.tagName === 'LI') {
                                parentSubList.classList.remove('fi-nav-search-hidden');
                            }

                            parentSubList = parentSubList.parentElement;
                        }
                    }
                });

                group.classList.toggle('fi-nav-search-hidden', !groupHasMatch);

                const groupItemsEl = group.querySelector(':scope > .fi-sidebar-group-items');

                if (groupHasMatch && groupItemsEl) {
                    groupItemsEl.classList.add('fi-nav-search-force-open');
                }
            });
        }

        const URL_PARAM = 'nav_search';
        const STORAGE_KEY = 'fi-nav-search-query';

        function getStoredQuery() {
            try {
                return window.sessionStorage.getItem(STORAGE_KEY) || '';
            } catch (error) {
                return '';
            }
        }

        function setStoredQuery(query) {
            try {
                if (query) {
                    window.sessionStorage.setItem(STORAGE_KEY, query);
                } else {
                    window.sessionStorage.removeItem(STORAGE_KEY);
                }
            } catch (error) {
                //
            }
        }

        function getQueryFromUrl() {
            return new URLSearchParams(window.location.search).get(URL_PARAM) || '';
        }

        function setQueryInUrl(query) {
            const url = new URL(window.location.href);

            if (query) {
                url.searchParams.set(URL_PARAM, query);
            } else {
                url.searchParams.delete(URL_PARAM);
            }

            window.history.replaceState(window.history.state, '', url);
        }

        function persistQuery(query) {
            setQueryInUrl(query);
            setStoredQuery(query);
        }

        function applyQueryFromUrl() {
            const input = document.getElementById('fi-nav-search-input');

            if (!input) {
                return;
            }

            // The URL carries the query across a page refresh; SPA navigation
            // to a fresh URL drops it, so sessionStorage is the fallback that
            // keeps the search alive while moving between pages.
            const query = getQueryFromUrl() || getStoredQuery();

            input.value = query;
            filterNav(query);
            persistQuery(query);
        }

        const HISTORY_KEY = 'fi-nav-search-history';
        const HISTORY_MAX = 3;

        function getHistory() {
            try {
                const parsed = JSON.parse(window.localStorage.getItem(HISTORY_KEY) || '[]');

                return Array.isArray(parsed) ? parsed : [];
            } catch (error) {
                return [];
            }
        }

        function setHistory(history) {
            try {
                window.localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
            } catch (error) {
                //
            }
        }

        function runSearch(query) {
            const input = document.getElementById('fi-nav-search-input');

            if (!input) {
                return;
            }

            input.value = query;
            filterNav(query);
            persistQuery(query);
        }

        function addToHistory(query) {
            const trimmed = (query || '').trim();

            if (!trimmed) {
                return;
            }

            const history = [trimmed, ...getHistory().filter((item) => item !== trimmed)].slice(0, HISTORY_MAX);

            setHistory(history);
            renderHistory();
        }

        function renderHistory() {
            const container = document.getElementById('fi-nav-search-history');

            if (!container) {
                return;
            }

            const history = getHistory();

            container.innerHTML = '';
            container.classList.toggle('fi-nav-search-history-empty', !history.length);

            history.forEach((query) => {
                const chip = document.createElement('span');
                chip.className = 'fi-nav-search-history-chip';

                const label = document.createElement('button');
                label.type = 'button';
                label.className = 'fi-nav-search-history-label';
                label.textContent = query;
                label.title = query;
                label.addEventListener('click', () => {
                    runSearch(query);
                    addToHistory(query);
                });

                // Last child → the chip's inline-end, i.e. its left corner in the RTL panel.
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'fi-nav-search-history-remove';
                remove.title = @js(__('filament-actions::delete.single.label'));
                remove.setAttribute('aria-label', remove.title + ': ' + query);
                remove.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>';
                // Keep focus in the search input: its blur handler re-renders
                // this list, which would detach the button before the click lands.
                remove.addEventListener('mousedown', (event) => event.preventDefault());
                remove.addEventListener('click', () => removeFromHistory(query));

                chip.append(label, remove);
                container.appendChild(chip);
            });
        }

        function removeFromHistory(query) {
            setHistory(getHistory().filter((item) => item !== query));
            renderHistory();
        }

        function bindNavSearch() {
            const input = document.getElementById('fi-nav-search-input');

            if (!input || input.dataset.bound === '1') {
                return;
            }

            input.dataset.bound = '1';

            input.addEventListener('input', (event) => {
                filterNav(event.target.value);
                persistQuery(event.target.value.trim());
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    addToHistory(input.value);
                }
            });

            input.addEventListener('blur', () => addToHistory(input.value));
        }

        function getGroupLabels() {
            const nav = document.querySelector('.fi-sidebar-nav-groups');

            if (!nav) {
                return [];
            }

            return Array.from(nav.querySelectorAll(':scope > .fi-sidebar-group[data-group-label]'))
                .map((el) => el.dataset.groupLabel)
                .filter(Boolean);
        }

        function getSidebarStore() {
            return window.Alpine ? window.Alpine.store('sidebar') : null;
        }

        function collapseAllGroups() {
            const store = getSidebarStore();
            const labels = getGroupLabels();

            if (!store || !Array.isArray(store.collapsedGroups) || !labels.length) {
                return;
            }

            store.collapsedGroups = labels;
        }

        function bindCollapseAllBtn() {
            const btn = document.getElementById('fi-nav-collapse-all-btn');

            if (!btn || btn.dataset.bound === '1') {
                return;
            }

            btn.dataset.bound = '1';

            btn.addEventListener('click', collapseAllGroups);
        }

        function init() {
            bindNavSearch();
            applyQueryFromUrl();
            bindCollapseAllBtn();
            renderHistory();
        }

        document.addEventListener('livewire:navigated', init);
        document.addEventListener('DOMContentLoaded', init);
        init();
    })();
</script>
