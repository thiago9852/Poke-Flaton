const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL, typeColors, typeEmojis } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('pokemon')
    .setDescription('Retorna informações detalhadas do Pokémon (Tipos, Evolução, Local)')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome ou ID do Pokémon')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.CHANNEL_POKEMON || process.env.ALL_COMMANDS;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const pokemonNameInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/pokemon/${pokemonNameInput}`);
      const data = response.data;

      const primaryType = data.types[0];
      const embedColor = typeColors[primaryType] || 0x3498db;

      // Formatar tipos
      const formattedTypes = data.types.map(t => typeEmojis[t] || t).join(', ');

      // Formatar atributos (Base Stats)
      const stats = data.stats;
      const statsField = [
        `**HP:** ${stats.hp}`,
        `**Ataque:** ${stats.attack}`,
        `**Defesa:** ${stats.defense}`,
        `**Atq. Esp.:** ${stats['special-attack']}`,
        `**Def. Esp.:** ${stats['special-defense']}`,
        `**Velocidade:** ${stats.speed}`
      ].join('\n');

      // Formatar locais de captura
      const locationsField = data.locations.length > 0 
        ? data.locations.map(loc => `📍 ${loc}`).join('\n') 
        : 'Nenhuma localização registrada.';

      // Formatar linha evolutiva
      let evolutionField = 'Sem evolução conhecida.';
      if (data.evolution_chain && Object.keys(data.evolution_chain).length > 0) {
        const sortedStages = Object.keys(data.evolution_chain).sort((a, b) => Number(a) - Number(b));
        const stageStrings = sortedStages.map(stage => {
          const nodes = data.evolution_chain[stage];
          return nodes.map(n => {
            const capitalizedNodeName = n.name.charAt(0).toUpperCase() + n.name.slice(1);
            let details = '';
            if (n.evolution_method) {
              details = ` (${n.evolution_method})`;
            }
            return `**${capitalizedNodeName}**${details}`;
          }).join(' ou ');
        });
        evolutionField = stageStrings.join(' ➔ ');
      }

      const embed = new EmbedBuilder()
        .setTitle(`📌 #${String(data.species_id).padStart(4, '0')} - ${data.name.charAt(0).toUpperCase() + data.name.slice(1)}`)
        .setColor(embedColor)
        .setThumbnail(data.sprite)
        .addFields(
          { name: '**Tipo(s)**', value: formattedTypes, inline: true },
          { name: '**Status Base**', value: statsField, inline: true },
          { name: '**Nature Recomendada**', value: `\`${data.recommended_nature}\``, inline: false },
          { name: '**Linha Evolutiva**', value: evolutionField, inline: false },
          { name: '**Onde Encontrar**', value: locationsField, inline: false }
        )
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });

    } catch (error) {
      console.error(error);
      const errorMsg = error.response && error.response.data && error.response.data.error
        ? error.response.data.error
        : 'Não foi possível encontrar este Pokémon ou conectar ao servidor do PokeFlaton.';
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
