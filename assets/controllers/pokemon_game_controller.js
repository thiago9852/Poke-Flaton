import { Controller } from '@hotwired/stimulus';

function formatLocalDateYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

export default class extends Controller {
    static values = {
        mode: String,
        pokemonList: Array,
        guessUrl: String,
        revealUrl: String
    }

    static targets = [
        "guessInput",
        "gameAutocomplete",
        "guessesBoard",
        "attemptsCounter",
        "btnGiveUp",
        "gameModalOverlay",
        "modalTitle",
        "modalArtwork",
        "modalPkmName",
        "modalPkmNumber",
        "modalPkmTypes",
        "modalPkmDesc",
        "statAttempts",
        "statStreak",
        "statMaxStreak",
        "btnShare",
        "toastMsg",
        "solvedPokemonContainer"
    ]

    connect() {
        this.maxAttempts = 8;
        this.todayStr = formatLocalDateYMD(new Date());
        
        // Garante a existência do user_token no localStorage para identificação anônima
        this.userTokenKey = 'pokeflaton_user_token';
        this.userToken = localStorage.getItem(this.userTokenKey);
        if (!this.userToken) {
            this.userToken = 'ut_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
            localStorage.setItem(this.userTokenKey, this.userToken);
        }

        // Chaves localStorage
        this.stateKey = `pokeflaton_daily_state_${this.todayStr}`;
        this.statsKey = 'pokeflaton_daily_stats';

        this.gameState = {
            guesses: [],
            isFinished: false,
            isWon: false,
            revealedDetails: null
        };

        this.activeAutoCompleteIndex = -1;
        this.filteredList = [];

        // Adiciona listener global para fechar o autocomplete ao clicar fora
        this.documentClickListener = (e) => {
            if (this.hasGuessInputTarget && this.hasGameAutocompleteTarget) {
                if (!this.guessInputTarget.contains(e.target) && !this.gameAutocompleteTarget.contains(e.target)) {
                    this.hideAutocomplete();
                }
            }
        };
        document.addEventListener('click', this.documentClickListener);

        this.initGame();
    }

    disconnect() {
        document.removeEventListener('click', this.documentClickListener);
    }

    // Inicialização do Jogo

