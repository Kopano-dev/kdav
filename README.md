# kDAV - Kopano CalDAV & CardDAV

Implements CalDAV and CardDAV support for Kopano 8.6.2 or newer. Due to
minimal PHP requirements of SabreDAV we recommend the use of PHP 7.0.

As DAV server [SabreDAV](http://sabre.io/dav) is used.

## License

kDAV is free software; you can redistribute it and/or modify it under
the terms of the GNU Affero General Public License as published by the
Free Software Foundation; either version 3 or (at your option) any later
version.

## Install

### Basics

The described method is intended for development and testing. It's
simplistic and aims to work fast and easy. How this code will be
distributed to end users still needs to be defined.

You should have got a checkout/clone of the repository. The main
dependencies are installed via Composer, to do that, first get composer
itself. Installation instructions can be found
[here](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx).
It's recommend to install composer locally into the main kDAV directory.
Then just do

```
# ./composer.phar install
```

### kDAV configuration

All configs are handled in the `config.php` file. Adjust `MAPI_SERVER`
to connect to your Kopano instance. The `DAV_ROOT_URI` parameter must
match your webserver configuration, so that it points directly to the
`server.php` file. The default value can be kept if kDAV runs in the
root of the domain.

This is the simplest way to setup, running with Apache at port 443:

```
<VirtualHost *:443>
    DocumentRoot /your-kdav-working-directory/kdav
    ServerName develop.local

    <Directory /your-kdav-working-directory/kdav>
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined

    RewriteEngine On
    # redirect well-known url http://sabre.io/dav/service-discovery/
    # (redirect may need to be done to the absolute external url)
    RewriteRule ^/.well-known/carddav$ / [R]
    RewriteRule ^/.well-known/caldav$ / [R]
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

    # set higher limits by default
    php_value memory_limit 256M
    php_value max_execution_time 259200

</VirtualHost>
```

Remember to enable `mod_rewrite`.

SSL is strongly recommended if you use real passwords.

### Sync states

kDAV makes use of sqlite to manage sync states. Make sure that your php
version has a module for it.

The default location for the sync states db is
`/var/lib/kopano/kdav/syncstate.db`. This location should be writeable
for the webserver process (e.g. `www-data` on Debian).

### log4php configuration

kDAV uses Apache's log4php for logging. The configuration file is
`log4php.xml` located in the root folder. The default log location is
`/var/log/kdav/kdav.log`. It is required to create the log directory
first:

```
mkdir -p /var/log/kdav
```

and grant permissions for the webserver user to write to that directory
e.g.

```
chown -R www-data. /var/log/kdav
```

The default log4php configuration doesn't rotate the log file, so it
might be a good idea to configure logrotate utility for kdav.log e.g by
creating ```/etc/logrotate.d/kdav``` with the following content:

```
/var/log/kdav/*.log {
    size 1k
    create www-data www-data
    compress
    rotate 4
}
```

kopano-dav also requires some php mapi classes. The easiest is to just
symlink them:

```
ln -s /usr/share/kopano/php/mapi /var/www/kdav
```

## Access

As first step, point your webbrowser to:

```
http://develop.local/
```

Login with the username + password of a valid Kopano user.

If you don't get the Sabre/Dav overview, check your webserver error
logfiles.

In your CalDAV client, set the server URL to

```
http://develop.local/calendars/<user>/Calendar/
```

The IP address can of course also be used.

## Reporting and Development

Development is done at
https://stash.kopano.io/projects/KC/repos/kdav/browse. If you have any
feedback or questions please open up a topic at the [Kopano
forum](https://forum.kopano.io/category/13/development).

### PHPUnit tests

kDAV uses PHPUnit for unit tests. It's installed by composer. The
executable binary is ```vendor/phpunit/phpunit/phpunit```, so creating a
symlink in the project's folder makes it easier:

```
ln -s vendor/phpunit/phpunit/phpunit phpunit
```

In order to run all the test execute:
```
./phpunit tests
```

In order to run a specific test pass a path to a specific class as
parameter, e.g.:

```
./phpunit tests\KopanoCardDavBackendTest
```

## Known Issues

- there were reports about syncing on Apple devices taking a very long
time. (Tests with Thunderbird/Lighning showed a fast sync)

