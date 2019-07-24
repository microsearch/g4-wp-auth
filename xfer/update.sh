#! /bin/bash

apt-get update -y
apt-get install unzip -y

cd /var/www/html
cp /var/www/xfer/wp-config.php .

cd plugins
unzip -o /var/www/xfer/error-log-monitor.zip

cd /home
touch php-errors.log
chmod 777 php-errors.log
