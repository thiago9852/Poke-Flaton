document.addEventListener('DOMContentLoaded', function () {
	// ==========================================
	// AUTOCOMPLETE DA BUSCA DE GOLPES
	// ==========================================
	const moveInput = document.getElementById('move-search-input');
	const autocompleteResults = document.getElementById('move-autocomplete-results');

	if (moveInput && autocompleteResults) {
		const apiUrl = moveInput.dataset.autocompleteUrl || '/api/move/search';
		let timeoutId;
		let selectedIndex = -1;

		moveInput.addEventListener('input', function () {
			clearTimeout(timeoutId);
			const query = this.value.trim();
			selectedIndex = -1;

			if (query.length < 2) {
				autocompleteResults.style.display = 'none';
				autocompleteResults.innerHTML = '';
				return;
			}

			timeoutId = setTimeout(async () => {
				try {
					const response = await fetch(`${apiUrl}?q=${encodeURIComponent(query)}`);
					const data = await response.json();

					autocompleteResults.innerHTML = '';

					if (Array.isArray(data) && data.length > 0) {
						data.forEach((move, idx) => {
							const a = document.createElement('a');
							a.href = move.url;
							a.className = 'autocomplete-item';
							a.setAttribute('data-index', idx);
							a.style.justifyContent = 'center';
							a.innerHTML = `
								<div class="autocomplete-item-info" style="text-align: center; width: 100%;">
									<span class="poke-name" style="font-weight: 600;">${move.name}</span>
								</div>
							`;

							a.addEventListener('click', function (e) {
								e.preventDefault();
								moveInput.value = move.slug;
								autocompleteResults.style.display = 'none';
								window.location.href = move.url;
							});

							autocompleteResults.appendChild(a);
						});
						autocompleteResults.style.display = 'flex';
					} else {
						autocompleteResults.innerHTML = '<div style="padding: 14px; text-align: center; color: var(--text-muted); font-size: 0.9rem;">Nenhum golpe encontrado.</div>';
						autocompleteResults.style.display = 'flex';
					}
				} catch (e) {
					console.error("Erro ao buscar sugestões de golpes:", e);
				}
			}, 250);
		});

		moveInput.addEventListener('keydown', function (e) {
			const items = autocompleteResults.querySelectorAll('.autocomplete-item');
			if (!items.length || autocompleteResults.style.display === 'none') return;

			if (e.key === 'ArrowDown') {
				e.preventDefault();
				selectedIndex = (selectedIndex + 1) % items.length;
				updateActiveItem(items);
			} else if (e.key === 'ArrowUp') {
				e.preventDefault();
				selectedIndex = (selectedIndex - 1 + items.length) % items.length;
				updateActiveItem(items);
			} else if (e.key === 'Enter' && selectedIndex >= 0) {
				e.preventDefault();
				items[selectedIndex].click();
			}
		});

		function updateActiveItem(items) {
			items.forEach((item, idx) => {
				if (idx === selectedIndex) {
					item.classList.add('active');
					item.scrollIntoView({ block: 'nearest' });
				} else {
					item.classList.remove('active');
				}
			});
		}

		moveInput.addEventListener('focus', function () {
			if (this.value.trim().length >= 2 && autocompleteResults.children.length > 0) {
				autocompleteResults.style.display = 'flex';
			}
		});

		document.addEventListener('click', function (e) {
			if (!moveInput.contains(e.target) && !autocompleteResults.contains(e.target)) {
				autocompleteResults.style.display = 'none';
			}
		});
	}
});
