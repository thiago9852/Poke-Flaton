(() => {
const TYPE_IDS = {
    'normal': 1, 'fighting': 2, 'flying': 3, 'poison': 4, 'ground': 5,
    'rock': 6, 'bug': 7, 'ghost': 8, 'steel': 9, 'fire': 10,
    'water': 11, 'grass': 12, 'electric': 13, 'psychic': 14, 'ice': 15,
    'dragon': 16, 'dark': 17, 'fairy': 18, 'stellar': 19, 'unknown': 10001,
    'shadow': 10002
};

const cacheKeyPrefix = 'poke_move_type_';

const MESSAGES = {
    pt_BR: {
        minMovesAlert: 'Por favor, selecione pelo menos 4 movimentos para salvar o moveset.',
        maxMovesAlert: 'Você já selecionou o limite máximo de {max} movimentos.',
        typeLabel: 'Tipo',
        powerLabel: 'Poder',
        categoryLabel: 'Categoria',
        descriptionLabel: 'Descrição',
        physicalLabel: 'Físico',
        specialLabel: 'Especial',
        statusLabel: 'Status',
        noDescription: 'Nenhuma descrição disponível.',
        removeMoveTitle: 'Remover golpe',
        typeTranslations: {
            normal: 'Normal', fire: 'Fogo', water: 'Água', grass: 'Grama', electric: 'Elétrico', ice: 'Gelo', fighting: 'Lutador', poison: 'Venenoso', ground: 'Terra', flying: 'Voador', psychic: 'Psíquico', bug: 'Inseto', rock: 'Pedra', ghost: 'Fantasma', dragon: 'Dragão', dark: 'Sombrio', steel: 'Aço', fairy: 'Fada'
        }
    },
    en: {
        minMovesAlert: 'Please select at least 4 moves to save the moveset.',
        maxMovesAlert: 'You have already selected the maximum limit of {max} moves.',
        typeLabel: 'Type',
        powerLabel: 'Power',
        categoryLabel: 'Category',
        descriptionLabel: 'Description',
        physicalLabel: 'Physical',
        specialLabel: 'Special',
        statusLabel: 'Status',
        noDescription: 'No description available.',
        removeMoveTitle: 'Remove move',
        typeTranslations: {
            normal: 'Normal', fire: 'Fire', water: 'Water', grass: 'Grass', electric: 'Electric', ice: 'Ice', fighting: 'Fighting', poison: 'Poison', ground: 'Ground', flying: 'Flying', psychic: 'Psychic', bug: 'Bug', rock: 'Rock', ghost: 'Ghost', dragon: 'Dragon', dark: 'Dark', steel: 'Steel', fairy: 'Fairy'
        }
    }
};

let MAX_MOVES = 4;
let selectedMoves = [];
let currentFilter = 'all';
let searchQuery = '';
let locale = 'pt_BR';

    // Resgatar os dados passados pelo Twig e inicializar
    const appData = document.getElementById('moveset-app-data');
    if (appData) {
        if (appData.dataset.maxMoves) {
            MAX_MOVES = parseInt(appData.dataset.maxMoves, 10);
        }
        if (appData.dataset.locale) {
            locale = appData.dataset.locale;
        }

        selectedMoves = Array(MAX_MOVES).fill(null);

        // Inicializar UI
        updateUI();
        loadAllMoveTypes();

        // Validação ao enviar o formulário
        const formWrapper = document.querySelector('.moveset-form-wrapper');
        if (formWrapper) {
            formWrapper.addEventListener('submit', function (e) {
                const activeMovesCount = selectedMoves.filter(m => m !== null).length;
                if (activeMovesCount < 4) {
                    e.preventDefault();
                    alert(t('minMovesAlert'));
                }
            });
        }

        // Filtro de pesquisa
        const searchInput = document.getElementById('available-moves-search');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                searchQuery = e.target.value;
                applyFilters();
            });
        }

        // Filtros por botões
        document.querySelectorAll('.btn-filter').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                applyFilters();
            });
        });
    }

function t(key) {
    const msgs = MESSAGES[locale] || MESSAGES.pt_BR;
    return msgs[key] !== undefined ? msgs[key] : key;
}

function translateType(type) {
    const msgs = MESSAGES[locale] || MESSAGES.pt_BR;
    const typeLower = type.toLowerCase();
    return msgs.typeTranslations[typeLower] || (type.charAt(0).toUpperCase() + type.slice(1));
}

