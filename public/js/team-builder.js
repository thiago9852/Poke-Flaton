function initTeamBuilder() {
	const STORAGE_KEY = 'poke_team_builder_v1';
	const teamSlotData = {}; // Cache de dados completos retornados da API por slot (1-6)

	const TYPE_COLORS = {
		normal: '#9ca3af', fire: '#ef4444', water: '#3b82f6', grass: '#10b981',
		electric: '#f59e0b', ice: '#06b6d4', fighting: '#dc2626', poison: '#a855f7',
		ground: '#b45309', flying: '#6366f1', psychic: '#ec4899', bug: '#84cc16',
		rock: '#78350f', ghost: '#4f46e5', dragon: '#7c3aed', steel: '#4b5563',
		dark: '#374151', fairy: '#f472b6'
	};

	// IDs oficiais dos tipos na PokeAPI (usados para montar as URLs dos ícones de tipo)
	const TYPE_IDS = {
		normal: 1, fighting: 2, flying: 3, poison: 4, ground: 5, rock: 6,
		bug: 7, ghost: 8, steel: 9, fire: 10, water: 11, grass: 12,
		electric: 13, psychic: 14, ice: 15, dragon: 16, dark: 17, fairy: 18
	};

	function typeIconUrl(typeName) {
		const id = TYPE_IDS[typeName] || 1;
		return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-viii/sword-shield/small/${id}.png`;
	}

	function itemIconUrl(itemName) {
		if (!itemName) return '';
		const slug = itemName.toLowerCase().trim().replace(/\s+/g, '-');
		return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/${slug}.png`;
	}

	function slugifyMoveName(name) {
		return (name || '').toLowerCase().trim().replace(/\s+/g, '-');
	}

	// Reconstrói golpes salvos (strings simples do LocalStorage) usando os golpes
	// detalhados (tipo/poder/precisão) já conhecidos para este Pokémon, evitando que
	// os golpes voltem "crus" (só o nome) depois de recarregar a página.
	function enrichSavedMoves(moves, data) {
		if (!Array.isArray(moves) || !data) return moves;

		const dict = {};
		(data.baseMoves || []).forEach(m => {
			if (m && m.name) dict[slugifyMoveName(m.name)] = m;
		});
		(data.movesets || []).forEach(ms => {
			(ms.moves || []).forEach(m => {
				if (m && m.name) dict[slugifyMoveName(m.name)] = m;
			});
		});

		return moves.map(mv => {
			if (typeof mv !== 'string') return mv; // já é um objeto detalhado
			const enriched = dict[slugifyMoveName(mv)];
			return enriched || mv;
		});
	}

	// Inicializa os 6 slots
	for (let i = 1; i <= 6; i++) {
		initSlotSearch(i);
		initRolePresets(i);
		initMovesetPresetSelector(i);
		initFieldWatchers(i);
	}

	// Restaura time salvo no LocalStorage se existir
	loadStateFromStorage();

	// Botões globais
	const btnClearTeam = document.getElementById('btn-clear-team');
	if (btnClearTeam) {
		btnClearTeam.addEventListener('click', function () {
			if (confirm('Deseja realmente limpar todos os 6 Pokémon do time?')) {
				for (let i = 1; i <= 6; i++) {
					clearSlot(i, false);
				}
				saveStateToStorage();
			}
		});
	}

	const btnCopyTeamText = document.getElementById('btn-copy-team-text');
	if (btnCopyTeamText) {
		btnCopyTeamText.addEventListener('click', function () {
			copyTeamAsText();
		});
	}

	const btnExportTeamImage = document.getElementById('btn-export-team-image');
	if (btnExportTeamImage) {
		btnExportTeamImage.addEventListener('click', async function () {
			const { wrapper, hasAny } = buildTeamExportNode();
			if (!hasAny) {
				alert('Seu time está vazio. Adicione pelo menos 1 Pokémon.');
				return;
			}

			const originalText = this.innerHTML;
			this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando Imagem...';
			this.disabled = true;

			wrapper.style.position = 'fixed';
			wrapper.style.top = '0';
			wrapper.style.left = '-99999px';
			wrapper.style.zIndex = '-1';
			document.body.appendChild(wrapper);

			try {
				await new Promise(resolve => setTimeout(resolve, 200));
				const canvas = await html2canvas(wrapper, {
					useCORS: true,
					allowTaint: true,
					scale: 2,
					backgroundColor: '#0f0f1b',
					logging: false
				});

				const link = document.createElement('a');
				link.download = 'meu-time-pokeflaton.png';
				link.href = canvas.toDataURL('image/png');
				link.click();
			} catch (e) {
				console.error("Erro ao gerar imagem do time:", e);
				alert("Não foi possível gerar a imagem. Tente novamente.");
			} finally {
				document.body.removeChild(wrapper);
				this.innerHTML = originalText;
				this.disabled = false;
			}
		});
	}

	// Coleta os golpes preenchidos de um slot (nome, tipo, poder e método: Nível ou TM)
	function getSlotMoves(slotId) {
		const data = teamSlotData[slotId];
		const learnMap = (data && data.pokemon && data.pokemon.moves_learn_info) || {};
		const moves = [];

		for (let mIdx = 1; mIdx <= 10; mIdx++) {
			const input = document.getElementById(`move-input-${slotId}-${mIdx}`);
			const val = input ? input.value.trim() : '';
			if (!val) continue;

			let type = 'normal';
			const badge = document.getElementById(`move-type-badge-${slotId}-${mIdx}`);
			if (badge && badge.style.display !== 'none') {
				const match = badge.className.match(/type-badge-(\w+)/);
				if (match) type = match[1];
			}

			let power = '—';
			const statsRow = document.getElementById(`move-stats-${slotId}-${mIdx}`);
			if (statsRow && statsRow.children.length >= 2) {
				power = statsRow.children[0].textContent.trim() || '—';
			}

			const slug = val.toLowerCase().replace(/\s+/g, '-');
			const learnInfo = learnMap[slug];
			let method = '—';
			if (learnInfo && learnInfo.learn_method) {
				if (learnInfo.learn_method === 'base') method = 'Nv.';
				else if (learnInfo.learn_method === 'TM') method = 'TM';
				else if (learnInfo.learn_method === 'both') method = 'Nv.';
			}

			moves.push({ name: val, type, power, method });
		}
		return moves;
	}

	// Monta o card compacto (função, sprite + tipos, ability/nature/item inline, e a lista de golpes) usado na exportação de imagem
	function buildTeamExportCard(slotId) {
		const searchInput = document.getElementById(`search-slot-${slotId}`);
		const pokemonName = searchInput ? searchInput.value.trim() : '';
		if (!pokemonName) return null;

		const data = teamSlotData[slotId];
		const spriteEl = document.getElementById(`sprite-slot-${slotId}`);
		const natureSelect = document.getElementById(`nature-slot-${slotId}`);
		const itemInput = document.getElementById(`item-slot-${slotId}`);
		const abilitySelect = document.getElementById(`ability-slot-${slotId}`);
		const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);

		const spriteSrc = spriteEl ? spriteEl.src : '';
		const types = (data && data.pokemon && Array.isArray(data.pokemon.types)) ? data.pokemon.types : [];
		const natureVal = natureSelect ? natureSelect.value : '';
		const itemVal = itemInput ? itemInput.value.trim() : '';
		const abilityVal = (abilitySelect && abilitySelect.selectedIndex > 0) ? abilitySelect.options[abilitySelect.selectedIndex].textContent : '';
		const roleVal = rolePreset ? rolePreset.value.trim() : '';
		const moves = getSlotMoves(slotId);

		const typeIconsHtml = types.map(t => `<img class="team-export-type-icon" src="${typeIconUrl(t)}" alt="${t}" crossorigin="anonymous">`).join('');

		const movesHtml = moves.length
			? moves.map(mv => `
				<div class="team-export-move">
					<img class="team-export-move-type-icon" src="${typeIconUrl(mv.type)}" alt="${mv.type}" crossorigin="anonymous">
					<span class="team-export-move-name">${mv.name}</span>
					<span class="team-export-move-power">${mv.power}</span>
					<span class="team-export-move-method">${mv.method}</span>
				</div>
			`).join('')
			: `<div class="team-export-move team-export-move-empty">— Sem golpes definidos —</div>`;

		const natureLabel = natureVal ? (natureVal.charAt(0).toUpperCase() + natureVal.slice(1)) : '';

		const itemValueHtml = itemVal
			? `<img class="team-export-item-icon" src="${itemIconUrl(itemVal)}" alt="${itemVal}" crossorigin="anonymous"><span class="team-export-item-text">${itemVal}</span>`
			: `<span class="team-export-stat-value-empty">— Nenhum —</span>`;

		const roleBarHtml = roleVal ? `<div class="team-export-role-bar">${roleVal}</div>` : '';

		const card = document.createElement('div');
		card.className = 'team-export-card';
		card.innerHTML = `
			${roleBarHtml}
			<div class="team-export-card-header">
				<div class="team-export-sprite-wrap">
					<img src="${spriteSrc}" class="team-export-sprite" crossorigin="anonymous">
				</div>
				<div class="team-export-header-info">
					<div class="team-export-name">${pokemonName}</div>
					<div class="team-export-type-icons">${typeIconsHtml}</div>
				</div>
			</div>
			<div class="team-export-stats-bar">
				<div class="team-export-stat-cell">
					<span class="team-export-stat-label">Ability</span>
					<span class="team-export-stat-value ${abilityVal ? '' : 'team-export-stat-value-empty'}">${abilityVal || '—'}</span>
				</div>
				<div class="team-export-stat-cell">
					<span class="team-export-stat-label"> Nature</span>
					<span class="team-export-stat-value ${natureLabel ? '' : 'team-export-stat-value-empty'}">${natureLabel || '—'}</span>
				</div>
				<div class="team-export-stat-cell">
					<span class="team-export-stat-label"> Item</span>
					<span class="team-export-stat-value team-export-item-value">${itemValueHtml}</span>
				</div>
			</div>
			<div class="team-export-moves">${movesHtml}</div>
		`;
		return card;
	}

	// Monta o nó completo (fora da tela) usado para gerar a imagem exportável do time
	function buildTeamExportNode() {
		const wrapper = document.createElement('div');
		wrapper.className = 'team-export-container';

		wrapper.innerHTML = `
			<div class="team-export-header">
				<div class="team-export-title">Meu Time</div>
			</div>
		`;

		const grid = document.createElement('div');
		grid.className = 'team-export-grid';

		for (let slotId = 1; slotId <= 6; slotId++) {
			const card = buildTeamExportCard(slotId);
			if (card) grid.appendChild(card);
		}

		wrapper.appendChild(grid);
		return { wrapper, hasAny: grid.children.length > 0 };
	}

	// Modal JSON
	const ioModal = document.getElementById('team-io-modal');
	const btnOpenIoModal = document.getElementById('btn-open-io-modal');
	const jsonArea = document.getElementById('team-json-area');
	const btnCopyModalJson = document.getElementById('btn-copy-modal-json');
	const btnImportModalJson = document.getElementById('btn-import-modal-json');

	if (btnOpenIoModal && ioModal && jsonArea) {
		btnOpenIoModal.addEventListener('click', function () {
			const teamJsonData = generateTeamJsonData();
			jsonArea.value = JSON.stringify(teamJsonData, null, 2);
			ioModal.style.display = 'flex';
		});
	}

	if (btnCopyModalJson && jsonArea) {
		btnCopyModalJson.addEventListener('click', function () {
			navigator.clipboard.writeText(jsonArea.value).then(() => {
				alert('JSON do time copiado para a área de transferência!');
			});
		});
	}

	if (btnImportModalJson && jsonArea) {
		btnImportModalJson.addEventListener('click', function () {
			try {
				const data = JSON.parse(jsonArea.value);
				if (Array.isArray(data)) {
					for (let i = 1; i <= 6; i++) {
						clearSlot(i, false);
					}
					data.slice(0, 6).forEach((pkData, idx) => {
						const slotId = idx + 1;
						if (pkData && pkData.name) {
							fetchAndPopulatePokemon(slotId, pkData.name, pkData);
						}
					});
					saveStateToStorage();
					closeIoModal();
				} else {
					alert('Formato de JSON inválido. Deve ser uma lista de Pokémon.');
				}
			} catch (e) {
				alert('Erro ao processar JSON: ' + e.message);
			}
		});
	}

	// Autocomplete para seleção de Pokémon no slot
	function initSlotSearch(slotId) {
		const searchInput = document.getElementById(`search-slot-${slotId}`);
		const autocompleteDropdown = document.getElementById(`autocomplete-slot-${slotId}`);

		if (!searchInput || !autocompleteDropdown) return;

		let timeoutId;
		searchInput.addEventListener('input', function () {
			clearTimeout(timeoutId);
			const query = this.value.trim();

			if (query.length < 2) {
				autocompleteDropdown.style.display = 'none';
				autocompleteDropdown.innerHTML = '';
				return;
			}

			timeoutId = setTimeout(async () => {
				try {
					const response = await fetch(`/api/pokemon/search?q=${encodeURIComponent(query)}`);
					const results = await response.json();

					autocompleteDropdown.innerHTML = '';

					if (Array.isArray(results) && results.length > 0) {
						results.forEach(poke => {
							const a = document.createElement('a');
							a.href = '#';
							a.className = 'autocomplete-item';
							a.innerHTML = `
								<img src="${poke.sprite}" alt="${poke.name}" style="width: 24px; height: 24px; object-fit: contain;">
								<div class="autocomplete-item-info">
									<span class="poke-name">${poke.name}</span>
								</div>
							`;

							a.addEventListener('click', function (e) {
								e.preventDefault();
								autocompleteDropdown.style.display = 'none';
								fetchAndPopulatePokemon(slotId, poke.name);
							});

							autocompleteDropdown.appendChild(a);
						});
						autocompleteDropdown.style.display = 'flex';
					} else {
						autocompleteDropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">Nenhum Pokémon encontrado.</div>';
						autocompleteDropdown.style.display = 'flex';
					}
				} catch (e) {
					console.error(`Erro ao buscar Pokémon para o slot ${slotId}:`, e);
				}
			}, 300);
		});

		document.addEventListener('click', function (e) {
			if (!searchInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
				autocompleteDropdown.style.display = 'none';
			}
		});
	}

	// Listener do Seletor de Movesets Criados
	function initMovesetPresetSelector(slotId) {
		const movesetPresetSelect = document.getElementById(`moveset-preset-slot-${slotId}`);
		if (movesetPresetSelect) {
			movesetPresetSelect.addEventListener('change', function () {
				const selectedVal = this.value;
				const data = teamSlotData[slotId];
				if (!data) return;

				if (selectedVal === 'base') {
					applyBuildAndMovesToSlot(slotId, {
						nature: data.nature,
						heldItem: data.heldItem,
						ability: data.ability,
						moves: data.baseMoves || data.moves
					});
				} else {
					const msId = parseInt(selectedVal, 10);
					const ms = (data.movesets || []).find(m => m.id === msId);
					if (ms) {
						applyBuildAndMovesToSlot(slotId, {
							nature: ms.nature,
							heldItem: ms.heldItem,
							ability: ms.ability,
							moves: ms.moves
						});
					}
				}
				saveStateToStorage();
			});
		}
	}

	// Busca dados completos do Pokémon e preenche o slot
	async function fetchAndPopulatePokemon(slotId, pokemonName, customOverrides = null) {
		try {
			const response = await fetch(`/api/team/pokemon-data/${encodeURIComponent(pokemonName)}`);
			if (!response.ok) return;
			const data = await response.json();

			teamSlotData[slotId] = data; // Armazena em cache

			const spriteEl = document.getElementById(`sprite-slot-${slotId}`);
			const searchInput = document.getElementById(`search-slot-${slotId}`);
			const movesetPresetSelect = document.getElementById(`moveset-preset-slot-${slotId}`);
			const abilitySelect = document.getElementById(`ability-slot-${slotId}`);
			const typesContainer = document.getElementById(`types-slot-${slotId}`);
			const slotCard = document.getElementById(`team-slot-card-${slotId}`);

			if (spriteEl) {
				spriteEl.src = data.pokemon.sprite;
				spriteEl.style.opacity = '1';
			}
			if (searchInput) searchInput.value = data.pokemon.display_name;

			// Exibe os tipos do Pokémon e tinge o card com a cor do tipo principal
			const types = (data.pokemon && Array.isArray(data.pokemon.types)) ? data.pokemon.types : [];
			if (typesContainer) {
				typesContainer.innerHTML = types.map(t => `<span class="type-badge type-badge-${t}">${t.toUpperCase()}</span>`).join('');
			}
			if (slotCard) {
				slotCard.style.setProperty('--slot-type-color', TYPE_COLORS[types[0]] || 'var(--color-primary)');
			}

			// Popula o select de Habilidade com as opções que este Pokémon pode aprender
			if (abilitySelect) {
				const abilities = (data.pokemon && Array.isArray(data.pokemon.abilities)) ? data.pokemon.abilities : [];
				abilitySelect.innerHTML = '<option value="">-- Selecione --</option>' +
					abilities.map(a => `<option value="${a}">${a.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>`).join('');
			}

			// Popula o dropdown de movesets criados
			if (movesetPresetSelect) {
				movesetPresetSelect.innerHTML = '<option value="base">-- Golpes Base --</option>';
				if (Array.isArray(data.movesets) && data.movesets.length > 0) {
					data.movesets.forEach(ms => {
						const opt = document.createElement('option');
						opt.value = ms.id;
						opt.textContent = ms.title;
						if (customOverrides && customOverrides.movesetId == ms.id) {
							opt.selected = true;
						}
						movesetPresetSelect.appendChild(opt);
					});
				}
				if (!customOverrides || !customOverrides.movesetId) {
					movesetPresetSelect.value = 'base';
				}
			}

			// Preenche build & golpes (usa customOverrides se existirem ou o padrão Golpes Base)
			const buildSource = {
				nature: customOverrides?.nature || data.nature || '',
				heldItem: customOverrides?.heldItem || data.heldItem || '',
				ability: customOverrides?.ability || data.ability || '',
				role: customOverrides?.role || '',
				moves: enrichSavedMoves(customOverrides?.moves || data.baseMoves || data.moves || [], data)
			};

			applyBuildAndMovesToSlot(slotId, buildSource);

			const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);
			if (rolePreset && buildSource.role) {
				rolePreset.value = buildSource.role;
			}

			saveStateToStorage();
		} catch (e) {
			console.error(`Erro ao carregar dados do Pokémon no slot ${slotId}:`, e);
		}
	}

	function applyBuildAndMovesToSlot(slotId, build) {
		const itemInput = document.getElementById(`item-slot-${slotId}`);
		const natureSelect = document.getElementById(`nature-slot-${slotId}`);
		const abilityInput = document.getElementById(`ability-slot-${slotId}`);

		if (itemInput) itemInput.value = build.heldItem || '';
		if (natureSelect) natureSelect.value = build.nature || '';
		if (abilityInput) abilityInput.value = build.ability || '';

		const moveList = build.moves || [];
		for (let mIdx = 1; mIdx <= 10; mIdx++) {
			const moveData = moveList[mIdx - 1];
			const moveCard = document.getElementById(`move-card-${slotId}-${mIdx}`);
			const moveInput = document.getElementById(`move-input-${slotId}-${mIdx}`);
			const typeBadge = document.getElementById(`move-type-badge-${slotId}-${mIdx}`);
			const statsRow = document.getElementById(`move-stats-${slotId}-${mIdx}`);

			if (typeof moveData === 'string') {
				if (moveInput) moveInput.value = moveData;
				if (typeBadge) typeBadge.style.display = 'none';
				if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
				if (moveCard) moveCard.style.removeProperty('--move-type-color');
			} else if (moveData && moveData.name) {
				if (moveInput) moveInput.value = moveData.name;
				if (typeBadge) {
					typeBadge.className = `type-badge type-badge-${moveData.type}`;
					typeBadge.textContent = moveData.type.toUpperCase();
					typeBadge.style.display = 'inline-flex';
				}
				if (statsRow) {
					statsRow.innerHTML = `
						<span><i class="fa-solid fa-fire"></i> ${moveData.power}</span>
						<span><i class="fa-solid fa-bullseye"></i> ${moveData.accuracy}</span>
					`;
				}
				if (moveCard) moveCard.style.setProperty('--move-type-color', TYPE_COLORS[moveData.type] || '');
			} else {
				if (moveInput) moveInput.value = '';
				if (typeBadge) typeBadge.style.display = 'none';
				if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
				if (moveCard) moveCard.style.removeProperty('--move-type-color');
			}
		}
	}

	// O próprio select de Tag/Função é a fonte da informação (não existe mais um campo de texto livre separado)
	function initRolePresets(slotId) {
		const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);
		if (rolePreset) {
			rolePreset.addEventListener('change', function () {
				saveStateToStorage();
			});
		}
	}

	function initFieldWatchers(slotId) {
		const fields = [
			`item-slot-${slotId}`,
			`nature-slot-${slotId}`,
			`ability-slot-${slotId}`,
			`role-preset-slot-${slotId}`,
		];

		for (let mIdx = 1; mIdx <= 10; mIdx++) {
			fields.push(`move-input-${slotId}-${mIdx}`);
		}

		fields.forEach(id => {
			const el = document.getElementById(id);
			if (el) {
				el.addEventListener('change', saveStateToStorage);
				el.addEventListener('input', saveStateToStorage);
			}
		});
	}

	window.clearSlot = function (slotId, autoSave = true) {
		delete teamSlotData[slotId];

		const spriteEl = document.getElementById(`sprite-slot-${slotId}`);
		const searchInput = document.getElementById(`search-slot-${slotId}`);
		const itemInput = document.getElementById(`item-slot-${slotId}`);
		const natureSelect = document.getElementById(`nature-slot-${slotId}`);
		const abilityInput = document.getElementById(`ability-slot-${slotId}`);
		const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);
		const movesetPresetSelect = document.getElementById(`moveset-preset-slot-${slotId}`);
		const typesContainer = document.getElementById(`types-slot-${slotId}`);
		const slotCard = document.getElementById(`team-slot-card-${slotId}`);

		if (spriteEl) {
			spriteEl.src = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png';
			spriteEl.style.opacity = '0.3';
		}
		if (searchInput) searchInput.value = '';
		if (itemInput) itemInput.value = '';
		if (natureSelect) natureSelect.value = '';
		if (abilityInput) abilityInput.innerHTML = '<option value="">-- Selecione --</option>';
		if (rolePreset) rolePreset.value = '';
		if (movesetPresetSelect) movesetPresetSelect.innerHTML = '<option value="base">-- Golpes Base --</option>';
		if (typesContainer) typesContainer.innerHTML = '';
		if (slotCard) slotCard.style.removeProperty('--slot-type-color');

		for (let mIdx = 1; mIdx <= 10; mIdx++) {
			const moveCard = document.getElementById(`move-card-${slotId}-${mIdx}`);
			const moveInput = document.getElementById(`move-input-${slotId}-${mIdx}`);
			const typeBadge = document.getElementById(`move-type-badge-${slotId}-${mIdx}`);
			const statsRow = document.getElementById(`move-stats-${slotId}-${mIdx}`);

			if (moveInput) moveInput.value = '';
			if (typeBadge) typeBadge.style.display = 'none';
			if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
			if (moveCard) moveCard.style.removeProperty('--move-type-color');
		}

		if (autoSave) saveStateToStorage();
	};

	function saveStateToStorage() {
		const teamData = generateTeamJsonData();
		localStorage.setItem(STORAGE_KEY, JSON.stringify(teamData));
	}

	function loadStateFromStorage() {
		try {
			const raw = localStorage.getItem(STORAGE_KEY);
			if (!raw) return;
			const data = JSON.parse(raw);
			if (Array.isArray(data)) {
				data.slice(0, 6).forEach((pkData, idx) => {
					const slotId = idx + 1;
					if (pkData && pkData.name) {
						fetchAndPopulatePokemon(slotId, pkData.name, pkData);
					}
				});
			}
		} catch (e) {
			console.error("Erro ao carregar time do LocalStorage:", e);
		}
	}

	function generateTeamJsonData() {
		const team = [];
		for (let slotId = 1; slotId <= 6; slotId++) {
			const searchInput = document.getElementById(`search-slot-${slotId}`);
			const itemInput = document.getElementById(`item-slot-${slotId}`);
			const natureSelect = document.getElementById(`nature-slot-${slotId}`);
			const abilityInput = document.getElementById(`ability-slot-${slotId}`);
			const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);
			const movesetPresetSelect = document.getElementById(`moveset-preset-slot-${slotId}`);

			const pokemonName = searchInput ? searchInput.value.trim() : '';
			if (!pokemonName) continue;

			const moves = [];
			for (let mIdx = 1; mIdx <= 10; mIdx++) {
				const mInput = document.getElementById(`move-input-${slotId}-${mIdx}`);
				const val = mInput ? mInput.value.trim() : '';
				if (val) moves.push(val);
			}

			team.push({
				slot: slotId,
				name: pokemonName.toLowerCase().replace(/\s+/g, '-'),
				displayName: pokemonName,
				movesetId: movesetPresetSelect ? movesetPresetSelect.value : 'base',
				heldItem: itemInput ? itemInput.value.trim() : '',
				nature: natureSelect ? natureSelect.value : '',
				ability: abilityInput ? abilityInput.value.trim() : '',
				role: rolePreset ? rolePreset.value.trim() : '',
				moves: moves,
			});
		}
		return team;
	}

	function copyTeamAsText() {
		const team = generateTeamJsonData();
		if (!team.length) {
			alert('Seu time está vazio. Adicione pelo menos 1 Pokémon.');
			return;
		}

		let text = '=== MEU TIME - POKEFLATON ===\n\n';
		team.forEach((pk, idx) => {
			text += `#${idx + 1} ${pk.displayName.toUpperCase()}\n`;
			if (pk.role) text += `Função/Tag: ${pk.role}\n`;
			if (pk.heldItem) text += `Item: ${pk.heldItem}\n`;
			if (pk.nature) text += `Nature: ${pk.nature}\n`;
			if (pk.ability) text += `Habilidade: ${pk.ability}\n`;
			if (pk.moves.length > 0) {
				text += `Golpes (1-10): ${pk.moves.join(', ')}\n`;
			}
			text += '\n';
		});

		navigator.clipboard.writeText(text).then(() => {
			alert('Resumo do time copiado para a área de transferência!');
		});
	}

	window.closeIoModal = function () {
		if (ioModal) ioModal.style.display = 'none';
	};
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initTeamBuilder);
} else {
	initTeamBuilder();
}
document.addEventListener('turbo:load', initTeamBuilder);

