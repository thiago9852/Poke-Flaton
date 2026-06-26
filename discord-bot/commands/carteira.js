const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const economy = require('../economy');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('carteira')
    .setDescription('Exibe seu saldo atual de VivillonCoins e sua classificação no ranking'),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_ECONOMIA;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();

    try {
      const balance = economy.getUserCoins(interaction.user.id);
      const leaderboard = economy.getLeaderboard();
      const rankIndex = leaderboard.findIndex(u => u.userId === interaction.user.id);
      const rank = rankIndex === -1 ? 'Sem ranking' : `#${rankIndex + 1}`;

      const embed = new EmbedBuilder()
        .setTitle(`👛 Carteira de ${interaction.user.username}`)
        .setDescription(`Confira suas moedas e sua classificação no servidor!`)
        .setColor(0xf1c40f)
        .setThumbnail(interaction.user.displayAvatarURL())
        .addFields(
          { name: '💰 Saldo', value: `**${balance} VivillonCoins**`, inline: true },
          { name: '🏆 Posição no Ranking', value: `**${rank}**`, inline: true }
        )
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });
    } catch (err) {
      console.error(err);
      await interaction.editReply({ content: '❌ Ocorreu um erro ao acessar a carteira.' });
    }
  }
};
