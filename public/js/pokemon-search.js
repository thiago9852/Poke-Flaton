(() => {

    // ==========================================
    // UTOCOMPLETE DROPDOWN (Usado na Home)
    // ==========================================
    const autocompleteInput = document.getElementById('global-search-input');
    const resultsContainer = document.getElementById('search-autocomplete-results');
    const searchDataEl = document.getElementById('global-search-data');

    if (autocompleteInput && resultsContainer && searchDataEl) {
        const searchUrl = searchDataEl.dataset.urlSearch;
        let autoTimeoutId;

        autocompleteInput.addEventListener('input', function () {
            clearTimeout(autoTimeoutId);
            const query = this.value.trim();

            if (query.length < 2) {
                resultsContainer.style.display = 'none';
                return;
            }

            autoTimeoutId = setTimeout(async () => {
                try {
                    const response = await fetch(`${searchUrl}?q=${encodeURIComponent(query)}`);
                    const data = await response.json();

                    resultsContainer.innerHTML = '';

                    if (data.length > 0) {
                        data.forEach(poke => {
                            const a = document.createElement('a');
                            a.href = poke.url;
                            a.className = 'autocomplete-item';
                            a.innerHTML = `
                                <img src="${poke.sprite}" alt="${poke.name}">
                                <div class="autocomplete-item-info">
                                    <span class="poke-id">#${String(poke.id).padStart(3, '0')}</span>
                                    <span class="poke-name">${poke.name}</span>
                                </div>
                            `;
                            resultsContainer.appendChild(a);
                        });
                        resultsContainer.style.display = 'flex';
                    } else {
                        resultsContainer.innerHTML = '<div style="padding: 15px; text-align: center; color: var(--text-muted);">Nenhum Pokémon encontrado.</div>';
                        resultsContainer.style.display = 'flex';
                    }
                } catch (e) {
                    console.error("Erro na busca de pokémons", e);
                }
            }, 300);
        });

        autocompleteInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 2 && resultsContainer.innerHTML.trim() !== '') {
                resultsContainer.style.display = 'flex';
            }
        });

        if (window.globalSearchClickHandler) {
            document.removeEventListener('click', window.globalSearchClickHandler);
        }
        window.globalSearchClickHandler = function (e) {
            if (!autocompleteInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                resultsContainer.style.display = 'none';
            }
        };
        document.addEventListener('click', window.globalSearchClickHandler);
    }

    // ==========================================
    // GRID FILTER SPA (Usado na Listagem)
    // ==========================================
    const gridContainer = document.querySelector('.pokemon-grid-container');
    const gridForm = document.querySelector('.filter-section .search-form');
    const gridSearchInput = gridForm ? gridForm.querySelector('.search-input') : null;
    const typeFilterContainer = document.querySelector('.type-filter-container');

    if (gridContainer && gridForm && gridSearchInput) {
        let gridTimeoutId;

        const fetchAndUpdateGrid = async (urlStr, pushState = true) => {
            try {
                gridContainer.style.opacity = '0.5';
                gridContainer.style.transition = 'opacity 0.2s';

                const response = await fetch(urlStr, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                const newGridContainer = doc.querySelector('.pokemon-grid-container');
                if (newGridContainer) gridContainer.innerHTML = newGridContainer.innerHTML;

                const newTypeBadges = doc.querySelector('.type-badges-list');
                if (newTypeBadges && typeFilterContainer) {
                    document.querySelector('.type-badges-list').innerHTML = newTypeBadges.innerHTML;
                }

                if (pushState) window.history.pushState({}, '', urlStr);
            } catch (e) {
                console.error('Erro ao atualizar grid de Pokémons:', e);
            } finally {
                gridContainer.style.opacity = '1';
            }
        };

        gridSearchInput.addEventListener('input', function () {
            clearTimeout(gridTimeoutId);
            const query = this.value;

            // Verifica botão limpar
            let clearBtn = document.querySelector('.filter-section .clear-search-btn');
            const searchBox = document.querySelector('.filter-section .search-box');
            if (query.trim() !== '') {
                if (!clearBtn && searchBox) {
                    clearBtn = document.createElement('a');
                    clearBtn.href = '#'; clearBtn.className = 'clear-search-btn';
                    clearBtn.style.cssText = 'position: absolute; right: 16px; color: var(--text-muted); z-index: 2;';
                    clearBtn.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                    searchBox.appendChild(clearBtn);
                }
            } else if (clearBtn && !new URLSearchParams(window.location.search).has('type') && (!gridForm.querySelector('.sort-select') || gridForm.querySelector('.sort-select').value === '')) clearBtn.remove();

            // Realiza a busca no Grid
            gridTimeoutId = setTimeout(() => {
                const url = new URL(gridForm.action, window.location.origin);
                const currentParams = new URLSearchParams(window.location.search);
                if (currentParams.has('type')) url.searchParams.set('type', currentParams.get('type'));
                if (query.trim() !== '') url.searchParams.set('q', query);
                const sortSelect = gridForm.querySelector('.sort-select');
                if (sortSelect) url.searchParams.set('sort', sortSelect.value);
                fetchAndUpdateGrid(url.toString());
            }, 400);
        });

        const sortSelect = gridForm.querySelector('.sort-select');
        if (sortSelect) {
            sortSelect.addEventListener('change', function () {
                const url = new URL(gridForm.action, window.location.origin);
                const currentParams = new URLSearchParams(window.location.search);
                if (currentParams.has('type')) url.searchParams.set('type', currentParams.get('type'));
                if (gridSearchInput && gridSearchInput.value.trim() !== '') url.searchParams.set('q', gridSearchInput.value);
                url.searchParams.set('sort', this.value);
                fetchAndUpdateGrid(url.toString());
            });
        }

        gridForm.addEventListener('submit', (e) => e.preventDefault());
        
        gridForm.addEventListener('click', (e) => {
            const cbf = e.target.closest('.btn-clear-filters');
            if (cbf) {
                e.preventDefault();
                if (gridSearchInput) gridSearchInput.value = '';
                const sortSel = gridForm.querySelector('.sort-select');
                if (sortSel) sortSel.value = '';
                
                document.querySelectorAll('.type-badge-link').forEach(badge => badge.classList.remove('active'));

                const url = new URL(gridForm.action, window.location.origin);
                fetchAndUpdateGrid(url.toString());

                const clearSearch = document.querySelector('.filter-section .clear-search-btn');
                if (clearSearch) clearSearch.remove();
            }
        });

        document.querySelector('.filter-section .search-box')?.addEventListener('click', (e) => { const cb = e.target.closest('.clear-search-btn'); if (cb) { e.preventDefault(); gridSearchInput.value = ''; gridSearchInput.dispatchEvent(new Event('input')); } });
        gridContainer.addEventListener('click', (e) => { const pageLink = e.target.closest('.pagination-container a'); if (pageLink) { e.preventDefault(); fetchAndUpdateGrid(pageLink.href); window.scrollTo({ top: 0, behavior: 'smooth' }); } });
        if (typeFilterContainer) typeFilterContainer.addEventListener('click', (e) => { const typeLink = e.target.closest('.type-badge-link'); if (typeLink) { e.preventDefault(); fetchAndUpdateGrid(typeLink.href); } });
        if (window.pokemonSearchPopstateHandler) {
            window.removeEventListener('popstate', window.pokemonSearchPopstateHandler);
        }
        window.pokemonSearchPopstateHandler = () => {
            fetchAndUpdateGrid(window.location.href, false);
            const params = new URLSearchParams(window.location.search);
            if (gridSearchInput) gridSearchInput.value = params.get('q') || '';
            const sortSel = gridForm.querySelector('.sort-select');
            if (sortSel) sortSel.value = params.get('sort') || '';
        };
        window.addEventListener('popstate', window.pokemonSearchPopstateHandler);
    }
})();