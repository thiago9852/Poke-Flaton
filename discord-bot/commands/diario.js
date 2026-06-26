const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const economy = require('../economy');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('diario')
    .setDescription('Resgata sua recompensa diária de VivillonCoins (a cada 24h)'),
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
      const result = economy.claimDaily(interaction.user.id, interaction.user.tag);
      if (result.success) {
        const embed = new EmbedBuilder()
          .setTitle('💰 Recompensa Diária!')
          .setDescription(`Você resgatou suas moedas diárias com sucesso!\n\nGanhou: **${result.reward} VivillonCoins**\nSaldo atual: **${result.total}** moedas`)
          .setColor(0x2ecc71)
          .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
          .setTimestamp();
          
        await interaction.editReply({ embeds: [embed] });
      } else {
        const totalSecs = Math.floor(result.remainingMs / 1000);
        const hrs = Math.floor(totalSecs / 3600);
        const mins = Math.floor((totalSecs % 3600) / 60);
        
        await interaction.editReply({
          content: `❌ Você já resgatou sua recompensa diária! Tente novamente em **${hrs}h ${mins}m**.`
        });
      }
    } catch (err) {
      console.error(err);
      await interaction.editReply({ content: '❌ Ocorreu um erro ao resgatar seu diário.' });
    }
  }
};
