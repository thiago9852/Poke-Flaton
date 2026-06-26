const { SlashCommandBuilder, EmbedBuilder, ButtonBuilder, ButtonStyle, ActionRowBuilder, ComponentType } = require('discord.js');
const axios = require('axios');
const { API_BASE_URL } = require('../config');
const economy = require('../economy');

const cooldowns = new Map();

module.exports = {
  data: new SlashCommandBuilder()
    .setName('batalhar')
    .setDescription('Desafie outro membro do servidor para uma batalha Pokémon simulada')
    .addUserOption(option =>
      option.setName('oponente')
        .setDescription('O treinador que você deseja desafiar')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.CHANNEL_BATTLE || process.env.ALL_COMMANDS // Usar o mesmo canal restrito se configurado
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    const challenger = interaction.user;
    const opponent = interaction.options.getUser('oponente');

    if (opponent.bot) {
      return interaction.reply({ content: '❌ Você não pode desafiar bots!', ephemeral: true });
    }

    if (opponent.id === challenger.id) {
      return interaction.reply({ content: '❌ Você não pode batalhar contra si mesmo!', ephemeral: true });
    }

    // Verificar Cooldown (3 minutos = 180000ms)
    const now = Date.now();
    const cooldownTime = 180000;
    if (cooldowns.has(challenger.id)) {
      const expirationTime = cooldowns.get(challenger.id) + cooldownTime;
      if (now < expirationTime) {
        const timeLeft = Math.ceil((expirationTime - now) / 1000);
        return interaction.reply({
          content: `⏱️ Você precisa aguardar **${timeLeft} segundos** antes de desafiar alguém novamente.`,
          ephemeral: true
        });
      }
    }

    await interaction.deferReply();

    // Criar botões para o oponente aceitar ou recusar
    const acceptBtn = new ButtonBuilder()
      .setCustomId(`accept_battle_${interaction.id}`)
      .setLabel('Aceitar Batalha')
      .setStyle(ButtonStyle.Success);

    const declineBtn = new ButtonBuilder()
      .setCustomId(`decline_battle_${interaction.id}`)
      .setLabel('Recusar Batalha')
      .setStyle(ButtonStyle.Danger);

    const row = new ActionRowBuilder().addComponents(acceptBtn, declineBtn);

    const inviteEmbed = new EmbedBuilder()
      .setTitle('⚔️ Desafio de Batalha Pokémon!')
      .setDescription(`**${challenger}** desafiou **${opponent}** para uma batalha de Pokémons aleatórios!\n\n**${opponent}**, você tem **30 segundos** para aceitar ou recusar o desafio!`)
      .setColor(0xe74c3c)
      .setFooter({ text: 'O vencedor ganha 50 VivillonCoins, o perdedor ganha 10!' });

    const reply = await interaction.editReply({ embeds: [inviteEmbed], components: [row] });

    // Criar coletor de componentes
    const collector = reply.createMessageComponentCollector({
      componentType: ComponentType.Button,
      time: 30000
    });

    collector.on('collect', async btnInteraction => {
      // Apenas o oponente pode interagir
      if (btnInteraction.user.id !== opponent.id) {
        return btnInteraction.reply({ content: '❌ Você não é o oponente deste desafio!', ephemeral: true });
      }

      if (btnInteraction.customId === `decline_battle_${interaction.id}`) {
        collector.stop('declined');
        return;
      }

      if (btnInteraction.customId === `accept_battle_${interaction.id}`) {
        collector.stop('accepted');
        await btnInteraction.deferUpdate();
      }
    });

    collector.on('end', async (collected, reason) => {
      // Remover os botões da mensagem
      await interaction.editReply({ components: [] });

      if (reason === 'declined') {
        const declineEmbed = new EmbedBuilder()
          .setTitle('❌ Desafio Recusado')
          .setDescription(`**${opponent.username}** recusou o desafio de batalha de **${challenger.username}**.`)
          .setColor(0x7f8c8d);
        return interaction.editReply({ embeds: [declineEmbed] });
      }

      if (reason === 'time') {
        const timeoutEmbed = new EmbedBuilder()
          .setTitle('⏱️ Desafio Expirado')
          .setDescription(`O desafio de batalha expirou porque **${opponent.username}** não respondeu a tempo.`)
          .setColor(0x7f8c8d);
        return interaction.editReply({ embeds: [timeoutEmbed] });
      }

      if (reason === 'accepted') {
        // Iniciar a batalha!
        // Aplicar cooldown ao desafiante
        cooldowns.set(challenger.id, Date.now());

        const battleEmbed = new EmbedBuilder()
          .setTitle('⚔️ Batalha Iniciada!')
          .setDescription('Buscando Pokémons aleatórios para o combate... 🔄')
          .setColor(0x3498db);

        await interaction.editReply({ embeds: [battleEmbed] });

        try {
          // Buscar dois pokemons aleatórios
          const [res1, res2] = await Promise.all([
            axios.get(`${API_BASE_URL}/api/discord/game/random`),
            axios.get(`${API_BASE_URL}/api/discord/game/random`)
          ]);

          const p1 = res1.data;
          const p2 = res2.data;

          const name1 = p1.name.toUpperCase();
          const name2 = p2.name.toUpperCase();

          // Simulação por etapas (turnos)
          // Turno 1
          await new Promise(r => setTimeout(r, 2000));
          const t1Embed = new EmbedBuilder()
            .setTitle('⚔️ Batalha Pokémon - Turno 1')
            .setDescription(`**${challenger.username}** enviou **${name1}**!\n**${opponent.username}** enviou **${name2}**!\n\n🔄 **${name1}** inicia usando um golpe rápido!`)
            .setColor(0xe67e22);
          await interaction.editReply({ embeds: [t1Embed] });

          // Turno 2
          await new Promise(r => setTimeout(r, 2000));
          const t2Embed = new EmbedBuilder()
            .setTitle('⚔️ Batalha Pokémon - Turno 2')
            .setDescription(`**${name1}** causou bom dano!\n\n🔄 **${name2}** contra-ataca com um movimento de força total! O combate está acirrado!`)
            .setColor(0xe67e22);
          await interaction.editReply({ embeds: [t2Embed] });

          // Cálculo do vencedor baseado nos status de HP + ATK + DEF + modificador aleatório
          await new Promise(r => setTimeout(r, 2500));
          const score1 = (p1.stats.hp + p1.stats.attack + p1.stats.defense) * (Math.random() * 0.4 + 0.8);
          const score2 = (p2.stats.hp + p2.stats.attack + p2.stats.defense) * (Math.random() * 0.4 + 0.8);

          let winnerUser, loserUser, winnerPkmn, loserPkmn, winnerSprite;
          if (score1 >= score2) {
            winnerUser = challenger;
            loserUser = opponent;
            winnerPkmn = name1;
            loserPkmn = name2;
            winnerSprite = p1.sprite;
          } else {
            winnerUser = opponent;
            loserUser = challenger;
            winnerPkmn = name2;
            loserPkmn = name1;
            winnerSprite = p2.sprite;
          }

          // Conceder prêmios de economia (Opção A: apenas 1x a cada 24h por usuário)
          const winRes = economy.addBattleReward(winnerUser.id, winnerUser.tag, true);
          const loseRes = economy.addBattleReward(loserUser.id, loserUser.tag, false);

          const winValStr = winRes.rewarded 
            ? `💰 +${winRes.earned} VivillonCoins (Novo saldo: **${winRes.total}**)`
            : `⏱️ Limite diário atingido (Saldo: **${winRes.total}**)`;

          const loseValStr = loseRes.rewarded 
            ? `💰 +${loseRes.earned} VivillonCoins (Novo saldo: **${loseRes.total}**)`
            : `⏱️ Limite diário atingido (Saldo: **${loseRes.total}**)`;

          const resultEmbed = new EmbedBuilder()
            .setTitle('🏆 Fim de Combate!')
            .setDescription(`**${loserPkmn}** desmaiou!\n\n**${winnerUser}** venceu a batalha com seu **${winnerPkmn}**!`)
            .setColor(0x2ecc71)
            .setImage(winnerSprite)
            .addFields(
              { name: `🥇 Vencedor: ${winnerUser.username}`, value: winValStr },
              { name: `🥈 Perdedor: ${loserUser.username}`, value: loseValStr }
            )
            .setFooter({ text: 'Parabéns aos dois treinadores pela batalha!' });

          await interaction.channel.send({ embeds: [resultEmbed] });

        } catch (err) {
          console.error(err);
          await interaction.editReply({ content: '❌ Erro ao simular a batalha. Tente novamente mais tarde.' });
        }
      }
    });
  }
};
