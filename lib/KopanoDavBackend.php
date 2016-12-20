<?php
/***********************************************
* File      :   KopanoDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano DAV backend class which
*               handles Kopano related activities.
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

class KopanoDavBackend {
    protected $session;

    public function __construct() {

    }

    /**
     * Connect to Kopano and initialize store.
     *
     * @param String $user
     * @param String $pass
     *
     * @access public
     * @return boolean
     */
    public function Logon ($user, $pass) {
        if (Utils::CheckMapiExtVersion('7.2.0')) {
            $kdavVersion = 'KopanoDav' . @constant('KDAV_VERSION');
            $userAgent = "unknown"; // TODO get user agent
            $this->session = @mapi_logon_zarafa($user, $pass, MAPI_SERVER, null, null, 0, $kdavVersion, $userAgent);
        }
        else {
            $this->session = @mapi_logon_zarafa($user, $pass, MAPI_SERVER, null, null, 0);
        }
        // FIXME error handling if logon fails
        return true;
    }
}