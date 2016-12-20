<?php
/***********************************************
* File      :   Utils.php
* Project   :   KopanoDAV
* Descr     :   Several utility functions.
*
* Created   :   15.12.2016
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

class Utils {

    /**
     * Checks if the PHP-MAPI extension is available and in a requested version.
     *
     * @param string    $version    the version to be checked ("6.30.10-18495", parts or build number)
     *
     * @access public
     * @return boolean installed version is superior to the checked string
     */
    static public function CheckMapiExtVersion($version = "") {
        if (!extension_loaded("mapi")) {
            return false;
        }
        // compare build number if requested
        if (preg_match('/^\d+$/', $version) && strlen($version) > 3) {
            $vs = preg_split('/-/', phpversion("mapi"));
            return ($version <= $vs[1]);
        }
        if (version_compare(phpversion("mapi"), $version) == -1){
            return false;
        }

        return true;
    }

}