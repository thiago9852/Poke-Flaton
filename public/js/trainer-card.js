(() => {
// Função para recuperar as URLs injetadas pelo Twig no DOM
function getTcUrl(key) {
    const dataEl = document.getElementById('trainer-card-data');
    return dataEl ? dataEl.dataset[key] : '';
}

window.filterTms = function () {
    const query = document.getElementById('tm-search-input').value.toLowerCase().trim();
    document.querySelectorAll('.tm-disc-card').forEach(card => {
        const item = card.dataset.item.toLowerCase();
        const move = card.dataset.move.toLowerCase();
        const moveDisplay = card.querySelector('.tm-move-name').textContent.toLowerCase();
        if (item.includes(query) || move.includes(query) || moveDisplay.includes(query)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
};

window.toggleTmOwnership = async function (element, moveName) {
    const formData = new FormData();
    formData.append('move', moveName);
    try {
        const response = await fetch(getTcUrl('urlTmToggle'), { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            element.classList.toggle('active', data.unlocked);
            const collBadge = document.getElementById('tm-count-badge');
            if (collBadge) collBadge.textContent = data.count;
        } else {
            alert(data.error || 'Erro ao atualizar TM.');
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão com o servidor.');
    }
};

window.releasePokemon = async function (pokemonName) {
    if (!confirm(`Você tem certeza de que deseja soltar o seu ${pokemonName.toUpperCase()}?`)) return;
    const formData = new FormData();
    formData.append('name', pokemonName);
    try {
        const response = await fetch(getTcUrl('urlPokemonToggleCatch'), { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success && !data.caught) {
            const card = document.getElementById(`caught-card-${pokemonName}`);
            if (card) card.remove();
            document.getElementById('pokedex-count-badge').textContent = data.count;
            if (data.count === 0) {
                document.querySelector('.pokedex-list-container').innerHTML = `
                    <div class="tms-header"><h2>Minha Pokedéx de Capturas</h2><p>Veja todos os Pokémons que você já capturou na sua jornada!</p></div>
                    <div class="empty-pokedex-msg" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block;"></i>
                        <h3>Nenhum Pokémon capturado ainda!</h3><p>Navegue pela lista de Pokémons e clique em "Capturar" nos seus favoritos!</p>
                    </div>`;
            }
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao soltar Pokémon.');
    }
};

window.unfollowTrainer = async function (username) {
    const formData = new FormData();
    formData.append('username', username);
    try {
        const response = await fetch(getTcUrl('urlFollowToggle'), { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success && !data.following) {
            const item = document.getElementById(`following-item-${username}`);
            if (item) item.remove();
            document.getElementById('following-count-badge').textContent = data.count;
            if (data.count === 0) {
                const followingContainer = document.querySelector('.following-list-container');
                if (followingContainer) {
                    followingContainer.innerHTML = `
                        <div class="tms-header"><h2>Treinadores que você Segue</h2><p>Acompanhe a atividade e veja o Trainer Card dos outros treinadores da comunidade.</p></div>
                        <div class="empty-pokedex-msg" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                            <i class="fa-solid fa-user-plus" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block;"></i>
                            <h3>Você não está seguindo ninguém ainda!</h3><p>Clique no nome dos autores dos movesets para visitar seus perfis públicos e segui-los!</p>
                        </div>`;
                }
            }
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao deixar de seguir.');
    }
};

window.deleteMoveset = async function (movesetId) {
    if (!confirm('Você tem certeza de que deseja excluir este moveset?')) return;
    try {
        const response = await fetch(`/moveset/${movesetId}/delete`, { method: 'POST' });
        const data = await response.json();
        if (data.success) {
            const card = document.getElementById(`user-moveset-card-${movesetId}`);
            if (card) card.remove();
            const movesetCountEls = document.querySelectorAll('.stat-count');
            if (movesetCountEls.length > 0) {
                movesetCountEls[0].textContent = Math.max(0, parseInt(movesetCountEls[0].textContent) - 1);
            }
            const grid = document.getElementById('user-movesets-list-grid');
            if (grid && grid.children.length === 0) {
                grid.parentElement.innerHTML = `
                    <h2>Meus Movesets Criados</h2><p class="section-subtitle">Gerencie as estratégias que você publicou para a comunidade.</p>
                    <div class="empty-movesets-msg" style="text-align: center; padding: 40px; color: var(--text-secondary);">
                        <i class="fa-solid fa-flask" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 15px; display: block;"></i>
                        <h3>Você ainda não criou nenhum moveset!</h3><p>Navegue pela lista de Pokémons e crie uma nova estratégia.</p>
                    </div>`;
            }
        } else {
            alert(data.error || 'Erro ao excluir moveset.');
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao excluir moveset.');
    }
};

window.openAvatarModal = () => document.getElementById('avatar-modal').classList.add('active');
window.closeAvatarModal = () => document.getElementById('avatar-modal').classList.remove('active');

window.switchAvatarTab = function (tab) {
    const btnTrainer = document.getElementById('tab-btn-trainer');
    const btnPkm = document.getElementById('tab-btn-pkm');
    const gridTrainer = document.getElementById('avatar-grid-trainer');
    const gridPkm = document.getElementById('avatar-grid-pkm');
    
    if (tab === 'trainer') {
        btnTrainer.classList.add('active');
        btnPkm.classList.remove('active');
        gridTrainer.style.display = 'grid';
        gridPkm.style.display = 'none';
    } else {
        btnPkm.classList.add('active');
        btnTrainer.classList.remove('active');
        gridTrainer.style.display = 'none';
        gridPkm.style.display = 'grid';
    }
};

window.updateAvatarSelection = async function (filename) {
    const formData = new FormData();
    formData.append('avatar', filename);
    try {
        const response = await fetch(getTcUrl('urlAvatarUpdate'), { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            document.getElementById('trainer-avatar-img').src = data.avatarUrl;
            document.querySelectorAll('.nav-user-avatar').forEach(img => img.src = data.avatarUrl);
            document.querySelectorAll('.modal-avatar-option').forEach(opt => opt.classList.toggle('selected', opt.dataset.filename === filename));
            closeAvatarModal();
        } else alert(data.error || 'Erro ao atualizar avatar.');
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao atualizar avatar.');
    }
};

window.openTemplateModal = () => document.getElementById('template-modal').classList.add('active');
window.closeTemplateModal = () => document.getElementById('template-modal').classList.remove('active');

window.updateTemplateSelection = async function (templateImage) {
    const formData = new FormData();
    formData.append('template', templateImage);
    try {
        const response = await fetch(getTcUrl('urlTemplateUpdate'), { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            closeTemplateModal();
            location.reload();
        } else {
            alert(data.error || 'Erro ao atualizar plano de fundo.');
        }
    } catch (e) {
        console.error(e);
        alert('Erro de conexão ao atualizar plano de fundo.');
    }
};

    const templateModal = document.getElementById('template-modal');
    if (templateModal) {
        templateModal.addEventListener('click', function (e) {
            if (e.target === this) closeTemplateModal();
        });
    }
})();