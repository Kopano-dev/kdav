<?php
/***********************************************
* File      :   DAVACL.php
* Project   :   KopanoDAV
* Descr     :   Kopano DAV ACL class.
*
* Created   :   19.04.2018
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

class DAVACL extends \Sabre\DAVACL\Plugin {

    /**
     * Returns the full ACL list.
     *
     * Either a uri or a DAV\INode may be passed.
     *
     * null will be returned if the node doesn't support ACLs.
     *
     * @param string|DAV\INode $node
     * @return array
     */
    public function getACL($node) {
            $acl = array(
                array(
                    'privilege' => '{DAV:}all',
                    'principal' => '{DAV:}authenticated',
                    'protected' => true,
                ),
            );
        return $acl;
    }
}
