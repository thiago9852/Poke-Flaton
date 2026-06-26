const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('item')
    .setDescription('Exibe informações detalhadas de um item segurável de Pokémon')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome do item')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_ITEM;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const itemNameInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/item/${itemNameInput}`);
      const data = response.data;

      const embed = new EmbedBuilder()
        .setTitle(`🎒 Item: ${data.name}`)
        .setColor(0xe67e22)
        .setDescription(data.description)
        .setThumbnail(data.sprite)
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });

    } catch (error) {
      console.error(error);
      const errorMsg = error.response && error.response.data && error.response.data.error
        ? error.response.data.error
        : 'Não foi possível encontrar este item.';
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
