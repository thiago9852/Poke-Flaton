const { SlashCommandBuilder, AttachmentBuilder } = require('discord.js');
const axios = require('axios');
const puppeteer = require('puppeteer');
const { API_BASE_URL } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('moveset')
    .setDescription('Retorna uma imagem (PNG) com o melhor moveset recomendado para o Pokémon')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome do Pokémon')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.CHANNEL_MOVESET || process.env.ALL_COMMANDS;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const pokemonNameInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/moveset/${pokemonNameInput}`);
      const data = response.data;

      const shareCardUrl = data.share_card_url;
      console.log(`Gerando print do moveset para ${data.pokemon_name} (${shareCardUrl})...`);

      const browser = await puppeteer.launch({
        headless: 'new',
        args: ['--no-sandbox', '--disable-setuid-sandbox']
      });
      
      const page = await browser.newPage();
      await page.setViewport({ width: 850, height: 480, deviceScaleFactor: 2 }); 

      try {
        await page.goto(shareCardUrl, { waitUntil: 'load', timeout: 15000 });
        await new Promise(resolve => setTimeout(resolve, 1500));

        const cardElement = await page.$('.share-card-canvas-container');
        if (!cardElement) {
          throw new Error('Elemento do card de compartilhamento não encontrado na página.');
        }

        const buffer = await cardElement.screenshot({
          type: 'png',
          omitBackground: true
        });

        await browser.close();

        const attachment = new AttachmentBuilder(buffer, { name: `${data.pokemon_name}_moveset.png` });

        await interaction.editReply({
          content: `Aqui está o melhor moveset recomendado para **${data.pokemon_name.charAt(0).toUpperCase() + data.pokemon_name.slice(1)}** (${data.type.toUpperCase()}):`,
          files: [attachment]
        });

      } catch (browserError) {
        await browser.close();
        throw browserError;
      }

    } catch (error) {
      console.error(error);
      let errorMsg = 'Não foi possível buscar ou renderizar o moveset para este Pokémon.';
      
      if (error.response && error.response.status === 404) {
        const createUrl = `${API_BASE_URL}/pokemon/${pokemonNameInput}/moveset/new`;
        errorMsg = `Não há movesets cadastrados no site para **${pokemonNameInput}**.\nSeja o primeiro a criar um em: ${createUrl}`;
      } else if (error.response && error.response.data && error.response.data.error) {
        errorMsg = error.response.data.error;
      }
      
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
