const { Client, GatewayIntentBits, PermissionFlagsBits, Collection } = require('discord.js');
const fs = require('fs');
const path = require('path');
require('dotenv').config();

const client = new Client({
  intents: [
    GatewayIntentBits.Guilds,
    GatewayIntentBits.GuildMessages,
    GatewayIntentBits.MessageContent
  ]
});

let countingState = null;
const economy = require('./economy');

// Carregar Comandos do Discord dinamicamente da pasta commands/
client.commands = new Collection();
const commandsPath = path.join(__dirname, 'commands');
const commandFiles = fs.readdirSync(commandsPath).filter(file => file.endsWith('.js'));

for (const file of commandFiles) {
  const filePath = path.join(commandsPath, file);
  const command = require(filePath);
  if ('data' in command && 'execute' in command) {
    client.commands.set(command.data.name, command);
  } else {
    console.warn(`[WARNING] O comando em ${filePath} está faltando uma propriedade "data" ou "execute" requerida.`);
  }
}

client.once('ready', async () => {
  console.log(`Bot conectado com sucesso como ${client.user.tag}!`);
  await initializeCountingState();
  economy.checkMonthlyAwards(client);
});

// Evento de Comandos Slash (/)
client.on('interactionCreate', async interaction => {
  if (!interaction.isChatInputCommand()) return;

  const command = interaction.client.commands.get(interaction.commandName);
  if (!command) {
    console.error(`Nenhum comando correspondente a ${interaction.commandName} foi encontrado.`);
    return;
  }

  try {
    await command.execute(interaction);
  } catch (error) {
    console.error(error);
    const errorReply = { content: '❌ Ocorreu um erro ao tentar executar este comando no bot!', ephemeral: true };
    if (interaction.replied || interaction.deferred) {
      await interaction.followUp(errorReply);
    } else {
      await interaction.reply(errorReply);
    }
  }
});

// Evento de Mensagens Gerais e Moderações (como Canal de Contar)
client.on('messageCreate', async message => {
  if (message.author.bot) return; // Ignorar mensagens de bots

  // 0. Moderação do canal de contar (COUNTING_CHANNEL)
  const countingChannelId = process.env.COUNTING_CHANNEL;
  if (countingChannelId && message.channelId === countingChannelId) {
    const text = message.content.trim();
    
    // Se não for um número inteiro positivo, exclui imediatamente
    if (!/^\d+$/.test(text)) {
      try {
        await message.delete();
      } catch (err) {
        console.error('Erro ao deletar mensagem não numérica no canal de contar:', err.message);
      }
      return;
    }

    const val = parseInt(text, 10);

    // Garantir que o estado esteja carregado
    if (!countingState) {
      await initializeCountingState();
    }

    const expected = countingState.number + 1;
    const isNext = (val === expected);
    const isDifferentUser = (countingState.userId !== message.author.id);

    if (isNext && isDifferentUser) {
      // Válido! Atualiza o estado
      countingState = {
        number: val,
        userId: message.author.id
      };
      
      // Concede 1 VivillonCoin
      economy.addCoins(message.author.id, message.author.tag, 1);
      
      try {
        await message.react('✅');
      } catch (e) {}
    } else {
      // Inválido! Exclui
      try {
        await message.delete();
      } catch (err) {
        console.error('Erro ao deletar mensagem inválida no canal de contar:', err.message);
      }
    }
    return;
  }

  // 1. Moderação de canais que aceitam APENAS comandos (COMMAND_CHANNELS)
  const commandChannels = (process.env.COMMAND_CHANNELS || '')
    .split(',')
    .map(id => id.trim())
    .filter(id => id.length > 0);

  if (commandChannels.includes(message.channelId)) {
    try {
      await message.delete();
      const reply = await message.channel.send(` ${message.author}, neste canal apenas comandos de barra (**/**) são permitidos!`);
      setTimeout(() => reply.delete().catch(() => {}), 5000);
    } catch (err) {
      console.error('Erro ao deletar mensagem no canal de comandos:', err.message);
    }
    return;
  }

  // 2. Moderação de canais que aceitam APENAS imagens (IMAGE_ONLY_CHANNELS)
  const isThread = message.channel.isThread && message.channel.isThread();
  if (isThread) return; // Permite texto normal dentro de tópicos (threads)

  const imageOnlyChannels = (process.env.IMAGE_ONLY_CHANNELS || '')
    .split(',')
    .map(id => id.trim())
    .filter(id => id.length > 0);

  if (imageOnlyChannels.includes(message.channelId)) {
    // Verificar se a mensagem possui anexos
    const hasAttachments = message.attachments.size > 0;
    
    if (!hasAttachments) {
      try {
        await message.delete();
        const reply = await message.channel.send(` ${message.author}, neste canal apenas **imagens/anexos** são permitidos! Para conversar, crie um **Tópico (Thread)** na imagem.`);
        setTimeout(() => reply.delete().catch(() => {}), 7000);
      } catch (err) {
        console.error('Erro ao deletar mensagem no canal de imagens:', err.message);
      }
    }
  }
});

async function initializeCountingState() {
  const channelId = process.env.COUNTING_CHANNEL;
  if (!channelId) {
    console.log('[Counting] Canal de contar não configurado no .env.');
    return;
  }
  try {
    const channel = await client.channels.fetch(channelId);
    if (!channel) {
      console.warn(`[Counting] Canal de contar com ID ${channelId} não foi encontrado.`);
      return;
    }
    console.log(`[Counting] Inicializando contador no canal #${channel.name}...`);
    // Buscar as últimas 50 mensagens para encontrar o último número válido
    const messages = await channel.messages.fetch({ limit: 50 });
    for (const [id, message] of messages) {
      if (message.author.bot) continue;
      const text = message.content.trim();
      if (/^\d+$/.test(text)) {
        const num = parseInt(text, 10);
        countingState = {
          number: num,
          userId: message.author.id
        };
        console.log(`[Counting] Estado inicializado: Número ${num} por <@${message.author.id}>`);
        return;
      }
    }
    // Se não encontrou nenhuma mensagem numérica, inicializa em 0
    countingState = { number: 0, userId: null };
    console.log('[Counting] Nenhuma mensagem numérica encontrada no histórico. Iniciando em 0.');
  } catch (err) {
    console.error('[Counting] Erro ao carregar mensagens do canal de contar:', err);
  }
}

client.login(process.env.DISCORD_TOKEN);
