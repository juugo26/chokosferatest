<<<<<<< HEAD
FROM node:18-alpine
WORKDIR /app

# Install dependencies
COPY package.json package-lock.json* ./
RUN npm ci --only=production || npm install --only=production

# Copy app
COPY . .

ENV NODE_ENV=production
EXPOSE 3000

CMD ["node", "server.js"]
=======
FROM node:18-alpine
WORKDIR /app

# Install dependencies
COPY package.json package-lock.json* ./
RUN npm ci --only=production || npm install --only=production

# Copy app
COPY . .

ENV NODE_ENV=production
EXPOSE 3000

CMD ["node", "server.js"]
>>>>>>> origin/darkyami
