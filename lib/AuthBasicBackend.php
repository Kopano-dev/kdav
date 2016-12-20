<?php
/***********************************************
* File      :   AuthBasicBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano Basic authentication backend class.
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

class AuthBasicBackend extends \Sabre\DAV\Auth\Backend\AbstractBasic {
    protected $kDavBackend;

    /**
     * Constructor.
     *
     * @param KopanoDavBackend $kDavBackend
     *
     * @access public
     * @return void
     */
    public function __construct (KopanoDavBackend $kDavBackend) {
        $this->kopanoDav = $kDavBackend;
    }

    /**
     * Validates a username and password
     *
     * This method should return true or false depending on if login
     * succeeded.
     *
     * @see \Sabre\DAV\Auth\Backend\AbstractBasic::validateUserPass()
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    protected function validateUserPass ($username, $password) {
        return $this->kopanoDav->Logon($username, $password);
    }
}