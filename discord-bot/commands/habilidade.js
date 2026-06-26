const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('habilidade')
    .setDescription('Exibe informações detalhadas de uma habilidade de Pokémon')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome da habilidade')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_HABILIDADE;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const abilityNameInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/ability/${abilityNameInput}`);
      const data = response.data;

      const embed = new EmbedBuilder()
        .setTitle(`🌀 Habilidade: ${data.name}`)
        .setColor(0x3498db)
        .setDescription(data.description)
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });

    } catch (error) {
      console.error(error);
      const errorMsg = error.response && error.response.data && error.response.data.error
        ? error.response.data.error
        : 'Não foi possível encontrar esta habilidade.';
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
