FROM node:18-alpine AS build

RUN apk add --no-cache openssl
RUN openssl req -x509 -nodes -days 365 \
    -subj "/C=CA/ST=QC/O=Miniboard, Inc./CN=nginx" \
    -addext "subjectAltName=DNS:nginx" \
    -newkey rsa:2048 \
    -keyout /etc/ssl/private/nginx-selfsigned.key \
    -out /etc/ssl/certs/nginx-selfsigned.crt

RUN mkdir -p /app
WORKDIR /app
COPY package.json webpack.config.js /app/
RUN yarn
COPY public /app/public/
RUN yarn run build


FROM nginx:1.25.2-alpine

# copy self signed ssl certs (OK for local and cloudflare/behind ssl-terminated proxy)
COPY --from=build /etc/ssl/private/nginx-selfsigned.key /etc/ssl/private/nginx-selfsigned.key
COPY --from=build /etc/ssl/certs/nginx-selfsigned.crt /etc/ssl/certs/nginx-selfsigned.crt

# copy nginx conf & site confs
COPY docker/nginx/nginx.conf.template /etc/nginx/
COPY docker/nginx/conf.d/default.conf.template /etc/nginx/templates/

# copy nginx entrypoint scripts
COPY docker/nginx/entrypoint/25-envsubst-on-templates.sh /docker-entrypoint.d/
RUN chmod +x /docker-entrypoint.d/*.sh

# copy app files
COPY --from=build /app/public /app/public
