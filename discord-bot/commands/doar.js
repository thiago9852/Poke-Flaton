const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const economy = require('../economy');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('doar')
    .setDescription('Doa VivillonCoins para outro membro do servidor')
    .addUserOption(option =>
      option.setName('membro')
        .setDescription('Membro que receberá a doação')
        .setRequired(true)
    )
    .addIntegerOption(option =>
      option.setName('quantidade')
        .setDescription('Quantidade de moedas a doar')
        .setRequired(true)
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_ECONOMIA;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    const receiver = interaction.options.getUser('membro');
    const amount = interaction.options.getInteger('quantidade');

    if (receiver.bot) {
      return interaction.reply({ content: '❌ Você não pode doar moedas para bots!', ephemeral: true });
    }

    if (receiver.id === interaction.user.id) {
      return interaction.reply({ content: '❌ Você não pode doar moedas para si mesmo!', ephemeral: true });
    }

    if (amount <= 0) {
      return interaction.reply({ content: '❌ A quantidade a doar deve ser maior que 0!', ephemeral: true });
    }

    await interaction.deferReply();

    try {
      const success = economy.removeCoins(interaction.user.id, amount);
      if (success) {
        const receiverNewBalance = economy.addCoins(receiver.id, receiver.tag, amount);
        const senderNewBalance = economy.getUserCoins(interaction.user.id);

        const embed = new EmbedBuilder()
          .setTitle('💸 Transferência Concluída!')
          .setDescription(`**${interaction.user}** transferiu moedas com sucesso para **${receiver}**!`)
          .setColor(0x3498db)
          .addFields(
            { name: 'Valor', value: `**${amount} VivillonCoins**`, inline: false },
            { name: `Saldo de ${interaction.user.username}`, value: `**${senderNewBalance}** moedas`, inline: true },
            { name: `Saldo de ${receiver.username}`, value: `**${receiverNewBalance}** moedas`, inline: true }
          )
          .setFooter({ text: 'PokeFlaton Bot', iconURL: interaction.client.user.displayAvatarURL() })
          .setTimestamp();

        await interaction.editReply({ embeds: [embed] });
      } else {
        await interaction.editReply({ content: '❌ Você não tem VivillonCoins suficientes para realizar esta doação!' });
      }
    } catch (err) {
      console.error(err);
      await interaction.editReply({ content: '❌ Ocorreu um erro ao processar a doação.' });
    }
  }
};
