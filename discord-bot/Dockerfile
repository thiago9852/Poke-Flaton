FROM node:20-slim

# Instalar dependências necessárias para o Puppeteer e o Chromium funcionar no Linux
RUN apt-get update && apt-get install -y \
    chromium \
    fonts-ipafont-gothic \
    fonts-wqy-zenhei \
    fonts-thai-tlwg \
    fonts-kacst \
    fonts-freefont-ttf \
    libxss1 \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

# Evitar o download do Chromium do Puppeteer (economiza espaço) e usar o do sistema
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true \
    PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium

WORKDIR /usr/src/app

COPY package*.json ./
RUN npm install --omit=dev

COPY . .

# Criar pasta data se não existir para o volume
RUN mkdir -p data

CMD [ "npm", "start" ]
