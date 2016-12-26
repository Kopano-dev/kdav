<?php
/***********************************************
* File      :   index.php
* Project   :   KopanoDAV
* Descr     :   This is the entry point
*               through which all requests
*               are processed.
*
* Created   :   13.12.2016
*
* Copyright 2016 Kopano b.v.
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License, version 3,
* as published by the Free Software Foundation.
*
* This software uses SabreDAV, an open source software distributed
* under three-clause BSD-license. Please see <http://sabre.io/dav/>
* for more information about SabreDAV.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* Consult LICENSE file for details
************************************************/

namespace Kopano\DAV;

// require composer auto-loader
require __DIR__ . '/vendor/autoload.php';

$kdavBackend = new KopanoDavBackend();

$authBackend = new AuthBasicBackend($kdavBackend);
$principalBackend = new PrincipalsBackend($kdavBackend);
$kCarddavBackend   = new KopanoCardDavBackend($kdavBackend);
$kCaldavBackend   = new KopanoCalDavBackend($kdavBackend);

// Setting up the directory tree
$nodes = array(
    new \Sabre\DAVACL\PrincipalCollection($principalBackend),
    new \Sabre\CardDAV\AddressBookRoot($principalBackend, $kCarddavBackend),
    new \Sabre\CalDAV\CalendarRoot($principalBackend, $kCaldavBackend),
);

$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri(DAV_ROOT_URI);

$server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, SABRE_AUTH_REALM));
$server->addPlugin(new \Sabre\CardDAV\Plugin());
$server->addPlugin(new \Sabre\DAVACL\Plugin());

// TODO: do we need $caldavPlugin for anything?
$caldavPlugin = new \Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);

$server->addPlugin(new \Sabre\DAV\Browser\Plugin(false));

$server->exec();