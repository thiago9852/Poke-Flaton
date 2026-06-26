const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const economy = require('../economy');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('ranking')
    .setDescription('Exibe o ranking dos treinadores no servidor')
    .addStringOption(option =>
      option.setName('tipo')
        .setDescription('Escolha o tipo de ranking')
        .setRequired(false)
        .addChoices(
          { name: 'Geral de Moedas', value: 'geral' },
          { name: 'Mensal de Acertos', value: 'mensal' }
        )
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_ECONOMIA;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const tipo = interaction.options.getString('tipo') || 'geral';

    try {
      if (tipo === 'geral') {
        const leaderboard = economy.getLeaderboard();
        const top10 = leaderboard.slice(0, 10);

        if (top10.length === 0) {
          return interaction.editReply({ content: 'Ninguém tem moedas ainda no servidor!' });
        }

        const listStr = top10.map((user, idx) => {
          let medal = '';
          if (idx === 0) medal = '🥇 ';
          else if (idx === 1) medal = '🥈 ';
          else if (idx === 2) medal = '🥉 ';
          else medal = `**#${idx + 1}** `;

          const displayName = user.username ? user.username.split('#')[0] : 'Desconhecido';
          return `${medal} ${displayName} — **${user.coins}** VivillonCoins`;
        }).join('\n');

        const embed = new EmbedBuilder()
          .setTitle('🏆 Leaderboard de VivillonCoins')
          .setDescription(`Aqui estão os 10 membros mais ricos do servidor:\n\n${listStr}`)
          .setColor(0xe74c3c)
          .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
          .setTimestamp();

        await interaction.editReply({ embeds: [embed] });
      } else {
        // Ranking Mensal de Acertos
        const now = new Date();
        const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        const leaderboard = economy.getMonthlyLeaderboard(monthKey);
        const top10 = leaderboard.slice(0, 10);

        if (top10.length === 0) {
          return interaction.editReply({ content: `Ninguém acertou Pokémons no mini-game ainda no mês de **${monthKey}**!` });
        }

        const listStr = top10.map((user, idx) => {
          let medal = '';
          if (idx === 0) medal = '🥇 ';
          else if (idx === 1) medal = '🥈 ';
          else if (idx === 2) medal = '🥉 ';
          else medal = `**#${idx + 1}** `;

          const displayName = user.username ? user.username.split('#')[0] : 'Desconhecido';
          return `${medal} ${displayName} — **${user.wins}** acertos`;
        }).join('\n');

        const embed = new EmbedBuilder()
          .setTitle(`🏆 Ranking Mensal de Acertos (${monthKey})`)
          .setDescription(`Membros com mais acertos em \`/jogar\` neste mês:\n\n${listStr}\n\n*O 1º colocado no final do mês receberá **500 VivillonCoins** de prêmio!*`)
          .setColor(0x9b59b6)
          .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
          .setTimestamp();

        await interaction.editReply({ embeds: [embed] });
      }
    } catch (err) {
      console.error(err);
      await interaction.editReply({ content: '❌ Ocorreu um erro ao carregar o ranking.' });
    }
  }
};
