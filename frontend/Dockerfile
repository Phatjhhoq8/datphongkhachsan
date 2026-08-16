FROM node:22-alpine

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY index.html vite.config.js ./
COPY public ./public
COPY scripts ./scripts
COPY src ./src
COPY test ./test

ARG VITE_API_BASE_URL=http://localhost:8000/api/v1
ARG VITE_SOCKET_URL=http://localhost:3001
ENV VITE_API_BASE_URL=$VITE_API_BASE_URL
ENV VITE_SOCKET_URL=$VITE_SOCKET_URL

RUN npm run build

EXPOSE 3000

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "3000"]
