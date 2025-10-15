#!/usr/bin/env bash

# get the user that owns our app here
APP_USER=`stat -c '%U' $PWD`;

# make sure we own it
chown -R $APP_USER:$APP_USER $PWD*;

# reset permissions first
find $PWD -type d -exec chmod 700 {} \;
find $PWD -type f -exec chmod 600 {} \;
chmod +x refresh.sh;

# make sure composer will not throw up on us...
export COMPOSER_ALLOW_SUPERUSER=1;

# update all packages
composer update;

# dump the composer autoloader and force it to regenerate
composer dumpautoload -o -n;

# Reinstall node_modules with correct permissions
#rm -rf $PWD/node_modules && npm install && npx update-browserslist-db@latest

# now refresh NPM
#npm run build;

# just inn case php is caching
service php8.4-fpm restart && service nginx reload

# clear out our redis cache
redis-cli flushall
