const fs = require('fs');
const path = require('path');

const DATA_FILE = path.join(__dirname, 'data', 'coins.json');

// Garante que o diretório data exista
if (!fs.existsSync(path.dirname(DATA_FILE))) {
  fs.mkdirSync(path.dirname(DATA_FILE), { recursive: true });
}

function loadData() {
  if (!fs.existsSync(DATA_FILE)) {
    return {};
  }
  try {
    const raw = fs.readFileSync(DATA_FILE, 'utf8');
    return JSON.parse(raw);
  } catch (e) {
    console.error('Erro ao carregar dados de economia:', e);
    return {};
  }
}

function saveData(data) {
  try {
    fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2), 'utf8');
  } catch (e) {
    console.error('Erro ao salvar dados de economia:', e);
  }
}

function getUserCoins(userId) {
  const data = loadData();
  return data[userId]?.coins || 0;
}

function addCoins(userId, username, amount) {
  const data = loadData();
  if (!data[userId]) {
    data[userId] = { coins: 0, lastDaily: null, username: username };
  }
  data[userId].coins += amount;
  data[userId].username = username;
  saveData(data);
  return data[userId].coins;
}

function removeCoins(userId, amount) {
  const data = loadData();
  if (!data[userId]) return false;
  if (data[userId].coins < amount) return false;
  data[userId].coins -= amount;
  saveData(data);
  return true;
}

function getLeaderboard() {
  const data = loadData();
  return Object.entries(data)
    .map(([userId, user]) => ({ userId, ...user }))
    .sort((a, b) => b.coins - a.coins);
}

function claimDaily(userId, username) {
  const data = loadData();
  if (!data[userId]) {
    data[userId] = { coins: 0, lastDaily: null, username: username };
  }
  
  const now = new Date();
  const lastDailyStr = data[userId].lastDaily;
  if (lastDailyStr) {
    const lastDaily = new Date(lastDailyStr);
    const diffMs = now - lastDaily;
    const diffHrs = diffMs / (1000 * 60 * 60);
    if (diffHrs < 24) {
      const remainingMs = (24 - diffHrs) * 60 * 60 * 1000;
      return { success: false, remainingMs };
    }
  }
  
  const reward = Math.floor(Math.random() * 101) + 50; // 50 a 150 moedas
  data[userId].coins += reward;
  data[userId].lastDaily = now.toISOString();
  data[userId].username = username;
  saveData(data);
  return { success: true, reward, total: data[userId].coins };
}

function addBattleReward(userId, username, isWinner) {
  const data = loadData();
  if (!data[userId]) {
    data[userId] = { coins: 0, lastDaily: null, lastBattleReward: null, username: username };
  }
  
  const now = new Date();
  const lastBattleRewardStr = data[userId].lastBattleReward;
  
  if (lastBattleRewardStr) {
    const lastBattleReward = new Date(lastBattleRewardStr);
    const diffMs = now - lastBattleReward;
    const diffHrs = diffMs / (1000 * 60 * 60);
    if (diffHrs < 24) {
      return { rewarded: false, earned: 0, total: data[userId].coins };
    }
  }
  
  const earned = isWinner ? 50 : 10;
  data[userId].coins += earned;
  data[userId].lastBattleReward = now.toISOString();
  data[userId].username = username;
  saveData(data);
  return { rewarded: true, earned, total: data[userId].coins };
}

function addGuessReward(userId, username) {
  const data = loadData();
  if (!data[userId]) {
    data[userId] = { coins: 0, lastDaily: null, lastGuessReward: null, username: username };
  }
  
  const now = new Date();
  const lastGuessRewardStr = data[userId].lastGuessReward;
  
  if (lastGuessRewardStr) {
    const lastGuessReward = new Date(lastGuessRewardStr);
    const diffMs = now - lastGuessReward;
    const diffHrs = diffMs / (1000 * 60 * 60);
    if (diffHrs < 24) {
      return { rewarded: false, earned: 0, total: data[userId].coins };
    }
  }
  
  const earned = 15;
  data[userId].coins += earned;
  data[userId].lastGuessReward = now.toISOString();
  data[userId].username = username;
  saveData(data);
  return { rewarded: true, earned, total: data[userId].coins };
}

function recordGuessWin(userId, username) {
  const data = loadData();
  if (!data[userId]) {
    data[userId] = { coins: 0, lastDaily: null, monthlyGuesses: {}, username: username };
  }
  if (!data[userId].monthlyGuesses) {
    data[userId].monthlyGuesses = {};
  }
  
  const now = new Date();
  const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  
  data[userId].monthlyGuesses[monthKey] = (data[userId].monthlyGuesses[monthKey] || 0) + 1;
  data[userId].username = username;
  saveData(data);
  return data[userId].monthlyGuesses[monthKey];
}

function getMonthlyLeaderboard(monthKey) {
  const data = loadData();
  const now = new Date();
  const key = monthKey || `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  
  return Object.entries(data)
    .filter(([userId, user]) => userId !== '_metadata' && user.monthlyGuesses && user.monthlyGuesses[key])
    .map(([userId, user]) => ({
      userId,
      username: user.username,
      wins: user.monthlyGuesses[key]
    }))
    .sort((a, b) => b.wins - a.wins);
}

function checkMonthlyAwards(client) {
  const data = loadData();
  if (!data._metadata) {
    data._metadata = { awardedMonths: [] };
  }
  
  const now = new Date();
  // Pegar o mês anterior
  const prevMonth = new Date(now.getFullYear(), now.getMonth() - 1, 1);
  const prevMonthKey = `${prevMonth.getFullYear()}-${String(prevMonth.getMonth() + 1).padStart(2, '0')}`;
  
  if (data._metadata.awardedMonths.includes(prevMonthKey)) {
    return; // Já premiado
  }
  
  let winnerId = null;
  let maxWins = 0;
  
  for (const [userId, user] of Object.entries(data)) {
    if (userId === '_metadata') continue;
    const wins = user.monthlyGuesses?.[prevMonthKey] || 0;
    if (wins > maxWins) {
      maxWins = wins;
      winnerId = userId;
    }
  }
  
  if (winnerId && maxWins > 0) {
    data[winnerId].coins += 500;
    data._metadata.awardedMonths.push(prevMonthKey);
    saveData(data);
    console.log(`[Economy] Concedido prêmio mensal de 500 moedas para o usuário ${winnerId} pelo mês ${prevMonthKey} (Acertos: ${maxWins})`);
    
    try {
      client.users.fetch(winnerId).then(user => {
        user.send(`🏆 **Parabéns!** Você ficou em **1º lugar** no ranking mensal de acertos do mês **${prevMonthKey}** com **${maxWins}** acertos! Você recebeu **500 VivillonCoins** de prêmio! 🎉`).catch(e => {});
      });
    } catch (e) {
      console.error('Falha ao notificar o vencedor do ranking mensal:', e);
    }
  } else {
    // Sem vencedor ou nenhum acerto no mês anterior
    data._metadata.awardedMonths.push(prevMonthKey);
    saveData(data);
  }
}

module.exports = {
  getUserCoins,
  addCoins,
  removeCoins,
  getLeaderboard,
  claimDaily,
  addBattleReward,
  addGuessReward,
  recordGuessWin,
  getMonthlyLeaderboard,
  checkMonthlyAwards
};