    initGame() {
        // Limpar estados antigos do desafio diário de outros dias
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('pokeflaton_daily_state_') && key !== this.stateKey) {
                localStorage.removeItem(key);
            }
        }

        // Carregar Estado anterior
        const savedState = localStorage.getItem(this.stateKey);
        if (savedState) {
            this.gameState = JSON.parse(savedState);
            this.renderBoard();
        }

        this.updateAttemptsCounter();

        // Se o jogo já terminou, bloqueia os inputs e mostra o modal
        if (this.gameState.isFinished) {
            this.disableInputs();
            if (this.gameState.revealedDetails) {
                this.showEndModal(this.gameState.isWon, this.gameState.revealedDetails);
                this.renderSolvedPokemon(this.gameState.isWon, this.gameState.revealedDetails);
            } else {
                this.fetchRevealDetails();
            }
        }
    }

    // Autocomplete
    onInput(e) {
        const query = e.target.value.trim().toLowerCase();
        this.activeAutoCompleteIndex = -1;
        
        if (query.length < 1) {
            this.hideAutocomplete();
            return;
        }

        this.filteredList = this.pokemonListValue.filter(p => 
            p.name.includes(query) || p.id.toString() === query
        ).slice(0, 8);

        this.renderAutocomplete();
    }

    onKeydown(e) {
        const items = this.gameAutocompleteTarget.querySelectorAll('.game-autocomplete-item');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this.activeAutoCompleteIndex = (this.activeAutoCompleteIndex + 1) % items.length;
            this.highlightAutocompleteItem(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeAutoCompleteIndex = (this.activeAutoCompleteIndex - 1 + items.length) % items.length;
            this.highlightAutocompleteItem(items);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (this.activeAutoCompleteIndex >= 0 && this.activeAutoCompleteIndex < items.length) {
                this.selectPokemon(this.filteredList[this.activeAutoCompleteIndex]);
            } else if (this.filteredList.length > 0) {
                this.selectPokemon(this.filteredList[0]);
            }
        } else if (e.key === 'Escape') {
            this.hideAutocomplete();
        }
    }

    renderAutocomplete() {
        this.gameAutocompleteTarget.innerHTML = '';
        if (this.filteredList.length === 0) {
            this.gameAutocompleteTarget.innerHTML = '<div style="padding: 12px; color: var(--text-muted); font-size: 0.9rem;">Nenhum Pokémon encontrado.</div>';
            this.gameAutocompleteTarget.style.display = 'flex';
            return;
        }

        this.filteredList.forEach((poke, index) => {
            const item = document.createElement('div');
            item.className = 'game-autocomplete-item';
            item.innerHTML = `
                <img src="${poke.sprite}" alt="${poke.display_name}">
                <span>${poke.display_name}</span>
            `;
            item.addEventListener('click', () => this.selectPokemon(poke));
            this.gameAutocompleteTarget.appendChild(item);
        });

        this.gameAutocompleteTarget.style.display = 'flex';
    }

    highlightAutocompleteItem(items) {
        items.forEach((item, idx) => {
            if (idx === this.activeAutoCompleteIndex) {
                item.classList.add('active');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active');
            }
        });
    }

    selectPokemon(poke) {
        this.guessInputTarget.value = poke.display_name;
        this.hideAutocomplete();
        this.submitGuess();
    }

    hideAutocomplete() {
        this.gameAutocompleteTarget.style.display = 'none';
        this.gameAutocompleteTarget.innerHTML = '';
        this.activeAutoCompleteIndex = -1;
    }

    // Ação de Palpitar
    async submitGuess(e) {
        if (e) e.preventDefault();
        
        if (this.gameState.isFinished) return;

        const guessVal = this.guessInputTarget.value.trim().toLowerCase();
        
        // Valida se o Pokémon está na lista permitida
        const matchedPokemon = this.pokemonListValue.find(p => p.display_name.toLowerCase() === guessVal || p.name === guessVal);
        if (!matchedPokemon) {
            this.showToast('Pokémon inválido ou não disponível nesta geração!');
            return;
        }

        // Verifica se já palpitou esse
        const alreadyGuessed = this.gameState.guesses.some(g => g.name.toLowerCase() === matchedPokemon.name.toLowerCase());
        if (alreadyGuessed) {
            this.showToast('Você já tentou este Pokémon!');
            return;
        }

        // Trava inputs temporariamente
        this.disableInputs();

        try {
            const response = await fetch(this.guessUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    mode: this.modeValue, 
                    guess: matchedPokemon.name,
                    user_token: this.userToken,
                    attempts: this.gameState.guesses.length + 1
                })
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error || 'Erro na requisição.');
            }

            const data = await response.json();
            
            // Adiciona palpite ao estado do jogo
            data.guess.is_correct = data.is_correct;
            this.gameState.guesses.push(data.guess);

            if (data.is_correct) {
                this.gameState.isFinished = true;
                this.gameState.isWon = true;
                this.gameState.revealedDetails = data.secret;
                
                this.updateDailyStats(true);
            }

            this.saveState();
            this.renderNewGuessRow(data.guess);
            this.updateAttemptsCounter();

            // Se terminou e não acertou, puxa a revelação oficial
            if (this.gameState.isFinished) {
                if (this.gameState.isWon) {
                    this.renderSolvedPokemon(true, this.gameState.revealedDetails);
                    setTimeout(() => this.showEndModal(true, this.gameState.revealedDetails), 1500);
                } else {
                    await this.fetchRevealDetails();
                }
            } else {
                this.enableInputs();
                this.guessInputTarget.value = '';
            }

        } catch (err) {
            this.showToast(err.message || 'Erro de conexão com o servidor.');
            this.enableInputs();
        }
    }

    // Renderização do Tabuleiro
    renderBoard() {
        this.guessesBoardTarget.innerHTML = '';
        this.gameState.guesses.forEach(g => {
            this.renderGuessRowDirectly(g);
        });
    }

    renderGuessRowDirectly(g) {
        const row = this.createRowEl(g, false); // false = sem animação de flip
        this.guessesBoardTarget.insertBefore(row, this.guessesBoardTarget.firstChild);
    }

    renderNewGuessRow(g) {
        const row = this.createRowEl(g, true); // true = aplica animação de flip
        this.guessesBoardTarget.insertBefore(row, this.guessesBoardTarget.firstChild);
    }

    createRowEl(g, animate) {
        const row = document.createElement('div');
        row.className = 'guess-capsule-row';
        if (animate) {
            row.classList.add('animate-row');
        }

        const typeTranslations = {
            normal: 'Normal',
            fire: 'Fogo',
            water: 'Água',
            grass: 'Planta',
            electric: 'Elétrico',
            ice: 'Gelo',
            fighting: 'Lutador',
            poison: 'Veneno',
            ground: 'Terra',
            flying: 'Voador',
            psychic: 'Psíquico',
            bug: 'Inseto',
            rock: 'Pedra',
            ghost: 'Fantasma',
            dragon: 'Dragão',
            dark: 'Sombrio',
            steel: 'Aço',
            fairy: 'Fada'
        };

        const typeIds = {
            normal: 1,
            fighting: 2,
            flying: 3,
            poison: 4,
            ground: 5,
            rock: 6,
            bug: 7,
            ghost: 8,
            steel: 9,
            fire: 10,
            water: 11,
            grass: 12,
            electric: 13,
            psychic: 14,
            ice: 15,
            dragon: 16,
            dark: 17,
            fairy: 18
        };

        const formatType = (t) => typeTranslations[t.toLowerCase()] || t;
        
        const getTypeSpriteUrl = (t) => {
            const id = typeIds[t.toLowerCase()];
            if (id) {
                return `https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/types/generation-viii/sword-shield/small/${id}.png`;
            }
            return '';
        };

        const getArrowBadge = (status) => {
            if (status === 'higher') {
                return '<div class="indicator-arrow-badge up"><i class="fa-solid fa-arrow-up"></i></div>';
            }
            if (status === 'lower') {
                return '<div class="indicator-arrow-badge down"><i class="fa-solid fa-arrow-down"></i></div>';
            }
            return '';
        };

        // Esquerda: Imagem e nome
        const infoDiv = document.createElement('div');
        infoDiv.className = 'guess-pokemon-info';
        infoDiv.innerHTML = `
            <img src="${g.sprite}" alt="${g.name}" class="guess-pokemon-img">
            <span class="guess-pokemon-name">${g.name}</span>
        `;
        row.appendChild(infoDiv);

        // Direita: Indicadores (Circles)
        const indicatorsDiv = document.createElement('div');
        indicatorsDiv.className = 'guess-indicators';

        // 1. Geração
        const genCircle = document.createElement('div');
        const genClass = g.generation.status === 'correct' ? 'correct' : 'incorrect';
        genCircle.className = `indicator-circle ${genClass}`;
        genCircle.innerHTML = `
            <span class="indicator-value">${g.generation.value}</span>
            <span class="indicator-label">GEN</span>
            ${getArrowBadge(g.generation.status)}
        `;
        indicatorsDiv.appendChild(genCircle);

        // 2. Tipo 1
        const type1 = g.types[0];
        const type1Circle = document.createElement('div');
        type1Circle.className = `indicator-circle ${type1.status}`;
        type1Circle.innerHTML = `
            <span class="indicator-icon"><img src="${getTypeSpriteUrl(type1.type)}" alt="${type1.type}" class="type-sprite-img"></span>
            <span class="indicator-label">${formatType(type1.type)}</span>
        `;
        indicatorsDiv.appendChild(type1Circle);

        // 3. Tipo 2
        const type2 = g.types[1];
        const type2Circle = document.createElement('div');
        type2Circle.className = `indicator-circle ${type2.status}`;
        type2Circle.innerHTML = `
            <span class="indicator-icon"><img src="${getTypeSpriteUrl(type2.type)}" alt="${type2.type}" class="type-sprite-img"></span>
            <span class="indicator-label">${formatType(type2.type)}</span>
        `;
        indicatorsDiv.appendChild(type2Circle);

        // 4. Altura (M)
        const heightCircle = document.createElement('div');
        const heightClass = g.height.status === 'correct' ? 'correct' : 'incorrect';
        heightCircle.className = `indicator-circle ${heightClass}`;
        heightCircle.innerHTML = `
            <span class="indicator-value">${g.height.value}</span>
            <span class="indicator-label">M</span>
            ${getArrowBadge(g.height.status)}
        `;
        indicatorsDiv.appendChild(heightCircle);

        // 5. Peso (KG)
        const weightCircle = document.createElement('div');
        const weightClass = g.weight.status === 'correct' ? 'correct' : 'incorrect';
        weightCircle.className = `indicator-circle ${weightClass}`;
        weightCircle.innerHTML = `
            <span class="indicator-value">${g.weight.value}</span>
            <span class="indicator-label">KG</span>
            ${getArrowBadge(g.weight.status)}
        `;
        indicatorsDiv.appendChild(weightCircle);

        row.appendChild(indicatorsDiv);
        return row;
    }

    // Fluxo Fim de Jogo e Modal
    async fetchRevealDetails() {
        try {
            const response = await fetch(this.revealUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    mode: this.modeValue,
                    user_token: this.userToken,
                    attempts: this.gameState.guesses.length
                })
            });
            const data = await response.json();
            this.gameState.revealedDetails = data;
            this.saveState();
            this.showEndModal(this.gameState.isWon, data);
            this.renderSolvedPokemon(this.gameState.isWon, data);
        } catch (e) {
            this.showToast('Erro ao carregar detalhes finais.');
        }
    }

    renderSolvedPokemon(isWon, secret) {
        if (!this.hasSolvedPokemonContainerTarget) return;

        if (isWon && secret) {
            this.solvedPokemonContainerTarget.innerHTML = `
                <img src="${secret.sprite}" alt="${secret.name}" class="solved-pokemon-art">
                <div class="solved-pokemon-info">
                    <span class="solved-pokemon-label">Resolvido!</span>
                    <span class="solved-pokemon-name">${secret.name}</span>
                    <span class="solved-pokemon-number">#${secret.id}</span>
                </div>
            `;
            this.solvedPokemonContainerTarget.classList.add('active');
        } else {
            this.solvedPokemonContainerTarget.innerHTML = '';
            this.solvedPokemonContainerTarget.classList.remove('active');
        }
    }

    showEndModal(isWon, secret) {
        this.modalTitleTarget.textContent = isWon ? 'Você Venceu!' : 'Fim de Jogo!';
        this.modalTitleTarget.className = `modal-title ${isWon ? 'win' : 'lose'}`;
        
        this.modalArtworkTarget.src = secret.sprite;
        this.modalPkmNameTarget.textContent = secret.name;
        this.modalPkmDescTarget.textContent = `"${secret.description}"`;
        
        if (this.hasModalPkmNumberTarget) {
            this.modalPkmNumberTarget.textContent = `#${secret.id}`;
        }

        if (this.hasModalPkmTypesTarget && secret.types) {
            this.modalPkmTypesTarget.innerHTML = '';
            secret.types.forEach(t => {
                const badge = document.createElement('span');
                badge.className = `guess-type-badge type-badge-sm-${t}`;
                badge.textContent = t;
                this.modalPkmTypesTarget.appendChild(badge);
            });
        }

        this.statAttemptsTarget.textContent = this.gameState.guesses.length;

        const stats = this.getDailyStats();
        if (this.hasStatStreakTarget) {
            this.statStreakTarget.textContent = stats.currentStreak;
        }
        if (this.hasStatMaxStreakTarget) {
            this.statMaxStreakTarget.textContent = stats.maxStreak;
        }

        this.gameModalOverlayTarget.classList.add('active');
    }

    closeModal() {
        this.gameModalOverlayTarget.classList.remove('active');
    }

    // Estatísticas e Estado Salvo
    saveState() {
        localStorage.setItem(this.stateKey, JSON.stringify(this.gameState));
    }

    updateAttemptsCounter() {
        this.attemptsCounterTarget.textContent = `Palpites: ${this.gameState.guesses.length}`;
    }

    disableInputs() {
        this.guessInputTarget.disabled = true;
        
        const btnGuess = this.element.querySelector('#btnGuess');
        if (btnGuess) btnGuess.disabled = true;
        
        if (this.hasBtnGiveUpTarget) {
            this.btnGiveUpTarget.disabled = true;
            this.btnGiveUpTarget.style.opacity = '0.5';
            this.btnGiveUpTarget.style.cursor = 'not-allowed';
        }
    }

    enableInputs() {
        this.guessInputTarget.disabled = false;
        
        const btnGuess = this.element.querySelector('#btnGuess');
        if (btnGuess) btnGuess.disabled = false;
        
        if (this.hasBtnGiveUpTarget) {
            this.btnGiveUpTarget.disabled = false;
            this.btnGiveUpTarget.style.opacity = '1';
            this.btnGiveUpTarget.style.cursor = 'pointer';
        }
    }

    getDailyStats() {
        const defaultStats = {
            totalDailyGames: 0,
            wonDailyGames: 0,
            currentStreak: 0,
            maxStreak: 0,
            lastPlayedDate: ''
        };
        const stats = localStorage.getItem(this.statsKey);
        return stats ? JSON.parse(stats) : defaultStats;
    }

    updateDailyStats(isWon) {
        const stats = this.getDailyStats();
        
        if (stats.lastPlayedDate === this.todayStr) {
            return; // Já salvou estatísticas hoje
        }

        stats.totalDailyGames++;
        if (isWon) {
            stats.wonDailyGames++;
            
            // Verifica se continuou a sequência de ontem
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const yesterdayStr = formatLocalDateYMD(yesterday);
            
            if (stats.lastPlayedDate === yesterdayStr || stats.lastPlayedDate === '') {
                stats.currentStreak++;
            } else {
                stats.currentStreak = 1;
            }
            
            if (stats.currentStreak > stats.maxStreak) {
                stats.maxStreak = stats.currentStreak;
            }
        } else {
            stats.currentStreak = 0;
        }

        stats.lastPlayedDate = this.todayStr;
        localStorage.setItem(this.statsKey, JSON.stringify(stats));
    }

    // Compartilhar (Daily Mode)
    share() {
        const totalGuesses = this.gameState.guesses.length;
        const secret = this.gameState.revealedDetails;
        
        let shareText = `PokeFlaton - Pokémon do Dia (${this.todayStr.split('-').reverse().join('/')}) \n`;
        
        if (this.gameState.isWon) {
            shareText += `Adivinhei em: ${totalGuesses} ${totalGuesses === 1 ? 'tentativa' : 'tentativas'}! \n`;
        } else {
            shareText += `Resultado: Desistiu \n`;
        }
        
        if (secret) {
            shareText += `Pokémon Secreto: #${secret.id} ${secret.name}\n`;
        }
        
        shareText += `\nJogue agora: ${window.location.href}`;
        
        navigator.clipboard.writeText(shareText).then(() => {
            this.showToastNotification('Resultado copiado para a área de transferência!');
        }).catch(() => {
            this.showToastNotification('Falha ao copiar automaticamente.');
        });
    }

    showToastNotification(msg) {
        this.toastMsgTarget.textContent = msg;
        this.toastMsgTarget.classList.add('active');
        setTimeout(() => {
            this.toastMsgTarget.classList.remove('active');
        }, 3000);
    }

    // Desistir
    async giveUp() {
        if (this.gameState.isFinished) return;
        if (!confirm('Tem certeza que deseja desistir desta rodada?')) return;

        this.disableInputs();
        this.gameState.isFinished = true;
        this.gameState.isWon = false;
        
        this.updateDailyStats(false);
        
        this.saveState();
        this.updateAttemptsCounter();
        await this.fetchRevealDetails();
    }

    // Helper toast simples
    showToast(msg) {
        this.showToastNotification(msg);
    }
}
