import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        topPokemons: Array,
        generations: Array,
        types: Array,
        genTopPokemons: Array,
        successRates: Array
    };

    connect() {
        this.myCharts = {};
        this.loadChartJs()
            .then((Chart) => {
                this.renderCharts(Chart);
            })
            .catch((err) => {
                console.error("Erro ao carregar Chart.js para as estatísticas:", err);
            });
    }

    disconnect() {
        // Destruir gráficos ao desconectar para evitar vazamentos de memória e erros de re-renderização
        if (this.myCharts) {
            Object.keys(this.myCharts).forEach(key => {
                if (this.myCharts[key]) {
                    this.myCharts[key].destroy();
                }
            });
        }
    }

    /**
     * Carrega a biblioteca Chart.js dinamicamente se ela ainda não estiver disponível.
     */
    loadChartJs() {
        return new Promise((resolve, reject) => {
            if (window.Chart) {
                resolve(window.Chart);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = () => resolve(window.Chart);
            script.onerror = (err) => reject(err);
            document.head.appendChild(script);
        });
    }

    renderCharts(Chart) {
        const topPokemons = this.topPokemonsValue;
        const generations = this.generationsValue;
        const types = this.typesValue;
        const genTopPokemons = this.genTopPokemonsValue;
        const successRates = this.successRatesValue;

        // Estilos e cores para o tema escuro do PokeFlaton
        const textColor = '#94a3b8';
        const gridColor = 'rgba(255, 255, 255, 0.05)';
        const tooltipBg = 'rgba(15, 23, 42, 0.95)';
        const tooltipBorder = 'rgba(139, 92, 246, 0.3)';

        Chart.defaults.color = textColor;
        Chart.defaults.font.family = "'Outfit', sans-serif";

        const tooltipOptions = {
            backgroundColor: tooltipBg,
            titleColor: '#fff',
            bodyColor: '#e2e8f0',
            borderColor: tooltipBorder,
            borderWidth: 1,
            padding: 10,
            cornerRadius: 6
        };

        // 1. Gráfico Top Pokémon (Barras Horizontais)
        const ctxPkm = document.getElementById('chart-top-pokemon');
        if (ctxPkm && topPokemons.length > 0) {
            this.myCharts.topPokemon = new Chart(ctxPkm, {
                type: 'bar',
                data: {
                    labels: topPokemons.map(p => p.name),
                    datasets: [{
                        label: 'Palpites',
                        data: topPokemons.map(p => p.plays),
                        backgroundColor: 'rgba(139, 92, 246, 0.45)',
                        borderColor: '#8b5cf6',
                        borderWidth: 1.5,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true, grid: { color: gridColor } },
                        y: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: tooltipOptions
                    }
                }
            });
        }

        // 2. Gráfico Distribuição por Geração (Pie/Donut)
        const ctxGen = document.getElementById('chart-generations');
        if (ctxGen && generations.length > 0) {
            this.myCharts.generations = new Chart(ctxGen, {
                type: 'doughnut',
                data: {
                    labels: generations.map(g => g.label),
                    datasets: [{
                        data: generations.map(g => g.value),
                        backgroundColor: [
                            '#ef4444', '#eab308', '#2563eb', '#3b82f6', 
                            '#4b5563', '#db2777', '#f97316', '#0d9488', '#7c3aed'
                        ],
                        borderWidth: 1,
                        borderColor: '#1e1b4b'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom', 
                            labels: { color: textColor, boxWidth: 10, padding: 15 } 
                        },
                        tooltip: tooltipOptions
                    }
                }
            });
        }

        // 3. Gráfico Distribuição por Tipo (Pie/Donut)
        const ctxTypes = document.getElementById('chart-types');
        if (ctxTypes && types.length > 0) {
            const typeColors = {
                'Normal': '#A8A77A', 'Fogo': '#EE8130', 'Fire': '#EE8130', 'Água': '#6390F0', 'Water': '#6390F0',
                'Elétrico': '#F7D02C', 'Electric': '#F7D02C', 'Planta': '#7AC74C', 'Grass': '#7AC74C',
                'Gelo': '#96D9D6', 'Ice': '#96D9D6', 'Lutador': '#C22E28', 'Fighting': '#C22E28',
                'Veneno': '#A33EA1', 'Poison': '#A33EA1', 'Terra': '#E2BF65', 'Ground': '#E2BF65',
                'Voador': '#A98FF3', 'Flying': '#A98FF3', 'Psíquico': '#F95587', 'Psychic': '#F95587',
                'Inseto': '#A6B91A', 'Bug': '#A6B91A', 'Pedra': '#B6A136', 'Rock': '#B6A136',
                'Fantasma': '#735797', 'Ghost': '#735797', 'Dragão': '#6F35FC', 'Dragon': '#6F35FC',
                'Sombrio': '#705746', 'Dark': '#705746', 'Aço': '#B7B7CE', 'Steel': '#B7B7CE',
                'Fada': '#D685AD', 'Fairy': '#D685AD'
            };
            const backgroundColors = types.map(t => typeColors[t.label] || typeColors[t.label.toUpperCase()] || '#a78bfa');

            this.myCharts.types = new Chart(ctxTypes, {
                type: 'doughnut',
                data: {
                    labels: types.map(t => t.label),
                    datasets: [{
                        data: types.map(t => t.value),
                        backgroundColor: backgroundColors,
                        borderWidth: 1,
                        borderColor: '#1e1b4b'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom', 
                            labels: { color: textColor, boxWidth: 10, padding: 15 } 
                        },
                        tooltip: tooltipOptions
                    }
                }
            });
        }

        // 4. Gráfico Presença por Geração (Barras Empilhadas)
        const ctxPresence = document.getElementById('chart-presence');
        if (ctxPresence && genTopPokemons.length > 0) {
            const stackColors = [
                '#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981',
                '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#d946ef'
            ];

            const datasets = genTopPokemons.map((ds, idx) => {
                return {
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: stackColors[idx % stackColors.length],
                    stack: 'generationStack'
                };
            });

            this.myCharts.presence = new Chart(ctxPresence, {
                type: 'bar',
                data: {
                    labels: ['Geração 1', 'Geração 2', 'Geração 3', 'Geração 4', 'Geração 5', 'Geração 6', 'Geração 7', 'Geração 8', 'Geração 9'],
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, grid: { display: false } },
                        y: { stacked: true, beginAtZero: true, grid: { color: gridColor } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipOptions,
                            callbacks: {
                                label: function(context) {
                                    const item = context.raw;
                                    if (!item || item.y === 0) return null;
                                    return `${item.name}: ${item.y} partidas`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // 5. Gráfico Histórico de Taxa de Acertos (Barras Verticais de Coluna)
        const ctxRate = document.getElementById('chart-success-rate');
        if (ctxRate && successRates.length > 0) {
            this.myCharts.successRate = new Chart(ctxRate, {
                type: 'bar',
                data: {
                    labels: successRates.map(r => r.date),
                    datasets: [{
                        label: 'Taxa de Acerto (%)',
                        data: successRates.map(r => r.rate),
                        backgroundColor: successRates.map(r => {
                            if (r.rate >= 80) return 'rgba(16, 185, 129, 0.45)';
                            if (r.rate >= 50) return 'rgba(245, 158, 11, 0.45)';
                            return 'rgba(239, 68, 68, 0.45)';
                        }),
                        borderColor: successRates.map(r => {
                            if (r.rate >= 80) return '#10b981';
                            if (r.rate >= 50) return '#f59e0b';
                            return '#ef4444';
                        }),
                        borderWidth: 1.5,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, max: 100, grid: { color: gridColor } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            ...tooltipOptions,
                            callbacks: {
                                afterLabel: function(context) {
                                    const item = successRates[context.dataIndex];
                                    return `Partidas: ${item.total} | Vitórias: ${item.won}`;
                                }
                            }
                        }
                    }
                }
            });
        }
    }
}
