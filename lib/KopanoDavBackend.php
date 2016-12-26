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

require_once("/usr/share/php/mapi/mapi.util.php");
require_once("/usr/share/php/mapi/mapidefs.php");
require_once("/usr/share/php/mapi/mapitags.php");

class KopanoDavBackend {
    protected $session;
    protected $store;
    protected $user;

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
    public function Logon($user, $pass) {
        if (Utils::CheckMapiExtVersion('7.2.0')) {
            $kdavVersion = 'KopanoDav' . @constant('KDAV_VERSION');
            $userAgent = "unknown"; // TODO get user agent
            $this->session = @mapi_logon_zarafa($user, $pass, MAPI_SERVER, null, null, 0, $kdavVersion, $userAgent);
        }
        else {
            $this->session = @mapi_logon_zarafa($user, $pass, MAPI_SERVER, null, null, 0);
        }
        // FIXME error handling if logon fails

        $this->store = GetDefaultStore($this->session);
        $this->user = $user;
        return true;
    }

    public function GetUser() {
        return $this->user;
    }
    public function GetFolders($principalUri, $class) {
        $folders = array();

        // TODO limit the output to subfolders of the principalUri?

        $rootfolder = mapi_msgstore_openentry($this->store);
        $rootfolderprops = mapi_getprops($rootfolder, array(PR_SOURCE_KEY));

        $hierarchy =  mapi_folder_gethierarchytable($rootfolder, CONVENIENT_DEPTH);
        // TODO also filter hidden folders
        $restriction = array(RES_PROPERTY, array(RELOP => RELOP_EQ, ULPROPTAG => PR_CONTAINER_CLASS, VALUE => $class));

        mapi_table_restrict($hierarchy, $restriction);

        // TODO how to handle hierarchies?
        $rows = mapi_table_queryallrows($hierarchy, array(PR_DISPLAY_NAME, PR_ENTRYID, PR_SOURCE_KEY, PR_PARENT_SOURCE_KEY, PR_CONTAINER_CLASS));

        foreach ($rows as $row) {
            $folders[] = [
                'id'           => bin2hex($row[PR_ENTRYID]),
                'uri'          => $row[PR_DISPLAY_NAME],
                'principaluri' => $principalUri,
            ];
        }
        return $folders;
    }

    public function GetMapiFolder($entryid) {
        return mapi_msgstore_openentry($this->store, hex2bin($entryid));
    }

    public function GetAddressBook() {
        // TODO could be a singleton
        return mapi_openaddressbook($this->session);
    }

    public function GetStore() {
        return $this->store;
    }

    public function GetSession() {
        return $this->session;
    }

    public function IsOurId($id) {
        return !preg_match("/[^A-Fa-f0-9]/", $id);
    }

}