FROM nginx:1.27-alpine

RUN apk add --no-cache openssl

COPY docker/prod/nginx.conf /etc/nginx/templates/default.conf.template
COPY docker/prod/proxy-entrypoint.sh /usr/local/bin/book-keeper-proxy-entrypoint

RUN chmod +x /usr/local/bin/book-keeper-proxy-entrypoint

ENTRYPOINT ["book-keeper-proxy-entrypoint"]
CMD ["nginx", "-g", "daemon off;"]
