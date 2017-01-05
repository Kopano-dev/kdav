<?php
/***********************************************
* File      :   version.php
* Project   :   KopanoDAV
* Descr     :   Version file for KopanoDAV.
*
* Created   :   20.12.2016
*
* Copyright 2016 - 2017 Kopano b.v.
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


if (!defined("KDAV_VERSION")) {
    $path = escapeshellarg(dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
    $branch = trim(exec("hash git 2>/dev/null && cd $path >/dev/null 2>&1 && git branch --no-color 2>/dev/null | sed -e '/^[^*]/d' -e \"s/* \(.*\)/\\1/\""));
    $version = exec("hash git 2>/dev/null && cd $path >/dev/null 2>&1 && git describe  --always 2>/dev/null");
    if ($branch && $version) {
        define("KDAV_VERSION", $branch .'-'. $version);
    }
    else {
        define("KDAV_VERSION", "GIT");
    }
}