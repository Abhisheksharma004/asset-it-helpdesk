/**
 * Searchable Select Component
 * VIROS IT Asset & Service Desk Portal
 * 
 * Transforms standard <select> elements into modern searchable dropdowns
 * with live filtering while maintaining full HTML5 form & event compatibility.
 */

(function () {
    'use strict';

    const SearchableSelect = {
        instances: new WeakMap(),

        /**
         * Initialize all eligible select elements on the page
         */
        initAll: function (root = document) {
            const selects = root.querySelectorAll('select:not([data-no-search])');
            selects.forEach(select => {
                SearchableSelect.init(select);
            });
        },

        /**
         * Initialize a single select element
         */
        init: function (select) {
            if (!select || select.dataset.searchableInitialized === 'true') {
                return;
            }

            select.dataset.searchableInitialized = 'true';

            // Create wrapper
            const wrapper = document.createElement('div');
            wrapper.className = 'custom-select-wrapper';
            if (select.classList.contains('filter-select') || select.classList.contains('asset-filter-select')) {
                wrapper.classList.add('is-filter-select');
            }
            if (select.id) {
                wrapper.dataset.targetId = select.id;
            }

            // Insert wrapper before select and move select inside wrapper
            select.parentNode.insertBefore(wrapper, select);
            wrapper.appendChild(select);
            select.classList.add('searchable-select-hidden');

            // Build UI Elements
            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'custom-select-trigger';
            trigger.setAttribute('aria-haspopup', 'listbox');
            trigger.setAttribute('aria-expanded', 'false');

            const labelSpan = document.createElement('span');
            labelSpan.className = 'custom-select-label';

            const arrowSpan = document.createElement('span');
            arrowSpan.className = 'custom-select-arrow';
            arrowSpan.innerHTML = `
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            `;

            trigger.appendChild(labelSpan);
            trigger.appendChild(arrowSpan);
            wrapper.appendChild(trigger);

            // Dropdown container
            const dropdown = document.createElement('div');
            dropdown.className = 'custom-select-dropdown';

            // Search box at top
            const searchBox = document.createElement('div');
            searchBox.className = 'custom-select-search-box';

            const searchIcon = document.createElement('span');
            searchIcon.className = 'custom-select-search-icon';
            searchIcon.innerHTML = `
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
            `;

            const searchInput = document.createElement('input');
            searchInput.type = 'text';
            searchInput.className = 'custom-select-search-input';
            searchInput.placeholder = 'Search...';
            searchInput.autocomplete = 'off';

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'custom-select-search-clear';
            clearBtn.innerHTML = '&times;';
            clearBtn.style.display = 'none';

            searchBox.appendChild(searchIcon);
            searchBox.appendChild(searchInput);
            searchBox.appendChild(clearBtn);
            dropdown.appendChild(searchBox);

            // Options List
            const optionsContainer = document.createElement('div');
            optionsContainer.className = 'custom-select-options';
            optionsContainer.setAttribute('role', 'listbox');
            dropdown.appendChild(optionsContainer);

            // No Results element
            const noResults = document.createElement('div');
            noResults.className = 'custom-select-no-results';
            noResults.textContent = 'No matching options found';
            noResults.style.display = 'none';
            dropdown.appendChild(noResults);

            wrapper.appendChild(dropdown);

            let highlightedIndex = -1;

            // Render options from native select
            function renderOptions() {
                optionsContainer.innerHTML = '';
                const options = Array.from(select.options);
                const currentVal = select.value;

                options.forEach((opt, idx) => {
                    const optEl = document.createElement('div');
                    optEl.className = 'custom-select-option';
                    optEl.dataset.value = opt.value;
                    optEl.dataset.index = idx;
                    optEl.setAttribute('role', 'option');

                    if (opt.value === currentVal) {
                        optEl.classList.add('is-selected');
                        optEl.setAttribute('aria-selected', 'true');
                    }
                    if (opt.disabled) {
                        optEl.classList.add('is-disabled');
                    }

                    const textSpan = document.createElement('span');
                    textSpan.className = 'custom-select-option-text';
                    textSpan.textContent = opt.textContent;
                    optEl.appendChild(textSpan);

                    const checkSpan = document.createElement('span');
                    checkSpan.className = 'custom-select-check';
                    checkSpan.innerHTML = `
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    `;
                    optEl.appendChild(checkSpan);

                    optEl.addEventListener('click', function (e) {
                        e.stopPropagation();
                        if (opt.disabled) return;
                        selectOption(opt.value);
                    });

                    optionsContainer.appendChild(optEl);
                });

                updateSelectedDisplay();
            }

            // Update Trigger Label
            function updateSelectedDisplay() {
                const selectedOpt = select.options[select.selectedIndex];
                if (selectedOpt) {
                    labelSpan.textContent = selectedOpt.textContent || 'Select...';
                    if (!selectedOpt.value && selectedOpt.value !== '0') {
                        labelSpan.classList.add('is-placeholder');
                    } else {
                        labelSpan.classList.remove('is-placeholder');
                    }
                } else {
                    labelSpan.textContent = 'Select...';
                    labelSpan.classList.add('is-placeholder');
                }

                // Update is-selected in custom list
                const currentVal = select.value;
                optionsContainer.querySelectorAll('.custom-select-option').forEach(el => {
                    if (el.dataset.value === currentVal) {
                        el.classList.add('is-selected');
                        el.setAttribute('aria-selected', 'true');
                    } else {
                        el.classList.remove('is-selected');
                        el.removeAttribute('aria-selected');
                    }
                });

                if (select.disabled) {
                    wrapper.classList.add('is-disabled');
                    trigger.disabled = true;
                } else {
                    wrapper.classList.remove('is-disabled');
                    trigger.disabled = false;
                }
            }

            // Select an option
            function selectOption(val) {
                if (select.value !== val) {
                    select.value = val;
                    // Trigger native events so other scripts respond
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                }
                wrapper.classList.remove('has-error');
                updateSelectedDisplay();
                closeDropdown();
                trigger.focus();
            }

            // Position & open dropdown
            function openDropdown() {
                if (select.disabled) return;

                // Close any other open searchable select
                document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
                    if (w !== wrapper) {
                        w.classList.remove('is-open');
                        w.classList.remove('is-dropup');
                    }
                });

                // Check vertical room
                const rect = wrapper.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;

                if (spaceBelow < 250 && spaceAbove > spaceBelow) {
                    wrapper.classList.add('is-dropup');
                } else {
                    wrapper.classList.remove('is-dropup');
                }

                wrapper.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');

                // Reset search
                searchInput.value = '';
                clearBtn.style.display = 'none';
                filterOptions('');

                // Focus search input
                setTimeout(() => {
                    searchInput.focus();
                }, 50);
            }

            function closeDropdown() {
                wrapper.classList.remove('is-open');
                wrapper.classList.remove('is-dropup');
                trigger.setAttribute('aria-expanded', 'false');
                highlightedIndex = -1;
            }

            // Filter options in real-time
            function filterOptions(query) {
                const q = query.toLowerCase().trim();
                const optionEls = optionsContainer.querySelectorAll('.custom-select-option');
                let visibleCount = 0;
                let firstVisible = -1;

                optionEls.forEach((el, idx) => {
                    const text = el.textContent.toLowerCase();
                    if (!q || text.includes(q)) {
                        el.style.display = 'flex';
                        visibleCount++;
                        if (firstVisible === -1) firstVisible = idx;
                    } else {
                        el.style.display = 'none';
                    }
                });

                if (visibleCount === 0) {
                    noResults.style.display = 'block';
                    noResults.textContent = `No matching options for "${query}"`;
                } else {
                    noResults.style.display = 'none';
                }

                highlightedIndex = firstVisible;
                highlightOption(highlightedIndex);
            }

            function highlightOption(index) {
                const visibleOpts = Array.from(optionsContainer.querySelectorAll('.custom-select-option')).filter(el => el.style.display !== 'none');
                visibleOpts.forEach((el, i) => {
                    if (i === index) {
                        el.classList.add('is-highlighted');
                        el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                    } else {
                        el.classList.remove('is-highlighted');
                    }
                });
            }

            // Event Listeners
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (wrapper.classList.contains('is-open')) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            // Focus on hidden select forwards to trigger
            select.addEventListener('focus', function () {
                trigger.focus();
            });

            // Search input live typing
            searchInput.addEventListener('input', function () {
                const val = searchInput.value;
                clearBtn.style.display = val.length > 0 ? 'block' : 'none';
                filterOptions(val);
            });

            // Clear search
            clearBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                searchInput.value = '';
                clearBtn.style.display = 'none';
                filterOptions('');
                searchInput.focus();
            });

            // Keyboard navigation in search input
            searchInput.addEventListener('keydown', function (e) {
                const visibleOpts = Array.from(optionsContainer.querySelectorAll('.custom-select-option')).filter(el => el.style.display !== 'none' && !el.classList.contains('is-disabled'));

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (visibleOpts.length === 0) return;
                    highlightedIndex = (highlightedIndex + 1) % visibleOpts.length;
                    highlightOption(highlightedIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (visibleOpts.length === 0) return;
                    highlightedIndex = (highlightedIndex - 1 + visibleOpts.length) % visibleOpts.length;
                    highlightOption(highlightedIndex);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (visibleOpts.length > 0 && highlightedIndex >= 0 && visibleOpts[highlightedIndex]) {
                        const val = visibleOpts[highlightedIndex].dataset.value;
                        selectOption(val);
                    }
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    closeDropdown();
                    trigger.focus();
                } else if (e.key === 'Tab') {
                    closeDropdown();
                }
            });

            // Keyboard navigation on trigger button
            trigger.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openDropdown();
                } else if (e.key === 'Escape') {
                    closeDropdown();
                }
            });

            // Listen for native select changes & validation
            select.addEventListener('change', function () {
                updateSelectedDisplay();
            });

            select.addEventListener('invalid', function () {
                wrapper.classList.add('has-error');
            });

            // Handle parent form reset
            if (select.form) {
                select.form.addEventListener('reset', function () {
                    setTimeout(updateSelectedDisplay, 10);
                });
            }

            // Hook programmatic value/selectedIndex updates
            try {
                const origValProp = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
                if (origValProp && origValProp.set) {
                    Object.defineProperty(select, 'value', {
                        get: function () {
                            return origValProp.get.call(this);
                        },
                        set: function (newVal) {
                            origValProp.set.call(this, newVal);
                            updateSelectedDisplay();
                        },
                        configurable: true
                    });
                }

                const origIndexProp = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
                if (origIndexProp && origIndexProp.set) {
                    Object.defineProperty(select, 'selectedIndex', {
                        get: function () {
                            return origIndexProp.get.call(this);
                        },
                        set: function (newIdx) {
                            origIndexProp.set.call(this, newIdx);
                            updateSelectedDisplay();
                        },
                        configurable: true
                    });
                }
            } catch (e) {
                // Ignore descriptor errors
            }

            // MutationObserver to auto-update when JS alters <option> elements (e.g. Dynamic AJAX categories)
            const observer = new MutationObserver(function () {
                renderOptions();
            });
            observer.observe(select, { childList: true, subtree: true, characterData: true });

            // Initial render
            renderOptions();

            // Store instance
            SearchableSelect.instances.set(select, {
                wrapper,
                renderOptions,
                updateSelectedDisplay,
                openDropdown,
                closeDropdown,
                observer
            });
        },

        /**
         * Sync display for a select if value or options updated programmatically
         */
        sync: function (select) {
            const instance = SearchableSelect.instances.get(select);
            if (instance) {
                instance.renderOptions();
            } else if (select) {
                SearchableSelect.init(select);
            }
        }
    };

    // Close any open select when clicking outside
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.custom-select-wrapper')) {
            document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
                w.classList.remove('is-open');
                w.classList.remove('is-dropup');
                const trig = w.querySelector('.custom-select-trigger');
                if (trig) trig.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close on Escape globally
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-select-wrapper.is-open').forEach(w => {
                w.classList.remove('is-open');
                w.classList.remove('is-dropup');
                const trig = w.querySelector('.custom-select-trigger');
                if (trig) trig.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Expose globally
    window.SearchableSelect = SearchableSelect;

    // Auto-init on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SearchableSelect.initAll());
    } else {
        SearchableSelect.initAll();
    }

})();