function formatMoveName(name) {
    return name.split('-').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function getTypeIconUrl(type) {
    const id = TYPE_IDS[type] || 1;
    return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-ix/scarlet-violet/small/${id}.png`;
}

async function getMoveData(moveName) {
    const cachedType = localStorage.getItem(cacheKeyPrefix + moveName);
    const cachedPower = localStorage.getItem(cacheKeyPrefix + moveName + '_power');
    const cachedCategory = localStorage.getItem(cacheKeyPrefix + moveName + '_cat');
    const cachedDescription = localStorage.getItem(cacheKeyPrefix + moveName + '_desc');

    if (cachedType && cachedPower !== null && cachedCategory && cachedDescription) {
        return { type: cachedType, power: cachedPower, category: cachedCategory, description: cachedDescription };
    }
    try {
        const response = await fetch(`https://pokeapi.co/api/v2/move/${moveName}`);
        if (!response.ok) throw new Error('Falha na resposta da API');

        const data = await response.json();
        const type = data.type.name;
        const power = data.power ? String(data.power) : '—';
        const category = data.damage_class ? data.damage_class.name : 'status';

        let description = '';
        if (data.flavor_text_entries && data.flavor_text_entries.length > 0) {
            const enEntry = data.flavor_text_entries.find(e => e.language.name === 'en');
            if (enEntry) {
                description = enEntry.flavor_text.replace(/\n|\f|\r/g, ' ');
            }
        }
        if (!description && data.effect_entries && data.effect_entries.length > 0) {
            description = data.effect_entries[0].short_effect || data.effect_entries[0].effect;
        }

        localStorage.setItem(cacheKeyPrefix + moveName, type);
        localStorage.setItem(cacheKeyPrefix + moveName + '_power', power);
        localStorage.setItem(cacheKeyPrefix + moveName + '_cat', category);
        localStorage.setItem(cacheKeyPrefix + moveName + '_desc', description);
        return { type, power, category, description };
    } catch (e) {
        console.error('Erro ao buscar dados para ' + moveName, e);
        return { type: 'normal', power: '—', category: 'status', description: '' };
    }
}

async function getMoveType(moveName) {
    const data = await getMoveData(moveName);
    return data.type;
}

function updateMoveCardType(card, type) {
    const badgeSpan = card.querySelector('.type-badge-sm');
    if (badgeSpan) {
        badgeSpan.className = `type-badge-sm type-badge-pr type-badge-${type}`;
        const typeIconUrl = getTypeIconUrl(type);
        badgeSpan.innerHTML = `<img src="${typeIconUrl}" alt="${type}" class="move-type-icon" style="width:17px; height:17px;"> ${translateType(type)}`;
    }
}

window.showMoveTooltip = async function (event, moveName) {
    const title = formatMoveName(moveName);
    let loadingBody = `
        <div class="tooltip-row"><span class="tooltip-label">${t('typeLabel')}</span><span class="tooltip-value"><span style="opacity:.5">...</span></span></div>
        <div class="tooltip-row"><span class="tooltip-label">${t('powerLabel')}</span><span class="tooltip-value">...</span></div>
        <div class="tooltip-row"><span class="tooltip-label">${t('categoryLabel')}</span><span class="tooltip-value">...</span></div>
        <div class="tooltip-row tooltip-desc-row"><span class="tooltip-label">${t('descriptionLabel')}</span><span class="tooltip-value">...</span></div>
    `;
    showGenericTooltip(event, title, loadingBody);

    const moveData = await getMoveData(moveName);
    const typeIconUrl = getTypeIconUrl(moveData.type);
    const typeName = translateType(moveData.type);
    const categoryMap = {
        'physical': { label: t('physicalLabel'), icon: 'fa-hand-fist' },
        'special': { label: t('specialLabel'), icon: 'fa-bolt' },
        'status': { label: t('statusLabel'), icon: 'fa-circle-dot' }
    };
    const cat = categoryMap[moveData.category] || categoryMap['status'];

    let finalBody = `
        <div class="tooltip-row"><span class="tooltip-label">${t('typeLabel')}</span><span class="tooltip-value"><img src="${typeIconUrl}" class="move-type-icon" style="width:14px;height:14px"> ${typeName}</span></div>
        <div class="tooltip-row"><span class="tooltip-label">${t('powerLabel')}</span><span class="tooltip-value">${moveData.power}</span></div>
        <div class="tooltip-row"><span class="tooltip-label">${t('categoryLabel')}</span><span class="tooltip-value"><i class="fa-solid ${cat.icon}" style="font-size:0.75rem"></i> ${cat.label}</span></div>
        <div class="tooltip-row tooltip-desc-row"><span class="tooltip-label">${t('descriptionLabel')}</span><span class="tooltip-value">${moveData.description || t('noDescription')}</span></div>
    `;
    showGenericTooltip(event, title, finalBody);
};

