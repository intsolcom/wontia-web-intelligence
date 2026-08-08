FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx curl libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql gd

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY . /app

RUN mkdir -p /app/public/assets/uploads /app/cache \
    && chown -R www-data:www-data /app/public/assets/uploads /app/cache \
    && chmod 777 /app/public/assets/uploads /app/cache

EXPOSE 80

CMD sh -c 'php-fpm -D && nginx -g "daemon off;"'
