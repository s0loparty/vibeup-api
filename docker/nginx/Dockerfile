FROM nginx:alpine

# Копируем собранную статику (Vite)
COPY public /var/www/html/public
# Копируем конфиг Nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Права (nginx работает от nginx user)
RUN chown -R nginx:nginx /var/www/html/public && \
    chmod -R 755 /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