window.hideMoveTooltip = function () {
    hideGenericTooltip();
};

async function loadAllMoveTypes() {
    const cards = document.querySelectorAll('.available-move-card');
    const chunkSize = 4;
    for (let i = 0; i < cards.length; i += chunkSize) {
        const chunk = Array.from(cards).slice(i, i + chunkSize);
        let fetchPerformed = false;
        await Promise.all(chunk.map(async (card) => {
            const moveName = card.dataset.moveName;
            if (localStorage.getItem(cacheKeyPrefix + moveName) === null) fetchPerformed = true;
            const type = await getMoveType(moveName);
            updateMoveCardType(card, type);
        }));
        if (fetchPerformed) await new Promise(resolve => setTimeout(resolve, 300));
    }
}

function updateUI() {
    const slotsContainer = document.getElementById('selected-slots-list');
    if (!slotsContainer) return;
    slotsContainer.innerHTML = '';

    for (let i = 0; i < MAX_MOVES; i++) {
        const moveName = selectedMoves[i];
        const hiddenInput = document.getElementById('hidden-move-' + (i + 1));
        if (hiddenInput) hiddenInput.value = moveName || '';

        const card = document.createElement('div');
        if (!moveName) {
            card.className = 'selected-slot-card empty';
            card.innerHTML = `<span class="selected-slot-number">#${i + 1}</span>`;
        } else {
            const availableCard = document.querySelector(`.available-move-card[data-move-name="${moveName}"]`);
            const learnMethod = availableCard ? availableCard.dataset.learnMethod : 'base';
            const type = localStorage.getItem(cacheKeyPrefix + moveName) || 'normal';
            const learnLabel = learnMethod === 'TM' ? 'TM' : 'Lvl';
            const learnClass = learnMethod === 'TM' ? 'method-tm' : 'method-base';
            card.className = `selected-slot-card border-type-${type}`;
            card.innerHTML = `
                <span class="selected-slot-number">#${i + 1}</span>
                <span class="selected-slot-name" title="${formatMoveName(moveName)}">${formatMoveName(moveName)}</span>
                <div class="move-badges">
                    <span class="learn-method-badge ${learnClass}">${learnLabel}</span>
                    <span class="type-badge-sm type-badge-${type}"><img src="${getTypeIconUrl(type)}" alt="${type}" class="move-type-icon"></span>
                </div>
                <button type="button" class="remove-slot-btn" onclick="removeMoveFromSlot(${i})" title="${t('removeMoveTitle')}"><i class="fa-solid fa-xmark"></i></button>
            `;
        }
        slotsContainer.appendChild(card);
    }

    document.querySelectorAll('.available-move-card').forEach(card => {
        const moveName = card.dataset.moveName;
        card.classList.toggle('selected', selectedMoves.includes(moveName));
    });
    updateCounter();
}

window.addMoveToFirstEmptySlot = function (moveName) {
    if (selectedMoves.includes(moveName)) return;
    const emptyIndex = selectedMoves.indexOf(null);
    if (emptyIndex === -1) return alert(t('maxMovesAlert').replace('{max}', MAX_MOVES));
    selectedMoves[emptyIndex] = moveName;
    updateUI();
};

window.removeMoveFromSlot = function (index) {
    let activeMoves = selectedMoves.filter(m => m !== null);
    activeMoves.splice(index, 1);
    for (let i = 0; i < MAX_MOVES; i++) selectedMoves[i] = activeMoves[i] || null;
    updateUI();
};

function applyFilters() {
    const query = searchQuery.toLowerCase().replace(/[^a-z0-9]/g, '');
    document.querySelectorAll('.available-move-card').forEach(card => {
        const moveName = card.dataset.moveName.toLowerCase().replace(/[^a-z0-9]/g, '');
        const displayName = card.querySelector('.move-name').textContent.toLowerCase().replace(/[^a-z0-9]/g, '');
        const learnMethod = card.dataset.learnMethod.toLowerCase();
        const matchesSearch = moveName.includes(query) || displayName.includes(query);

        let matchesFilter = true;
        if (currentFilter === 'lvl') matchesFilter = (learnMethod === 'base' || learnMethod === 'both');
        else if (currentFilter === 'tm') matchesFilter = (learnMethod === 'tm' || learnMethod === 'both');

        card.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
    });
}

function updateCounter() {
    const count = selectedMoves.filter(m => m !== null).length;
    const counter = document.getElementById('slots-counter');
    if (counter) counter.textContent = `(${count} / ${MAX_MOVES})`;
}
})();