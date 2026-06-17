(() => {
    function initDetail() {
        const shinyToggle = document.getElementById('shiny-toggle');
        if (shinyToggle && !shinyToggle.dataset.initialized) {
            shinyToggle.dataset.initialized = 'true';
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
        }

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

// Modal de Compartilhamento e Download de PNG do Moveset
window.openShareModal = async function (movesetId) {
    const modal = document.getElementById('share-modal');
    const loader = document.getElementById('share-modal-loader');
    const previewContainer = document.getElementById('share-modal-preview');
    const previewImg = document.getElementById('share-modal-image');
    
    if (!modal) return;
    
    // Mostra o modal e exibe o loader, ocultando previews anteriores
    modal.classList.add('active');
    loader.style.display = 'flex';
    previewContainer.style.display = 'none';
    previewImg.src = '';
    
    try {
        const shareCard = document.getElementById('share-card-' + movesetId);
        if (!shareCard) {
            alert('Erro: Card de compartilhamento não encontrado.');
            closeShareModal();
            return;
        }
        
        // Sincroniza a imagem do Pokémon no share card (copia se for Shiny)
        const currentArtwork = document.getElementById('pokemon-artwork');
        const shareArtwork = document.getElementById('share-pokemon-artwork-' + movesetId);
        if (currentArtwork && shareArtwork) {
            shareArtwork.src = currentArtwork.src;
        }
        
        // Gera o QR Code dinamicamente apontando para a URL da página atual
        const qrContainer = document.getElementById('share-qr-' + movesetId);
        if (qrContainer) {
            qrContainer.innerHTML = '';
            // qrcode(typeNumber, errorCorrectionLevel) - 0 autodetecta tipo
            const qr = qrcode(0, 'M');
            qr.addData(window.location.href);
            qr.make();
            // Gera tag img do QR code com tamanho e margem ajustados para caber em 100px
            qrContainer.innerHTML = qr.createImgTag(3, 4);
        }
        
        // Aguarda todas as imagens do card (Pokémon, item e QR Code) estarem 100% carregadas
        const images = Array.from(shareCard.querySelectorAll('img'));
        await Promise.all(images.map(img => {
            if (img.complete) return Promise.resolve();
            return new Promise(resolve => {
                img.onload = resolve;
                img.onerror = resolve; // resolve mesmo em erro para não travar a aplicação
            });
        }));
        
        // Pequena pausa para garantir renderização de fontes e layout antes do print
        await new Promise(resolve => setTimeout(resolve, 250));
        
        // Executa html2canvas no card oculto com alta definição (scale: 2)
        const canvas = await html2canvas(shareCard, {
            useCORS: true,
            scale: 2,
            backgroundColor: '#121216',
            logging: false
        });
        
        // Converte o canvas para imagem e insere no modal de pré-visualização
        const pngUrl = canvas.toDataURL('image/png');
        previewImg.src = pngUrl;
        
        loader.style.display = 'none';
        previewContainer.style.display = 'flex';
        
        // Define o callback do botão de download de forma dinâmica para este moveset
        const downloadBtn = document.getElementById('share-modal-download-btn');
        downloadBtn.onclick = function () {
            const pokemonName = document.querySelector('.current-title')?.textContent?.trim()?.split(/\s+/)[0]?.toLowerCase() || 'pokemon';
            const link = document.createElement('a');
            link.download = `moveset-${pokemonName}-${movesetId}.png`;
            link.href = pngUrl;
            link.click();
        };
        
    } catch (err) {
        console.error('Erro ao gerar imagem:', err);
        alert('Erro ao processar a imagem do moveset. Tente novamente.');
        closeShareModal();
    }
};

window.closeShareModal = function () {
    const modal = document.getElementById('share-modal');
    if (modal) {
        modal.classList.remove('active');
    }
};

        // Evento para fechar modal clicando fora do card no overlay
        const modal = document.getElementById('share-modal');
        if (modal && !modal.dataset.initialized) {
            modal.dataset.initialized = 'true';
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    closeShareModal();
                }
            });
        }

        // Lógica de Curtir (Upvote) Moveset
        document.querySelectorAll('.upvote-btn').forEach(btn => {
            if (!btn.dataset.initialized) {
                btn.dataset.initialized = 'true';
                const movesetId = btn.dataset.id;
                if (localStorage.getItem(`voted_moveset_${movesetId}`) === 'true') {
                    btn.classList.add('voted');
                    const icon = btn.querySelector('i');
                    if (icon) {
                        icon.className = 'fa-solid fa-thumbs-up';
                    }
                }
            }
        });
    }

    // Registrar para eventos do Turbo e executar imediatamente
    document.addEventListener('turbo:load', initDetail);
    initDetail();

if (window.upvoteClickHandler) {
    document.removeEventListener('click', window.upvoteClickHandler);
}
window.upvoteClickHandler = async function (e) {
    const btn = e.target.closest('.upvote-btn');
    if (!btn) return;

    e.preventDefault();
    if (btn.classList.contains('voted') || btn.disabled) return;

    btn.disabled = true;
    const movesetId = btn.dataset.id;
    const voteCountSpan = btn.querySelector('.vote-count');
    const icon = btn.querySelector('i');

    try {
        const response = await fetch(`/moveset/${movesetId}/vote`, {
            method: 'POST'
        });

        if (!response.ok) {
            throw new Error('Falha ao registrar voto.');
        }

        const data = await response.json();
        if (data.success) {
            if (voteCountSpan) {
                voteCountSpan.textContent = data.votes;
            }
            btn.classList.add('voted');
            if (icon) {
                icon.className = 'fa-solid fa-thumbs-up';
            }
            localStorage.setItem(`voted_moveset_${movesetId}`, 'true');
        } else {
            alert(data.error || 'Erro ao curtir moveset.');
        }
    } catch (err) {
        console.error(err);
        alert('Erro de conexão ao curtir o moveset.');
    } finally {
        btn.disabled = false;
    }
};
document.addEventListener('click', window.upvoteClickHandler);
})();