document.addEventListener('DOMContentLoaded', function () {
    const shinyToggle = document.getElementById('shiny-toggle');
    if (!shinyToggle) return;

    // Elementos da img
    const mainArtwork = document.getElementById('pokemon-artwork');

    // Elementos de stats
    const statValues = document.querySelectorAll('.stat-value');
    const statBars = document.querySelectorAll('.stat-bar-fill');
    const MAX_STAT = 150;

    shinyToggle.addEventListener('click', function () {
        const isShiny = shinyToggle.classList.toggle('active');
        shinyToggle.style.opacity = isShiny ? '1' : '0.35';

        // troca sprite
        if (mainArtwork) {
            mainArtwork.src = isShiny ? mainArtwork.dataset.shiny : mainArtwork.dataset.normal;
        }

        // Analisar base stats com buff de 50%
        statValues.forEach((span, index) => {
            const baseVal = parseInt(span.dataset.baseVal, 10);
            const newVal = isShiny ? Math.round(baseVal * 1.5) : baseVal;
            span.textContent = newVal;

            // Recupera a barra base e a barra do buff
            const bar = statBars[index];
            if (bar) {
                const container = bar.parentElement;
                const buffBar = container.querySelector('.stat-bar-buff');

                if (isShiny) {
                    bar.style.width = bar.dataset.basePercentage + '%';
                    const basePercentage = parseFloat(bar.dataset.basePercentage);
                    let buffPercentage = ((newVal - baseVal) / MAX_STAT) * 100;

                    // Se a soma ultrapassar 100%, limita o buff
                    if (basePercentage + buffPercentage > 100) {
                        buffPercentage = 100 - basePercentage;
                    }
                    if (buffBar) buffBar.style.width = buffPercentage + '%';
                } else {
                    bar.style.width = bar.dataset.basePercentage + '%';
                    if (buffBar) buffBar.style.width = '0%';
                }
            }
        });
    });
});

// AJAX catch/release Pokémon
window.toggleCatchState = async function (btn) {
    const pokemonName = btn.dataset.pokemonName;
    const icon = document.getElementById('catch-pokeball-icon');
    const text = document.getElementById('catch-btn-text');

    icon.style.animation = 'pokeballShake 0.6s ease';
    const formData = new FormData();
    formData.append('name', pokemonName);

    const dataEl = document.getElementById('pokemon-detail-data');
    const toggleUrl = dataEl ? dataEl.dataset.urlToggleCatch : '';

    try {
        const response = await fetch(toggleUrl, { method: 'POST', body: formData });
        const data = await response.json();

        setTimeout(() => { icon.style.animation = ''; }, 600);

        if (data.success) {
            if (data.caught) {
                btn.classList.add('caught');
                btn.style.color = '#10b981';
                btn.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                icon.style.filter = 'none';
                text.textContent = 'Capturado!';
                btn.title = 'Na sua Pokedex (Soltar?)';
                if (data.pattern) alert(`Você capturou o Vivillon! Padrão desbloqueado: ${data.pattern.toUpperCase()}!`);
            } else {
                btn.classList.remove('caught');
                btn.style.color = 'var(--text-secondary)';
                btn.style.borderColor = 'var(--border-color)';
                icon.style.filter = 'grayscale(1) opacity(0.6)';
                text.textContent = 'Capturar';
                btn.title = 'Capturar Pokémon!';
            }
        } else alert(data.error || 'Erro ao capturar Pokémon.');
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao capturar Pokémon.');
        icon.style.animation = '';
    }
};