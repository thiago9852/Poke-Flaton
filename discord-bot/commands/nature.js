const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('nature')
    .setDescription('Retorna detalhes de uma Nature específica (ex: Modest, Jolly)')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome da Nature (em inglês ou português)')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.CHANNEL_NATURE || process.env.ALL_COMMANDS;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const natureNameInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/nature/${natureNameInput}`);
      const data = response.data;

      const embed = new EmbedBuilder()
        .setTitle(`🔮 Nature: ${data.name} (${data.name_pt})`)
        .setColor(0x9b59b6)
        .addFields(
          { name: 'Aumento (+10%)', value: `🟢 **${data.increased_pt}**`, inline: true },
          { name: 'Redução (-10%)', value: `🔴 **${data.decreased_pt}**`, inline: true }
        )
        .setDescription(
          data.increased === 'none' 
            ? 'Esta é uma nature neutra. Ela não altera nenhum atributo.' 
            : `Aumenta o atributo **${data.increased_pt}** e diminui o atributo **${data.decreased_pt}**.`
        )
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });

    } catch (error) {
      console.error(error);
      const errorMsg = error.response && error.response.data && error.response.data.error
        ? error.response.data.error
        : 'Não foi possível encontrar esta Nature.';
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
