echo dependencies
apt-get install apache2 php5 php5-sqlite php5-curl sqlite3
echo
echo change port 80 to 8080 in
echo nano /etc/apache2/ports.conf
echo nano /etc/apache2/sites-enabled/000-default
echo /etc/init.d/apache2 restart
echo
echo mv b.e. folder into www-folder 
echo mv hz-blockexplorer/hz-blockexplorer /var/www/hzbe 
echo
echo backend must be writeable by www-data
echo mv hz-explorer-backend /opt
echo sqlite3 /opt/hz-explorer-backend/explorer.db < /opt/hz-explorer-backend/db.txt
echo chown www-data /opt/hz-explorer-backend -R
echo
echo create and edit the config file
echo cp /var/www/hzbe/config/config.php.default /var/www/hzbe/config/config.php
echo nano /var/www/hzbe/config/config.php
echo "$config['dbpath']   = '/opt/hz-explorer-backend/';"
echo
