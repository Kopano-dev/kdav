# kDAV - Kopano CalDAV & CardDAV

Implements CalDAV and CardDAV support for Kopano 8.3 or newer.

As DAV server [SabreDAV](http://http://sabre.io/dav) is used.

## License

This project is licensed under the terms of the [GNU Affero General Public
License, version 3](http://www.gnu.org/licenses/agpl-3.0.html).

## Install

### Basics

The described method is intended for development and testing.
It's simplistic and aims to work fast and easy.
How this code will be distributed to end users still needs to be defined.

You should have got a checkout/clone of the repository. 
The main dependencies are installed via Composer, to do that, first get composer itself.
Installation instructions can be found 
[here](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).
It's recommend to install composer locally into the main kDAV directory. Then just do

```
# ./composer.phar install
```

### kDAV configuration

All configs are handled in the `config.php` file.
Adjust `MAPI_SERVER` to connect to your Kopano instance.
The `DAV_ROOT_URI` parameter must match your webserver configuration,
so that it points directly to the `server.php` file.

This is the simplest way to setup, running at port 8843:

```
<VirtualHost *:80>
    DocumentRoot /your-kdav-working-directory/kdav
    ServerName develop.local

    <Directory /your-kdav-working-directory/kdav>
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined

    RewriteEngine On
    # This makes every request go to server.php
    RewriteRule ^/(.*)$ /server.php [L]

    # Output buffering needs to be off, to prevent high memory usage
    php_flag output_buffering off

    # This is also to prevent high memory usage
    php_flag always_populate_raw_post_data off

    # This is almost a given, but magic quotes is *still* on on some
    # linux distributions
    php_flag magic_quotes_gpc off

    # SabreDAV is not compatible with mbstring function overloading
    php_flag mbstring.func_overload off

</VirtualHost>
```

SSL is strongly recommended if you use real passwords.

### log4php configuration
kDAV uses Apache's log4php for logging. The configuration file is `log4php.xml`
located in the root folder.
The default log location is `/var/log/kdav/kdav.log`.
It is required to create the log directory first:

```
mkdir -p /var/log/kdav
```

and grant permissions for the webserver user to write to that directory e.g.

```
chown -R www-data. /var/log/kdav
```

The default log4php configuration doesn't rotate the log file, so it might be a
good idea to configure logrotate utility for kdav.log e.g by creating
```/etc/logrotate.d/kdav``` with the following content:

```
/var/log/kdav/*.log {
    size 1k
    create www-data www-data
    compress
    rotate 4
}
```

## Access

As first step, point your webbrowser to:

```
http://develop.local/
```
    
Login with the username + password of a valid Kopano user.

If you don't get the Sabre/Dav overview, check your webserver error logfiles.

In your CalDAV client, set the server URL to

```
http://develop.local/calendars/<user>/Calendar/
```

The IP address can of course also be used.

## Reporting and Development

Atm development is done on z-hub.io in the KDAV project.

### PHPUnit tests

kDAV uses PHPUnit for unit tests. It's installed by composer. The executable
binary is ```vendor/phpunit/phpunit/phpunit```, so creating a symlink in
the project's folder makes it easier:

```
ln -s vendor/phpunit/phpunit/phpunit phpunit
```

In order to run all the test execute:
```
phpunit tests
```

In order to run a specific test pass a path to a specific class as parameter, e.g.:
```
phpunit tests\KopanoCardDavBackendTest
```