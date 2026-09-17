# LeadFlow Form Partners worker (Render "Background Worker" or any VPS)
FROM node:20-bookworm-slim

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev \
 && npx playwright install --with-deps chromium \
 && rm -rf /var/lib/apt/lists/*

COPY . .

CMD ["node", "index.js"]
