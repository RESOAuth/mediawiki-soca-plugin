FROM docker.io/library/composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader
COPY lib/ lib/
COPY src/ src/
RUN composer dump-autoload --no-dev --classmap-authoritative
FROM docker.io/library/mediawiki@sha256:76b3d47b50c4e4a78f82e814917421cb62c49b0c56b4a0f981cd74e2bddd10ac
ADD https://github.com/wikimedia/mediawiki-extensions-PluggableAuth/archive/a3d41e99bbc378f6ca38f8b14e7802e9addbf575.tar.gz /tmp/pluggableauth.tar.gz
RUN mkdir -p extensions/PluggableAuth && tar -xzf /tmp/pluggableauth.tar.gz --strip-components=1 -C extensions/PluggableAuth && rm /tmp/pluggableauth.tar.gz
COPY . extensions/SOCA/
COPY --from=dependencies /app/vendor/ extensions/SOCA/vendor/
COPY demo/entrypoint.sh /usr/local/bin/soca-demo
RUN chmod +x /usr/local/bin/soca-demo && a2disconf other-vhosts-access-log && \
    sed -i '/CustomLog/d' /etc/apache2/sites-available/000-default.conf && \
    printf '%s\n' 'LogFormat "%h %l %u %t \"%m %U %H\" %>s %b" soca_redacted' 'CustomLog /proc/self/fd/1 soca_redacted' > /etc/apache2/conf-enabled/soca-log.conf
ENV SOCA_PRIVATE_KEY_FILE=/run/secrets/soca-private.pem SOCA_DEMO_ORIGIN=https://wiki.localhost:8443
ENTRYPOINT ["soca-demo"]
CMD ["apache2-foreground"]
