<?php
/***********************************************
* File      :   server.php
* Project   :   KopanoDAV
* Descr     :   This is the entry point
*               through which all requests
*               are processed.
*
* Created   :   13.12.2016
*
* Copyright 2016 - 2018 Kopano b.v.
*
* This file is part of kDAV. kDAV is free software; you can redistribute
* it and/or modify it under the terms of the GNU Affero General Public
* License as published by the Free Software Foundation; either version 3
* or (at your option) any later version.
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

// Configure & create main logger
KLogger::configure(__DIR__ . '/log4php.xml');
$logger = new KLogger('main');

// don't log any Sabre asset requests (images etc)
if (isset($_REQUEST['sabreAction']) && $_REQUEST['sabreAction'] == 'asset') {
    $logger->resetConfiguration();
}

// log the start data
$logger->debug('------------------ Start');
$logger->debug('%s %s', $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
$logger->debug('KDAV version %s', KDAV_VERSION);
$logger->debug('SabreDAV version %s',\Sabre\DAV\Version::VERSION);


$kdavBackend = new KopanoDavBackend(new KLogger(('dav')));
$authBackend = new AuthBasicBackend($kdavBackend);
$authBackend->setRealm(SABRE_AUTH_REALM);
$principalBackend = new PrincipalsBackend($kdavBackend);
$kCarddavBackend   = new KopanoCardDavBackend($kdavBackend, new KLogger('card'));
$kCaldavBackend   = new KopanoCalDavBackend($kdavBackend, new KLogger('cal'));

// Setting up the directory tree
$nodes = array(
    new \Sabre\DAVACL\PrincipalCollection($principalBackend),
    new \Sabre\CardDAV\AddressBookRoot($principalBackend, $kCarddavBackend),
    new \Sabre\CalDAV\CalendarRoot($principalBackend, $kCaldavBackend),
);

// initialize the server
$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri(DAV_ROOT_URI);
$server->setLogger(new KPSR3Logger($logger));

$authPlugin = new \Sabre\DAV\Auth\Plugin($authBackend, SABRE_AUTH_REALM);
$server->addPlugin($authPlugin);

// add our version to the headers
$server->httpResponse->addHeader('X-KDAV-Version', KDAV_VERSION);

// log the incoming request (only if authenticated)
$logger->LogIncoming($server->httpRequest);

$aclPlugin = new DAVACL();
$aclPlugin->allowUnauthenticatedAccess = false;
$server->addPlugin($aclPlugin);

$schedulePlugin = new KopanoSchedulePlugin($kdavBackend, new KLogger('schedule'));
$server->addPlugin($schedulePlugin);

$imipPlugin = new KopanoIMipPlugin($kdavBackend, new KLogger('imip'));
$server->addPlugin($imipPlugin);

$server->addPlugin(new \Sabre\CalDAV\ICSExportPlugin());
$server->addPlugin(new \Sabre\CardDAV\Plugin());

// TODO: do we need $caldavPlugin for anything?
$caldavPlugin = new \Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);

if (DEVELOPER_MODE) {
    $server->addPlugin(new \Sabre\DAV\Browser\Plugin(false));
}

$server->exec();

// Log outgoing data
$logger->LogOutgoing($server->httpResponse);

$logger->debug("httpcode='%s' memory='%s/%s' time='%ss'",
                http_response_code(), $logger->FormatBytes(memory_get_peak_usage(false)), $logger->FormatBytes(memory_get_peak_usage(true)),
                number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2));
$logger->debug('------------------ End');
