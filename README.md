# kDAV - Kopano CalDav & CardDav

Implements CalDav and CardDav support for Kopano 8.2 or newer.

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
It's recommend to install composer locally into the main kDav directory. Then just do

    # ./composer.phar install

### Configuration

All configs are handled in the `config.php` file.
Adjust `MAPI_SERVER` to connect to your Kopano instance.
The `DAV_ROOT_URI` parameter must match your webserver configuration,
so that it points directly to the `index.php` file.

This is the simplest way to setup, running at port 8843:

    Listen 8843
    <VirtualHost *:8843>
        DocumentRoot /your-kdav-working-directory/kdav
        ServerName develop.local
        
        <Directory /your-kdav-working-directory/kdav>
            DirectoryIndex index.php
            RewriteEngine On
            RewriteBase /kdav

            # Rewrite accesses without direct reference to index
            RewriteCond %{REQUEST_FILENAME} !-f
            RewriteCond %{REQUEST_FILENAME} !-d
            RewriteRule ^.*$ /index.php

            Require all granted
        </Directory> 
        <Directory /your-kdav-working-directory/kdav/lib>
            Deny from all
        </Directory>
        <Directory /your-kdav-working-directory/kdav/vendor>
            Deny from all
        </Directory>
    </VirtualHost>

SSL is strongly recommended if you use real passwords.

## Access

As first step, point your webbrowser to:

    http://develop.local:8443/
    
Login with the username + password of a valid Kopano user.

If you don't get the Sabre/Dav overview, check your webserver error logfiles.

In your CalDav client, set the server URL to

    http://develop.local:8443/calendars/<user>/Calendar/

The IP address can of course also be used.

## Reporting and Development

Atm developement is done on z-hub.io in the KDAV project.
    