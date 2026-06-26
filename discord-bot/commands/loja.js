const { SlashCommandBuilder, EmbedBuilder } = require('discord.js');
const economy = require('../economy');

const shopItems = {
  cargo_elite: {
    name: 'Cargo: Treinador de Elite',
    roleName: 'Treinador de Elite',
    price: 500,
    description: 'Dá a você um cargo de destaque dourado no servidor.'
  },
  cargo_mestre: {
    name: 'Cargo: Mestre Pokémon',
    roleName: 'Mestre Pokémon',
    price: 1200,
    description: 'Dá a você o cargo máximo de prestígio no servidor.'
  },
  tag_shiny: {
    name: 'Cargo: Treinador Shiny ✨',
    roleName: 'Treinador Shiny',
    price: 300,
    description: 'Dá a você um cargo brilhante com a insígnia Shiny.'
  }
};

module.exports = {
  data: new SlashCommandBuilder()
    .setName('loja')
    .setDescription('Acesse a loja virtual de VivillonCoins')
    .addSubcommand(subcommand =>
      subcommand
        .setName('ver')
        .setDescription('Exibe os itens disponíveis para compra')
    )
    .addSubcommand(subcommand =>
      subcommand
        .setName('comprar')
        .setDescription('Compre um item da loja')
        .addStringOption(option =>
          option.setName('item')
            .setDescription('O item que deseja comprar')
            .setRequired(true)
            .addChoices(
              { name: 'Cargo: Treinador de Elite (500 moedas)', value: 'cargo_elite' },
              { name: 'Cargo: Mestre Pokémon (1200 moedas)', value: 'cargo_mestre' },
              { name: 'Cargo: Treinador Shiny (300 moedas)', value: 'tag_shiny' }
            )
        )
    ),
  async execute(interaction) {
    const allowedChannelId = process.env.ALL_COMMANDS || process.env.CHANNEL_ECONOMIA;
    if (allowedChannelId && interaction.channelId !== allowedChannelId) {
      return interaction.reply({
        content: `❌ Este comando só pode ser utilizado no canal <#${allowedChannelId}>.`,
        ephemeral: true
      });
    }

    await interaction.deferReply();
    const subcommand = interaction.options.getSubcommand();

    if (subcommand === 'ver') {
      const embed = new EmbedBuilder()
        .setTitle('🏪 Loja de VivillonCoins')
        .setDescription('Use as moedas que você acumulou jogando e contando para comprar recompensas exclusivas!')
        .setColor(0x2ecc71)
        .setThumbnail('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/items/poke-ball.png')
        .addFields(
          Object.entries(shopItems).map(([id, item]) => ({
            name: `🔹 ${item.name} — 💰 ${item.price} VivillonCoins`,
            value: `${item.description}\n*ID para compra:* \`/loja comprar item:${id}\``,
            inline: false
          }))
        )
        .setFooter({ text: 'PokeFlaton Bot' })
        .setTimestamp();

      return interaction.editReply({ embeds: [embed] });
    }

    if (subcommand === 'comprar') {
      const itemId = interaction.options.getString('item');
      const item = shopItems[itemId];

      if (!item) {
        return interaction.editReply({ content: '❌ Item inválido selecionado.' });
      }

      const balance = economy.getUserCoins(interaction.user.id);
      if (balance < item.price) {
        return interaction.editReply({ 
          content: `❌ Saldo insuficiente! Você tem **${balance}** VivillonCoins, mas o item custa **${item.price}**.` 
        });
      }

      const member = interaction.member;
      const roleName = item.roleName;
      
      // Buscar se o membro já tem o cargo
      const hasRole = member.roles.cache.some(r => r.name === roleName);
      if (hasRole) {
        return interaction.editReply({ content: `❌ Você já possui o cargo **${roleName}**!` });
      }

      // Procurar cargo no servidor
      let role = interaction.guild.roles.cache.find(r => r.name === roleName);
      if (!role) {
        try {
          // Tentar criar se não existir
          role = await interaction.guild.roles.create({
            name: roleName,
            color: itemId === 'cargo_elite' ? '#f1c40f' : (itemId === 'cargo_mestre' ? '#e74c3c' : '#3498db'),
            reason: 'Compra na loja de VivillonCoins'
          });
        } catch (err) {
          console.error(err);
          return interaction.editReply({ 
            content: `❌ O cargo **${roleName}** não existe no servidor e o bot não tem permissões para criá-lo. Peça para um administrador criar o cargo manualmente.` 
          });
        }
      }

      try {
        // Deduzir moedas
        const success = economy.removeCoins(interaction.user.id, item.price);
        if (!success) {
          return interaction.editReply({ content: '❌ Erro ao deduzir saldo.' });
        }

        // Adicionar cargo
        await member.roles.add(role);

        const newBalance = economy.getUserCoins(interaction.user.id);

        const embed = new EmbedBuilder()
          .setTitle('🎉 Compra Realizada!')
          .setDescription(`Parabéns! Você adquiriu o cargo **${roleName}** com sucesso!\nO cargo já foi aplicado ao seu perfil.`)
          .setColor(0x2ecc71)
          .addFields(
            { name: 'Item', value: item.name, inline: true },
            { name: 'Custo', value: `💰 ${item.price} VivillonCoins`, inline: true },
            { name: 'Novo Saldo', value: `💰 ${newBalance} VivillonCoins`, inline: true }
          )
          .setFooter({ text: 'PokeFlaton Bot' })
          .setTimestamp();

        await interaction.editReply({ embeds: [embed] });

      } catch (err) {
        console.error(err);
        await interaction.editReply({ 
          content: `❌ Ocorreu um erro ao tentar aplicar o cargo. Por favor, verifique se o cargo do Bot está acima do cargo **${roleName}** na lista de cargos do servidor.` 
        });
      }
    }
  }
};
