const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL, typeColors, typeEmojis } = require('../config');
const economy = require('../economy');

const activeGames = new Set();

module.exports = {
  data: new SlashCommandBuilder()
    .setName('jogar')
    .setDescription('Inicia um jogo de adivinhar o Pokémon (Quem é esse Pokémon?)'),
  async execute(interaction) {
    if (activeGames.has(interaction.channelId)) {
      return interaction.reply({
        content: '❌ Já existe um jogo em andamento neste canal! Aguarde o fim da rodada atual.',
        ephemeral: true
      });
    }

    await interaction.deferReply();

    try {
      const response = await axios.get(`${API_BASE_URL}/api/discord/game/random`);
      const pokemon = response.data;

      activeGames.add(interaction.channelId);

      const primaryType = pokemon.types[0];
      const embedColor = typeColors[primaryType] || 0x3498db;
      const formattedTypes = pokemon.types.map(t => typeEmojis[t] || t).join(', ');

      const stats = pokemon.stats;
      const statsField = [
        `**HP:** ${stats.hp}`,
        `**Ataque:** ${stats.attack}`,
        `**Defesa:** ${stats.defense}`,
        `**Atq. Esp.:** ${stats['special-attack']}`,
        `**Def. Esp.:** ${stats['special-defense']}`,
        `**Velocidade:** ${stats.speed}`
      ].join('\n');

      const embed = new EmbedBuilder()
        .setTitle('🎮 Quem é esse Pokémon? / Who\'s That Pokémon?')
        .setDescription(`Adivinhe o Pokémon baseado nas informações abaixo! Você tem **30 segundos**!\n\n**Dica da Pokédex:**\n${pokemon.description}`)
        .setColor(embedColor)
        .setThumbnail('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png')
        .addFields(
          { name: '**Geração**', value: `Gen ${pokemon.generation}`, inline: true },
          { name: '**Tipo(s)**', value: formattedTypes, inline: true },
          { name: '**Status Base**', value: statsField, inline: false }
        )
        .setFooter({ text: 'Digite o nome do Pokémon no chat para responder!' })
        .setTimestamp();

      await interaction.editReply({ embeds: [embed] });

      const filter = msg => !msg.author.bot;
      const collector = interaction.channel.createMessageCollector({ filter, time: 30000 });

      function normalizePokemonName(name) {
        return name
          .toLowerCase()
          .normalize("NFD")
          .replace(/[\u0300-\u036f]/g, "")
          .replace(/[^a-z0-9]/g, "");
      }

      const targetNormalized = normalizePokemonName(pokemon.name);
      let answered = false;

      collector.on('collect', async msg => {
        const guessNormalized = normalizePokemonName(msg.content);
        if (guessNormalized === targetNormalized) {
          answered = true;
          collector.stop('correct');
          
          const currentMonthWins = economy.recordGuessWin(msg.author.id, msg.author.tag);
          const rewardRes = economy.addGuessReward(msg.author.id, msg.author.tag);
          
          const coinsText = rewardRes.rewarded
            ? `💰 Você ganhou **15 VivillonCoins**! (Seu saldo atual: **${rewardRes.total}** moedas)`
            : `⏱️ Limite diário de moedas atingido! (Seu saldo atual: **${rewardRes.total}** moedas)`;
          
          const winEmbed = new EmbedBuilder()
            .setTitle(`🎉 Parabéns!`)
            .setDescription(`**${msg.author}** acertou! O Pokémon era **${pokemon.name.charAt(0).toUpperCase() + pokemon.name.slice(1)}**!\n\n${coinsText}\n🏆 Seus acertos no mês atual: **${currentMonthWins}**`)
            .setColor(0x2ecc71)
            .setImage(pokemon.sprite)
            .setFooter({ text: 'PokeFlaton Bot' })
            .setTimestamp();
            
          await msg.reply({ embeds: [winEmbed] });
        }
      });

      collector.on('end', async (collected, reason) => {
        activeGames.delete(interaction.channelId);
        if (!answered && reason !== 'correct') {
          const timeoutEmbed = new EmbedBuilder()
            .setTitle(`⏱️ Tempo Esgotado!`)
            .setDescription(`Ninguém acertou! O Pokémon era **${pokemon.name.charAt(0).toUpperCase() + pokemon.name.slice(1)}**!`)
            .setColor(0xe74c3c)
            .setImage(pokemon.sprite)
            .setFooter({ text: 'PokeFlaton Bot' })
            .setTimestamp();

          await interaction.channel.send({ embeds: [timeoutEmbed] });
        }
      });

    } catch (error) {
      console.error(error);
      activeGames.delete(interaction.channelId);
      await interaction.editReply({ content: '❌ Ocorreu um erro ao tentar iniciar o jogo.' });
    }
  }
};
