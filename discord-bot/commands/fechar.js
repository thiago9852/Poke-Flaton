const { SlashCommandBuilder, EmbedBuilder, PermissionFlagsBits } = require('discord.js');

module.exports = {
  data: new SlashCommandBuilder()
    .setName('fechar')
    .setDescription('Fecha o ticket, envia a resposta ao usuário por DM e deleta o canal')
    .addStringOption(option =>
      option.setName('resposta')
        .setDescription('A resposta final a ser enviada no privado do usuário')
        .setRequired(true)
    ),
  async execute(interaction) {
    // Verificar se o membro tem permissão de gerenciar canais (Staff/Admin)
    if (!interaction.member.permissions.has(PermissionFlagsBits.ManageChannels)) {
      return interaction.reply({ 
        content: 'Você não tem permissão para fechar este ticket (requer a permissão "Gerenciar Canais").', 
        ephemeral: true 
      });
    }

    // Verificar se é de fato um canal de ticket (ex: começa com "ticket-")
    if (!interaction.channel.name.startsWith('ticket-')) {
      return interaction.reply({ 
        content: 'Este comando só pode ser utilizado em canais de ticket (nomes que começam com "ticket-").', 
        ephemeral: true 
      });
    }

    await interaction.deferReply();
    const resposta = interaction.options.getString('resposta');

    try {
      // 1. Localizar quem abriu o ticket
      const opener = await findTicketOpener(interaction.channel);

      let dmSent = false;
      if (opener) {
        try {
          // 2. Criar embed com a resposta final e enviar para o usuário via DM
          const dmEmbed = new EmbedBuilder()
            .setTitle(`📬 Seu Ticket foi Respondido!`)
            .setDescription(`Olá! O seu ticket no servidor **${interaction.guild.name}** foi respondido por um administrador.`)
            .setColor(0x2ecc71)
            .addFields(
              { name: 'Canal do Ticket', value: `#${interaction.channel.name}`, inline: true },
              { name: 'Respondido por', value: `${interaction.user.tag}`, inline: true },
              { name: 'Resposta do Administrador', value: `\`\`\`\n${resposta}\n\`\`\``, inline: false }
            )
            .setFooter({ text: 'PokeFlaton Bot' })
            .setTimestamp();

          await opener.send({ embeds: [dmEmbed] });
          dmSent = true;
        } catch (dmErr) {
          console.error(`Não foi possível enviar DM para o usuário ${opener.tag}:`, dmErr.message);
        }
      }

      // 3. Responder no canal do ticket
      if (dmSent) {
        await interaction.editReply({ 
          content: `✅ Resposta enviada com sucesso no privado de **${opener.tag}**!\nVocê já pode fechar o canal usando o **Ticket Tool** para gerar a transcrição.` 
        });
      } else {
        const userText = opener ? `**${opener.tag}** (está com o privado fechado)` : 'Usuário não localizado';
        await interaction.editReply({ 
          content: `A resposta **não** pôde ser enviada no privado de: ${userText}.\nEnvie a resposta manualmente antes de fechar o ticket no **Ticket Tool**.` 
        });
      }

    } catch (err) {
      console.error(err);
      await interaction.editReply({ content: ` Ocorreu um erro ao tentar processar o fechamento do ticket: ${err.message}` });
    }
  }
};

async function findTicketOpener(channel) {
  // Método 1: Procurar membro pelo nome do canal (ex: ticket-username)
  const username = channel.name.replace('ticket-', '').toLowerCase();
  const memberByName = channel.guild.members.cache.find(m => m.user.username.toLowerCase() === username);
  if (memberByName) return memberByName.user;

  // Método 2: Procurar nos Overwrites de permissão do canal (tipo 1 = Membro individual)
  const memberOverwrites = channel.permissionOverwrites.cache.filter(o => o.type === 1);
  for (const [userId, overwrite] of memberOverwrites) {
    if (userId === channel.client.user.id) continue;
    try {
      const member = await channel.guild.members.fetch(userId);
      if (member && !member.user.bot && !member.permissions.has(PermissionFlagsBits.Administrator)) {
        return member.user;
      }
    } catch (e) {}
  }

  // Método 3: Pegar o primeiro usuário que mandou mensagem no histórico (que não seja bot)
  try {
    const messages = await channel.messages.fetch({ limit: 50 });
    const sorted = [...messages.values()].sort((a, b) => a.createdTimestamp - b.createdTimestamp);
    const firstUserMsg = sorted.find(msg => !msg.author.bot);
    if (firstUserMsg) return firstUserMsg.author;
  } catch (e) {}

  return null;
}
