function initTierList() {
    const configEl = document.getElementById('tierListConfig');
    if (!configEl) {
        return;
    }
    if (configEl.dataset.initialized === 'true') {
        return;
    }
    configEl.dataset.initialized = 'true';

    const pokemonTypes = JSON.parse(configEl.getAttribute('data-types-map') || '{}');
    const translations = {
        loadSuccess: configEl.getAttribute('data-trans-load-success'),
        moveUp: configEl.getAttribute('data-trans-move-up'),
        moveDown: configEl.getAttribute('data-trans-move-down'),
        settings: configEl.getAttribute('data-trans-settings'),
        deleteRow: configEl.getAttribute('data-trans-delete'),
        confirmDeleteTier: configEl.getAttribute('data-trans-confirm-delete-tier'),
        confirmClear: configEl.getAttribute('data-trans-confirm-clear'),
        confirmReset: configEl.getAttribute('data-trans-confirm-reset'),
        shareSuccess: configEl.getAttribute('data-trans-share-success'),
        sharePrompt: configEl.getAttribute('data-trans-share-prompt'),
        shareError: configEl.getAttribute('data-trans-share-error'),
        exportGenerating: configEl.getAttribute('data-trans-export-generating'),
        exportSuccess: configEl.getAttribute('data-trans-export-success'),
        exportError: configEl.getAttribute('data-trans-export-error'),
        cloneSuccess: configEl.getAttribute('data-trans-clone-success') || "Estrutura clonada com sucesso!"
    };

    // Estrutura Padrão
    const defaultTiers = [
        { label: 'S', color: '#ff7f7f', pokemons: [] },
        { label: 'A', color: '#ffbf7f', pokemons: [] },
        { label: 'B', color: '#ffdf7f', pokemons: [] },
        { label: 'C', color: '#ffff7f', pokemons: [] }
    ];

    let tiers = [];
    let activeRowIndex = 0;
    let editingRowIndex = null;

    // Elementos do DOM
    const tierBoard = document.getElementById('tierBoard');
    const pokemonBank = document.getElementById('pokemonBank');
    const bankCount = document.getElementById('bankCount');
    const searchInput = document.getElementById('searchPokemon');
    const filterType = document.getElementById('filterType');
    const filterGeneration = document.getElementById('filterGeneration');

    // Elementos do Modal de Configurações
    const settingsModal = document.getElementById('settingsModal');
    const rowLabelInput = document.getElementById('rowLabelInput');
    const rowColorInput = document.getElementById('rowColorInput');
    const btnCloseModal = document.getElementById('btnCloseModal');
    const btnCancelSettings = document.getElementById('btnCancelSettings');
    const btnSaveSettings = document.getElementById('btnSaveSettings');
    const colorOptions = document.querySelectorAll('.color-option');

    // Elementos do Modal de Publicação
    const publishModal = document.getElementById('publishModal');
    const btnPublishOpen = document.getElementById('btnPublishOpen');
    const btnPublishClose = document.getElementById('btnPublishClose');
    const btnPublishCancel = document.getElementById('btnPublishCancel');
    const btnPublishSubmit = document.getElementById('btnPublishSubmit');
    const publishTitleInput = document.getElementById('publishTitleInput');
    const tagCheckboxLabels = document.querySelectorAll('.tag-checkbox-label');

    // Elementos do Toast
    const tierToast = document.getElementById('tierToast');
    const toastMessage = document.getElementById('toastMessage');

    // Função para obter cor de contraste
    function getContrastColor(hexColor) {
        const r = parseInt(hexColor.slice(1, 3), 16);
        const g = parseInt(hexColor.slice(3, 5), 16);
        const b = parseInt(hexColor.slice(5, 7), 16);
        const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
        return (yiq >= 128) ? '#11131a' : '#ffffff';
    }

    // Gera Geração por ID
    function getGenerationById(id) {
        if (id >= 1 && id <= 151) return 1;
        if (id >= 152 && id <= 251) return 2;
        if (id >= 252 && id <= 386) return 3;
        if (id >= 387 && id <= 493) return 4;
        if (id >= 494 && id <= 649) return 5;
        if (id >= 650 && id <= 721) return 6;
        if (id >= 722 && id <= 809) return 7;
        if (id >= 810 && id <= 905) return 8;
        if (id >= 906 && id <= 1025) return 9;
        return 0;
    }

    // Inicializa o Estado das Tiers
    function initTiers() {
        // 1. Tenta clone vindo do servidor
        const cloneState = configEl.getAttribute('data-clone-state');
        const cloneTitle = configEl.getAttribute('data-clone-title');
        if (cloneState) {
            try {
                const parsed = JSON.parse(cloneState);
                if (Array.isArray(parsed)) {
                    tiers = parsed;
                    if (publishTitleInput && cloneTitle) {
                        publishTitleInput.value = cloneTitle + " (Clone)";
                    }
                    window.history.replaceState({}, document.title, window.location.pathname);
                    saveToLocalStorage();
                    showToast(translations.cloneSuccess);
                    renderBoard();
                    applyFilters();
                    return;
                }
            } catch (e) {
                console.error("Erro ao importar clone:", e);
            }
        }

        // 2. Tenta os parâmetros da URL (Link de compartilhamento)
        const urlParams = new URLSearchParams(window.location.search);
        const shareState = urlParams.get('share');
        if (shareState) {
            try {
                const decodedData = decodeURIComponent(escape(atob(shareState.replace(/_/g, '/').replace(/-/g, '+'))));
                const parsed = JSON.parse(decodedData);
                if (Array.isArray(parsed)) {
                    tiers = parsed;
                    window.history.replaceState({}, document.title, window.location.pathname);
                    saveToLocalStorage();
                    showToast(translations.loadSuccess);
                    renderBoard();
                    applyFilters();
                    return;
                }
            } catch (e) {
                console.error("Erro ao importar estado:", e);
            }
        }

        // 3. Tenta o LocalStorage
        const saved = localStorage.getItem('pokeflaton_tier_list');
        if (saved) {
            try {
                tiers = JSON.parse(saved);
                if (Array.isArray(tiers) && tiers.length > 0) {
                    renderBoard();
                    applyFilters();
                    return;
                }
            } catch (e) {
                console.error("Erro ao carregar do LocalStorage:", e);
            }
        }

        // 4. Fallback para o Padrão
        tiers = JSON.parse(JSON.stringify(defaultTiers));
        renderBoard();
        applyFilters();
    }

    // Salva Estado no LocalStorage
    function saveToLocalStorage() {
        const rows = document.querySelectorAll('.tier-row');
        rows.forEach((row, idx) => {
            if (tiers[idx]) {
                const cards = row.querySelector('.tier-content-col').querySelectorAll('.tier-pokemon-card');
                tiers[idx].pokemons = Array.from(cards).map(card => parseInt(card.getAttribute('data-id')));
            }
        });
        localStorage.setItem('pokeflaton_tier_list', JSON.stringify(tiers));
    }

    // Renderiza a Grade de Tiers
    function renderBoard() {
        tierBoard.innerHTML = '';

        tiers.forEach((tier, index) => {
            const contrastColor = getContrastColor(tier.color);
            const rowEl = document.createElement('div');
            rowEl.className = 'tier-row';
            if (index === activeRowIndex) {
                rowEl.classList.add('active-row');
            }
            rowEl.setAttribute('data-index', index);

            // Coluna do Label
            const labelCol = document.createElement('div');
            labelCol.className = 'tier-label-col';
            labelCol.style.backgroundColor = tier.color;
            labelCol.style.color = contrastColor;
            
            let labelInner = '';
            if (index === activeRowIndex) {
                labelInner += `<span class="active-row-indicator" style="background-color: ${contrastColor}; box-shadow: 0 0 8px ${contrastColor}"></span>`;
            }
            labelInner += `<span class="tier-label-text">${tier.label}</span>`;
            labelCol.innerHTML = labelInner;

            // Clique no label abre as configurações
            labelCol.addEventListener('click', () => {
                setActiveRow(index);
                openSettingsModal(index);
            });

            // Coluna de Conteúdo
            const contentCol = document.createElement('div');
            contentCol.className = 'tier-content-col';
            contentCol.addEventListener('click', () => {
                setActiveRow(index);
            });

            // Adiciona os cards de pokémon salvos nesta coluna de conteúdo
            tier.pokemons.forEach(pokeId => {
                const pCard = document.getElementById(`pokemon-${pokeId}`);
                if (pCard) {
                    contentCol.appendChild(pCard);
                }
            });

            // Eventos de Drag and Drop para as fileiras
            contentCol.addEventListener('dragover', (e) => {
                e.preventDefault();
                rowEl.classList.add('drag-over');
                
                const draggingCard = document.querySelector('.tier-pokemon-card.dragging');
                if (draggingCard) {
                    const afterElement = getDragAfterElement(contentCol, e.clientX);
                    if (afterElement == null) {
                        contentCol.appendChild(draggingCard);
                    } else {
                        contentCol.insertBefore(draggingCard, afterElement);
                    }
                }
            });

            contentCol.addEventListener('dragleave', () => {
                rowEl.classList.remove('drag-over');
            });

            contentCol.addEventListener('drop', (e) => {
                e.preventDefault();
                rowEl.classList.remove('drag-over');
                
                const draggingCard = document.querySelector('.tier-pokemon-card.dragging');
                if (draggingCard) {
                    contentCol.appendChild(draggingCard);
                    saveToLocalStorage();
                    applyFilters();
                }
            });

            // Coluna de Ações
            const actionsCol = document.createElement('div');
            actionsCol.className = 'tier-actions-col';

            const btnUp = document.createElement('button');
            btnUp.className = 'btn-action-icon';
            btnUp.title = translations.moveUp;
            btnUp.innerHTML = '<i class="fa-solid fa-chevron-up"></i>';
            btnUp.disabled = (index === 0);
            btnUp.addEventListener('click', () => moveRow(index, -1));

            const btnDown = document.createElement('button');
            btnDown.className = 'btn-action-icon';
            btnDown.title = translations.moveDown;
            btnDown.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';
            btnDown.disabled = (index === tiers.length - 1);
            btnDown.addEventListener('click', () => moveRow(index, 1));

            const btnSettings = document.createElement('button');
            btnSettings.className = 'btn-action-icon';
            btnSettings.title = translations.settings;
            btnSettings.innerHTML = '<i class="fa-solid fa-gear"></i>';
            btnSettings.addEventListener('click', () => openSettingsModal(index));

            const btnDel = document.createElement('button');
            btnDel.className = 'btn-action-icon btn-delete';
            btnDel.title = translations.deleteRow;
            btnDel.innerHTML = '<i class="fa-solid fa-trash-can"></i>';
            btnDel.addEventListener('click', () => deleteRow(index));

            actionsCol.appendChild(btnUp);
            actionsCol.appendChild(btnDown);
            actionsCol.appendChild(btnSettings);
            actionsCol.appendChild(btnDel);

            rowEl.appendChild(labelCol);
            rowEl.appendChild(contentCol);
            rowEl.appendChild(actionsCol);
            tierBoard.appendChild(rowEl);
        });

        updateCounts();
    }

    // Função auxiliar para obter o elemento mais próximo à posição do mouse para reordenar
    function getDragAfterElement(container, x) {
        const draggableElements = [...container.querySelectorAll('.tier-pokemon-card:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = x - box.left - box.width / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Função auxiliar para definir o indicador de linha ativa para clique de fallback móvel
    function setActiveRow(index) {
        if (index >= 0 && index < tiers.length) {
            activeRowIndex = index;
            
            const rows = document.querySelectorAll('.tier-row');
            rows.forEach((row, idx) => {
                const labelCol = row.querySelector('.tier-label-col');
                const textSpan = labelCol.querySelector('.tier-label-text');
                const tier = tiers[idx];
                const contrastColor = getContrastColor(tier.color);
                
                row.classList.remove('active-row');
                
                let labelInner = '';
                if (idx === activeRowIndex) {
                    row.classList.add('active-row');
                    labelInner += `<span class="active-row-indicator" style="background-color: ${contrastColor}; box-shadow: 0 0 8px ${contrastColor}"></span>`;
                }
                labelInner += `<span class="tier-label-text">${textSpan.textContent}</span>`;
                labelCol.innerHTML = labelInner;
            });
        }
    }

    // Eventos de Drag & Drop nas cartas de Pokémon no Banco
    const bindDraggableCards = () => {
        const cards = document.querySelectorAll('.tier-pokemon-card');
        cards.forEach(card => {
            card.addEventListener('dragstart', () => {
                card.classList.add('dragging');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
            });

            card.addEventListener('click', () => {
                const isInsideTier = card.parentElement.classList.contains('tier-content-col');
                if (isInsideTier) {
                    pokemonBank.appendChild(card);
                } else {
                    const activeContentCol = document.querySelectorAll('.tier-row')[activeRowIndex].querySelector('.tier-content-col');
                    activeContentCol.appendChild(card);
                }
                saveToLocalStorage();
                applyFilters();
            });
        });
    };

    bindDraggableCards();

    // Eventos de Drop no Banco
    pokemonBank.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    pokemonBank.addEventListener('drop', (e) => {
        e.preventDefault();
        const draggingCard = document.querySelector('.tier-pokemon-card.dragging');
        if (draggingCard) {
            pokemonBank.appendChild(draggingCard);
            saveToLocalStorage();
            applyFilters();
        }
    });

    // Função para atualizar os contadores
    function updateCounts() {
        const allCards = document.querySelectorAll('.tier-pokemon-card');
        const totalInBank = pokemonBank.querySelectorAll('.tier-pokemon-card').length;
        bankCount.textContent = `${totalInBank} / ${allCards.length}`;
    }

    // Função para aplicar os filtros de busca
    function applyFilters() {
        const searchText = searchInput.value.toLowerCase().trim();
        const selectedType = filterType.value;
        const selectedGen = filterGeneration.value ? parseInt(filterGeneration.value) : null;

        const bankCards = pokemonBank.querySelectorAll('.tier-pokemon-card');
        bankCards.forEach(card => {
            const id = parseInt(card.getAttribute('data-id'));
            const name = card.getAttribute('data-name').toLowerCase();
            const dexId = parseInt(card.getAttribute('data-dex'));
            const gen = getGenerationById(dexId);

            const matchesSearch = !searchText || name.includes(searchText) || String(id) === searchText;
            const matchesType = !selectedType || (pokemonTypes[id] && pokemonTypes[id].includes(selectedType));
            const matchesGen = !selectedGen || gen === selectedGen;

            if (matchesSearch && matchesType && matchesGen) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });

        updateCounts();
    }

    searchInput.addEventListener('input', applyFilters);
    filterType.addEventListener('change', applyFilters);
    filterGeneration.addEventListener('change', applyFilters);

    // Operações de Ações da Linha
    function moveRow(index, offset) {
        const targetIndex = index + offset;
        if (targetIndex >= 0 && targetIndex < tiers.length) {
            const temp = tiers[index];
            tiers[index] = tiers[targetIndex];
            tiers[targetIndex] = temp;

            if (activeRowIndex === index) {
                activeRowIndex = targetIndex;
            } else if (activeRowIndex === targetIndex) {
                activeRowIndex = index;
            }

            saveToLocalStorage();
            renderBoard();
            applyFilters();
        }
    }

    function deleteRow(index) {
        if (confirm(translations.confirmDeleteTier)) {
            const rows = document.querySelectorAll('.tier-row');
            const rowEl = rows[index];
            const cards = rowEl.querySelector('.tier-content-col').querySelectorAll('.tier-pokemon-card');
            
            cards.forEach(card => {
                pokemonBank.appendChild(card);
            });

            tiers.splice(index, 1);

            if (activeRowIndex >= tiers.length) {
                activeRowIndex = Math.max(0, tiers.length - 1);
            }

            saveToLocalStorage();
            renderBoard();
            applyFilters();
        }
    }

    function addRow() {
        const newTier = { label: 'NEW', color: '#9e9e9e', pokemons: [] };
        tiers.push(newTier);
        activeRowIndex = tiers.length - 1;
        saveToLocalStorage();
        renderBoard();
        applyFilters();
        window.scrollTo({
            top: document.querySelector('.tier-board').getBoundingClientRect().bottom + window.scrollY - 300,
            behavior: 'smooth'
        });
    }

    // Operações de Ferramentas
    function clearAll() {
        if (confirm(translations.confirmClear)) {
            const allCardsInTiers = tierBoard.querySelectorAll('.tier-pokemon-card');
            allCardsInTiers.forEach(card => {
                pokemonBank.appendChild(card);
            });
            
            tiers.forEach(tier => {
                tier.pokemons = [];
            });

            saveToLocalStorage();
            renderBoard();
            applyFilters();
        }
    }

    function resetTiers() {
        if (confirm(translations.confirmReset)) {
            const allCardsInTiers = tierBoard.querySelectorAll('.tier-pokemon-card');
            allCardsInTiers.forEach(card => {
                pokemonBank.appendChild(card);
            });

            tiers = JSON.parse(JSON.stringify(defaultTiers));
            activeRowIndex = 0;
            
            saveToLocalStorage();
            renderBoard();
            applyFilters();
        }
    }

    // Operações de edição do modal
    function openSettingsModal(index) {
        editingRowIndex = index;
        const tier = tiers[index];
        
        rowLabelInput.value = tier.label;
        rowColorInput.value = tier.color;

        colorOptions.forEach(opt => {
            if (opt.getAttribute('data-color') === tier.color.toLowerCase()) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });

        settingsModal.classList.add('show');
        rowLabelInput.focus();
    }

    function closeSettingsModal() {
        settingsModal.classList.remove('show');
        editingRowIndex = null;
    }

    colorOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            colorOptions.forEach(o => o.classList.remove('selected'));
            opt.classList.add('selected');
            rowColorInput.value = opt.getAttribute('data-color');
        });
    });

    rowColorInput.addEventListener('input', () => {
        colorOptions.forEach(o => o.classList.remove('selected'));
    });

    btnSaveSettings.addEventListener('click', () => {
        if (editingRowIndex !== null) {
            const newLabel = rowLabelInput.value.trim() || 'Tier';
            const newColor = rowColorInput.value;

            tiers[editingRowIndex].label = newLabel;
            tiers[editingRowIndex].color = newColor;

            saveToLocalStorage();
            renderBoard();
            applyFilters();
            closeSettingsModal();
        }
    });

    btnCloseModal.addEventListener('click', closeSettingsModal);
    btnCancelSettings.addEventListener('click', closeSettingsModal);
    
    settingsModal.addEventListener('click', (e) => {
        if (e.target === settingsModal) {
            closeSettingsModal();
        }
    });

    // --- Modal de Publicação Lógica ---
    if (btnPublishOpen) {
        btnPublishOpen.addEventListener('click', () => {
            const totalClassified = tierBoard.querySelectorAll('.tier-pokemon-card').length;
            if (totalClassified === 0) {
                showToast("Por favor, classifique ao menos um Pokémon antes de publicar!");
                return;
            }
            publishModal.classList.add('show');
            publishTitleInput.focus();
        });
    }

    const closePublishModal = () => {
        if (publishModal) {
            publishModal.classList.remove('show');
        }
    };

    if (btnPublishClose) btnPublishClose.addEventListener('click', closePublishModal);
    if (btnPublishCancel) btnPublishCancel.addEventListener('click', closePublishModal);
    if (publishModal) {
        publishModal.addEventListener('click', (e) => {
            if (e.target === publishModal) {
                closePublishModal();
            }
        });
    }

    // Toggle de estilo dos checkboxes de tags
    tagCheckboxLabels.forEach(label => {
        const cb = label.querySelector('input[type="checkbox"]');
        cb.addEventListener('change', () => {
            const tagValue = cb.value;
            if (cb.checked) {
                label.classList.add(`selected-tag-${tagValue}`);
            } else {
                label.classList.remove(`selected-tag-${tagValue}`);
            }
        });
    });

    if (btnPublishSubmit) {
        btnPublishSubmit.addEventListener('click', () => {
            const title = publishTitleInput.value.trim();
            if (!title) {
                showToast("Por favor, insira um título para a sua Tier List!");
                return;
            }

            // Capturar tags selecionadas
            const checkedTags = Array.from(document.querySelectorAll('input[name="publishTags"]:checked')).map(cb => cb.value);

            // Compilar estado atual
            saveToLocalStorage();
            
            const rows = document.querySelectorAll('.tier-row');
            const compiledState = [];
            rows.forEach((row, idx) => {
                if (tiers[idx]) {
                    const cards = row.querySelector('.tier-content-col').querySelectorAll('.tier-pokemon-card');
                    compiledState.push({
                        label: tiers[idx].label,
                        color: tiers[idx].color,
                        pokemons: Array.from(cards).map(card => parseInt(card.getAttribute('data-id')))
                    });
                }
            });

            btnPublishSubmit.disabled = true;
            showToast("Publicando na nuvem...");

            fetch('/tier-list/salvar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    title: title,
                    tags: checkedTags,
                    state: compiledState
                })
            })
            .then(res => {
                if (!res.ok) throw new Error("Erro do servidor");
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    showToast("Publicada com sucesso! Redirecionando...");
                    localStorage.removeItem('pokeflaton_tier_list');
                    setTimeout(() => {
                        window.location.href = `/tier-list/${data.id}`;
                    }, 1000);
                } else {
                    btnPublishSubmit.disabled = false;
                    showToast(data.error || "Falha ao publicar a Tier List.");
                }
            })
            .catch(err => {
                console.error("Erro:", err);
                btnPublishSubmit.disabled = false;
                showToast("Falha de conexão ao publicar.");
            });
        });
    }

    // Notificações Toast
    function showToast(message) {
        toastMessage.textContent = message;
        tierToast.classList.add('show');
        setTimeout(() => {
            tierToast.classList.remove('show');
        }, 3000);
    }

    // Geração de link para compartilhamento
    function shareLink() {
        saveToLocalStorage();
        
        const simplified = tiers.map(t => ({
            l: t.label,
            c: t.color,
            p: t.pokemons
        }));

        try {
            const serialized = btoa(unescape(encodeURIComponent(JSON.stringify(simplified))))
                .replace(/\//g, '_')
                .replace(/\+/g, '-')
                .replace(/=/g, '');

            const shareUrl = `${window.location.origin}/tier-list/criar?share=${serialized}`;
            
            navigator.clipboard.writeText(shareUrl).then(() => {
                showToast(translations.shareSuccess);
            }).catch(err => {
                console.error("Falha ao copiar:", err);
                prompt(translations.sharePrompt, shareUrl);
            });
        } catch (e) {
            console.error("Erro ao gerar link:", e);
            showToast(translations.shareError);
        }
    }

    // Exporta o tier list como imagem PNG usando html2canvas
    function exportAsImage() {
        showToast(translations.exportGenerating);

        const actionsCols = document.querySelectorAll('.tier-actions-col');
        actionsCols.forEach(col => col.style.display = 'none');

        // Dynamically update export title text if available from modal input
        if (publishTitleInput && publishTitleInput.value.trim() !== '') {
            const exportTitleText = document.getElementById('exportTitleText');
            if (exportTitleText) {
                exportTitleText.textContent = publishTitleInput.value.trim();
            }
        }

        const board = document.getElementById('tierBoard');
        const wrapper = document.getElementById('tierBoardWrapper') || board;
        const exportHeader = document.getElementById('exportHeader');
        const exportFooter = document.getElementById('exportFooter');

        if (exportHeader) exportHeader.style.display = 'flex';
        if (exportFooter) exportFooter.style.display = 'block';

        const rows = board.querySelectorAll('.tier-row');
        rows.forEach(row => {
            row.style.borderBottom = '2px solid rgba(255, 255, 255, 0.08)';
        });

        html2canvas(wrapper, {
            useCORS: true,
            backgroundColor: '#090a0f',
            scale: 2 
        }).then(canvas => {
            if (exportHeader) exportHeader.style.display = 'none';
            if (exportFooter) exportFooter.style.display = 'none';
            actionsCols.forEach(col => col.style.display = 'flex');
            rows.forEach(row => {
                row.style.borderBottom = '';
            });

            const link = document.createElement('a');
            link.download = 'pokeflaton-tier-list.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
            showToast(translations.exportSuccess);
        }).catch(err => {
            if (exportHeader) exportHeader.style.display = 'none';
            if (exportFooter) exportFooter.style.display = 'none';
            console.error("Erro ao exportar imagem:", err);
            actionsCols.forEach(col => col.style.display = 'flex');
            rows.forEach(row => {
                row.style.borderBottom = '';
            });
            showToast(translations.exportError);
        });
    }

    const btnExport = document.getElementById('btnExport');
    if (btnExport) btnExport.addEventListener('click', exportAsImage);

    const btnShare = document.getElementById('btnShare');
    if (btnShare) btnShare.addEventListener('click', shareLink);

    const btnAddRow = document.getElementById('btnAddRow');
    if (btnAddRow) btnAddRow.addEventListener('click', addRow);

    const btnResetTiers = document.getElementById('btnResetTiers');
    if (btnResetTiers) btnResetTiers.addEventListener('click', resetTiers);

    const btnClearAll = document.getElementById('btnClearAll');
    if (btnClearAll) btnClearAll.addEventListener('click', clearAll);

    // Init
    initTiers();
}

document.addEventListener('turbo:load', initTierList);
initTierList();