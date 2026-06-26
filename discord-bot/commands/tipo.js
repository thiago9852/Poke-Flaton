const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL, typeColors, typeEmojis } = require('../config');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('tipo')
    .setDescription('Exibe as vantagens, fraquezas, resistências e imunidades de um tipo')
    .addStringOption(option =>
      option.setName('nome')
        .setDescription('Nome do tipo (ex: fogo, água, dragão, dark)')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_POKEMON; // Compartilhar canal do pokemon se configurado
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const typeInput = interaction.options.getString('nome').toLowerCase().trim();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/type/${typeInput}`);
      const data = response.data;

      const typeNameEng = data.name.toLowerCase();
      const embedColor = typeColors[typeNameEng] || 0x3498db;
      
      const relations = data.damage_relations;

      // Helper para formatar lista de tipos
      const formatTypeList = (list) => {
        if (!list || list.length === 0) return 'Nenhum';
        return list.map(item => {
          const name = item.name;
          const emoji = typeEmojis[name] || name;
          return `🔹 ${emoji}`;
        }).join(', ');
      };

      const embed = new EmbedBuilder()
        .setTitle(`📊 Tipo: ${data.name_pt.toUpperCase()} (${data.name})`)
        .setColor(embedColor)
        .setDescription(`Relações de dano para ataques e defesas do tipo **${data.name_pt}**:`)
        .addFields(
          { 
            name: '⚔️ Ataque - Super Efetivo Contra (Dano x2)', 
            value: formatTypeList(relations.double_damage_to), 
            inline: false 
          },
          { 
            name: '🛡️ Defesa - Fraco Contra (Recebe Dano x2 de)', 
            value: formatTypeList(relations.double_damage_from), 
            inline: false 
          },
          { 
            name: '⚔️ Ataque - Pouco Efetivo Contra (Dano x0.5 para)', 
            value: formatTypeList(relations.half_damage_to), 
            inline: false 
          },
          { 
            name: '🛡️ Defesa - Resistente Contra (Recebe Dano x0.5 de)', 
            value: formatTypeList(relations.half_damage_from), 
            inline: false 
          }
        )
        .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
        .setTimestamp();

      // Adicionar imunidades se houverem
      if (relations.no_damage_to && relations.no_damage_to.length > 0) {
        embed.addFields({ 
          name: '⚔️ Ataque - Inútil Contra (Sem Dano para)', 
          value: formatTypeList(relations.no_damage_to), 
          inline: true 
        });
      }
      if (relations.no_damage_from && relations.no_damage_from.length > 0) {
        embed.addFields({ 
          name: '🛡️ Defesa - Imune Contra (Sem Dano de)', 
          value: formatTypeList(relations.no_damage_from), 
          inline: true 
        });
      }

      await interaction.editReply({ embeds: [embed] });

    } catch (error) {
      console.error(error);
      const errorMsg = error.response && error.response.data && error.response.data.error
        ? error.response.data.error
        : 'Não foi possível encontrar este tipo Pokémon.';
      await interaction.editReply({ content: `**Erro:** ${errorMsg}` });
    }
  }
};
