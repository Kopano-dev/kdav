<?php
/***********************************************
* File      :   config.php
* Project   :   KopanoDAV
* Descr     :   Configuration file for KopanoDAV.
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

// ************************
//  BackendKopano settings
// ************************

// Defines the server to which we want to connect.
//
// Depending on your setup, it might be advisable to change the lines below to one defined with your
// default socket location.
// Normally "default:" points to the default setting ("file:///var/run/kopano/server.sock")
// Examples: define("MAPI_SERVER", "default:");
//           define("MAPI_SERVER", "http://localhost:236/kopano");
//           define("MAPI_SERVER", "https://localhost:237/kopano");
//           define("MAPI_SERVER", "file:///var/run/kopano/server.sock");
// If you are using ZCP >= 7.2.0, set it to the zarafa location, e.g.
//           define("MAPI_SERVER", "http://localhost:236/zarafa");
//           define("MAPI_SERVER", "https://localhost:237/zarafa");
//           define("MAPI_SERVER", "file:///var/run/zarafad/server.sock");
// For ZCP versions prior to 7.2.0 the socket location is different (http(s) sockets are the same):
//           define("MAPI_SERVER", "file:///var/run/zarafa");

define('MAPI_SERVER', 'default:');

// Authentication realm
define('SABRE_AUTH_REALM', 'Kopano DAV');

// Location of the SabreDAV server.
define('DAV_ROOT_URI', '/');

// Location of the sync database (PDO syntax)
define('SYNC_DB', 'sqlite:/tmp/syncstate.db');

// Developer mode: verifies log messages
define('DEVELOPER_MODE', true);
