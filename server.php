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

// Configure logger
\Logger::configure(__DIR__ . '/log4php.xml');
// Create logger for this server:
$logger = \Logger::getLogger('main');

// initialize own logger utililty
$logUtil = new KLogUtil($logger);

// don't log any Sabre asset requests (images etc)
if (isset($_REQUEST['sabreAction']) && $_REQUEST['sabreAction'] == 'asset') {
    $logger->resetConfiguration();
}

// log the start data
$logger->debug('------------------ Start');
$logger->debug(sprintf('%s %s', $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']));
$logger->debug(sprintf('KDAV version %s', KDAV_VERSION));
$logger->debug(sprintf('SabreDAV version %s',\Sabre\DAV\Version::VERSION));


$kdavBackend = new KopanoDavBackend();
$authBackend = new AuthBasicBackend($kdavBackend);
$authBackend->setRealm(SABRE_AUTH_REALM);
$principalBackend = new PrincipalsBackend($kdavBackend);
$kCarddavBackend   = new KopanoCardDavBackend($kdavBackend);
$kCaldavBackend   = new KopanoCalDavBackend($kdavBackend);

// Setting up the directory tree
$nodes = array(
    new \Sabre\DAVACL\PrincipalCollection($principalBackend),
    new \Sabre\CardDAV\AddressBookRoot($principalBackend, $kCarddavBackend),
    new \Sabre\CalDAV\CalendarRoot($principalBackend, $kCaldavBackend),
);

// initialize the server
$server = new \Sabre\DAV\Server($nodes);
$server->setBaseUri(DAV_ROOT_URI);
$server->setLogger($logUtil->GetPSRLoggerInterface());

$authPlugin = new \Sabre\DAV\Auth\Plugin($authBackend, SABRE_AUTH_REALM);
$server->addPlugin($authPlugin);

// add our version to the headers
$server->httpResponse->addHeader('X-KDAV-Version', KDAV_VERSION);

// log the incoming request (only if authenticated)
$logUtil->LogIncoming($server->httpRequest);

$aclPlugin = new \Sabre\DAVACL\Plugin();
$aclPlugin->allowUnauthenticatedAccess = false;
$server->addPlugin($aclPlugin);

$server->addPlugin(new \Sabre\CardDAV\Plugin());

// TODO: do we need $caldavPlugin for anything?
$caldavPlugin = new \Sabre\CalDAV\Plugin();
$server->addPlugin($caldavPlugin);

$server->addPlugin(new \Sabre\DAV\Browser\Plugin(false));

$server->exec();

// Log outgoing data
$logUtil->LogOutgoing($server->httpResponse);

$logger->debug(sprintf("httpcode='%s' memory='%s/%s' time='%ss'",
                http_response_code(), Utils::FormatBytes(memory_get_peak_usage(false)), Utils::FormatBytes(memory_get_peak_usage(true)),
                number_format(microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"], 2)));
$logger->debug('------------------ End');