document.addEventListener('DOMContentLoaded', function () {
	const filterButtons = document.querySelectorAll('.btn-filter-move');
	const counter = document.getElementById('results-counter');
	const noResultsBox = document.getElementById('no-filtered-results');
	const noResultsText = document.getElementById('no-filtered-results-text');
	const grid = document.getElementById('pokemon-results-grid');

	if (!filterButtons.length) return;

	function applyFilter(filterType) {
		const cards = document.querySelectorAll('.result-pokemon-card');
		let visibleCount = 0;

		cards.forEach(card => {
			const isBase = card.getAttribute('data-is-base') === 'true';
			const hasTm = card.getAttribute('data-has-tm') === 'true';

			const baseBadge = card.querySelector('[data-badge-type="base"]');
			const tmBadge = card.querySelector('[data-badge-type="tm"]');

			let show = false;
			if (filterType === 'tm') {
				show = hasTm;
				if (tmBadge) tmBadge.style.display = 'inline-flex';
				if (baseBadge) baseBadge.style.display = 'none';
			} else {
				show = isBase;
				if (baseBadge) baseBadge.style.display = 'inline-flex';
				if (tmBadge) tmBadge.style.display = 'none';
			}

			if (show) {
				card.classList.remove('filtered-out');
				visibleCount++;
			} else {
				card.classList.add('filtered-out');
			}
		});

		if (counter) {
			counter.textContent = '(' + visibleCount + ')';
		}

		if (visibleCount === 0) {
			if (grid) grid.style.display = 'none';
			if (noResultsBox) {
				noResultsBox.style.display = 'block';
				if (filterType === 'tm') {
					noResultsText.textContent = noResultsBox.dataset.msgTm || 'Nenhum Pokémon nesta busca aprende este golpe por TM.';
				} else {
					noResultsText.textContent = noResultsBox.dataset.msgBase || 'Nenhum Pokémon nesta busca aprende este golpe como Golpe Base (Base Move).';
				}
			}
		} else {
			if (grid) grid.style.display = 'grid';
			if (noResultsBox) noResultsBox.style.display = 'none';
		}
	}

	filterButtons.forEach(btn => {
		btn.addEventListener('click', function () {
			filterButtons.forEach(b => b.classList.remove('active'));
			this.classList.add('active');
			const filterType = this.getAttribute('data-filter');
			applyFilter(filterType);

			const url = new URL(window.location);
			if (filterType === 'base') {
				url.searchParams.delete('filter');
			} else {
				url.searchParams.set('filter', filterType);
			}
			window.history.replaceState({}, '', url);
		});
	});

	const activeBtn = document.querySelector('.btn-filter-move.active');
	const initialFilter = activeBtn ? activeBtn.getAttribute('data-filter') : 'base';
	applyFilter(initialFilter);
});
