function initTeamBuilder() {
	const STORAGE_KEY = 'poke_team_builder_v1';
	const teamSlotData = {}; // Cache de dados completos retornados da API por slot (1-6)

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
			const container = document.querySelector('.team-slots-list');
			if (!container) return;

			const originalText = this.innerHTML;
			this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando Imagem...';
			this.disabled = true;

			try {
				await new Promise(resolve => setTimeout(resolve, 200));
				const canvas = await html2canvas(container, {
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
				this.innerHTML = originalText;
				this.disabled = false;
			}
		});
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

			if (spriteEl) {
				spriteEl.src = data.pokemon.sprite;
				spriteEl.style.opacity = '1';
			}
			if (searchInput) searchInput.value = data.pokemon.display_name;

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
				moves: customOverrides?.moves || data.baseMoves || data.moves || []
			};

			applyBuildAndMovesToSlot(slotId, buildSource);

			const roleCustom = document.getElementById(`role-custom-slot-${slotId}`);
			if (roleCustom && buildSource.role) {
				roleCustom.value = buildSource.role;
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
			const moveInput = document.getElementById(`move-input-${slotId}-${mIdx}`);
			const typeBadge = document.getElementById(`move-type-badge-${slotId}-${mIdx}`);
			const statsRow = document.getElementById(`move-stats-${slotId}-${mIdx}`);

			if (typeof moveData === 'string') {
				if (moveInput) moveInput.value = moveData;
				if (typeBadge) typeBadge.style.display = 'none';
				if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
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
			} else {
				if (moveInput) moveInput.value = '';
				if (typeBadge) typeBadge.style.display = 'none';
				if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
			}
		}
	}

	function initRolePresets(slotId) {
		const rolePreset = document.getElementById(`role-preset-slot-${slotId}`);
		const roleCustom = document.getElementById(`role-custom-slot-${slotId}`);

		if (rolePreset && roleCustom) {
			rolePreset.addEventListener('change', function () {
				if (this.value) {
					if (roleCustom.value) {
						roleCustom.value = `${this.value} - ${roleCustom.value}`;
					} else {
						roleCustom.value = this.value;
					}
					saveStateToStorage();
				}
			});
		}
	}

	function initFieldWatchers(slotId) {
		const fields = [
			`item-slot-${slotId}`,
			`nature-slot-${slotId}`,
			`ability-slot-${slotId}`,
			`role-custom-slot-${slotId}`,
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
		const roleCustom = document.getElementById(`role-custom-slot-${slotId}`);
		const movesetPresetSelect = document.getElementById(`moveset-preset-slot-${slotId}`);

		if (spriteEl) {
			spriteEl.src = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/other/official-artwork/25.png';
			spriteEl.style.opacity = '0.3';
		}
		if (searchInput) searchInput.value = '';
		if (itemInput) itemInput.value = '';
		if (natureSelect) natureSelect.value = '';
		if (abilityInput) abilityInput.value = '';
		if (rolePreset) rolePreset.value = '';
		if (roleCustom) roleCustom.value = '';
		if (movesetPresetSelect) movesetPresetSelect.innerHTML = '<option value="base">-- Golpes Base --</option>';

		for (let mIdx = 1; mIdx <= 10; mIdx++) {
			const moveInput = document.getElementById(`move-input-${slotId}-${mIdx}`);
			const typeBadge = document.getElementById(`move-type-badge-${slotId}-${mIdx}`);
			const statsRow = document.getElementById(`move-stats-${slotId}-${mIdx}`);

			if (moveInput) moveInput.value = '';
			if (typeBadge) typeBadge.style.display = 'none';
			if (statsRow) statsRow.innerHTML = '<span class="text-muted" style="font-size: 0.7rem;">—</span>';
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
			const roleCustom = document.getElementById(`role-custom-slot-${slotId}`);
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
				role: roleCustom ? roleCustom.value.trim() : '',
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
