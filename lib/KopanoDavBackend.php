<?php
/***********************************************
* File      :   KopanoDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano DAV backend class which
*               handles Kopano related activities.
*
* Created   :   15.12.2016
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
namespace Kopano\DAV;

class KopanoDavBackend {
    private $logger;
    protected $session;
    protected $stores;
    protected $user;

    public function __construct(KLogger $klogger) {
        $this->logger = $klogger;
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
        $this->logger->trace('%s / password', $user);

        $kdavVersion = 'KopanoDav' . @constant('KDAV_VERSION');
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $this->session = mapi_logon_zarafa($user, $pass, MAPI_SERVER, null, null, 1, $kdavVersion, $userAgent);
        if (!$this->session) {
            $this->logger->info("Auth: ERROR - logon failed for user %s", $user);
            return false;
        }

        $this->user = $user;
        $this->logger->debug("Auth: OK - user %s - session %s", $this->user, $this->session);
        return true;
    }

    /**
     * Returns the authenticated user.
     *
     * @access public
     * @return String
     */
    public function GetUser() {
        $this->logger->trace($this->user);
        return $this->user;
    }

    /**
     * Create a folder with MAPI class
     *
     * @param string $url
     * @param string $class
     * @param string $displayname
     * @return String
     */
    public function CreateFolder($principalUri, $url, $class, $displayname) {
        $props = mapi_getprops($this->GetStore($principalUri), array(PR_IPM_SUBTREE_ENTRYID));
        $folder = mapi_msgstore_openentry($this->GetStore($principalUri), $props[PR_IPM_SUBTREE_ENTRYID]);
        $newfolder = mapi_folder_createfolder($folder, $url, $displayname);
        mapi_setprops($newfolder, array(PR_CONTAINER_CLASS => $class));
        return $url;
    }

    /**
     * Delete a folder with MAPI class
     *
     * @param string $url
     * @param string $class
     * @param string $displayname
     * @return bool
     */
    public function DeleteFolder($id) {
        $folder = $this->GetMapiFolder($id);
        if (!$folder)
            return false;

        $props = mapi_getprops($folder, array(PR_ENTRYID, PR_PARENT_ENTRYID));
        $parentfolder = mapi_msgstore_openentry($this->GetStoreById($id), $props[PR_PARENT_ENTRYID]);
        mapi_folder_deletefolder($parentfolder, $props[PR_ENTRYID]);

        return true;
    }

    /**
     * Returns a list of folders for a MAPI class.
     *
     * @param string $principalUri
     * @param string $class
     * @return array
     */
    public function GetFolders($principalUri, $classes) {
        $this->logger->trace("principal '%s', classes '%s'", $principalUri, $classes);
        $folders = array();

        // TODO limit the output to subfolders of the principalUri?

        $rootfolder = mapi_msgstore_openentry($this->GetStore($principalUri));
        $hierarchy =  mapi_folder_gethierarchytable($rootfolder, CONVENIENT_DEPTH);
        // TODO also filter hidden folders
        $restrictions = array();
        foreach ($classes as $class) {
		    $restrictions[] = array(RES_PROPERTY, array(RELOP => RELOP_EQ, ULPROPTAG => PR_CONTAINER_CLASS, VALUE => $class));
        }
        mapi_table_restrict($hierarchy, array(RES_OR, $restrictions));

        // TODO how to handle hierarchies?
        $rows = mapi_table_queryallrows($hierarchy, array(PR_DISPLAY_NAME, PR_ENTRYID, PR_SOURCE_KEY, PR_PARENT_SOURCE_KEY, PR_FOLDER_TYPE, PR_LOCAL_COMMIT_TIME_MAX));

        $rootprops = mapi_getprops($rootfolder, array(PR_IPM_CONTACT_ENTRYID));
        foreach ($rows as $row) {
            if ($row[PR_FOLDER_TYPE] == FOLDER_SEARCH)
                continue;

            $folder = [
                'id'           => $principalUri . ":" . bin2hex($row[PR_SOURCE_KEY]),
                'uri'          => $row[PR_DISPLAY_NAME],
                'principaluri' => $principalUri,
                '{DAV:}displayname' => $row[PR_DISPLAY_NAME],
                '{http://calendarserver.org/ns/}getctag' => isset($row[PR_LOCAL_COMMIT_TIME_MAX]) ? strval($row[PR_LOCAL_COMMIT_TIME_MAX]) : '0000000000',
            ];

            // ensure default contacts folder is put first, some clients
            // i.e. Apple Addressbook only supports one contact folder,
            // therefore it is desired that folder is the default one.
            if (in_array("IPF.Contact", $classes) && isset($rootprops[PR_IPM_CONTACT_ENTRYID]) && $row[PR_ENTRYID] == $rootprops[PR_IPM_CONTACT_ENTRYID])
                array_unshift($folders, $folder);
            else
                array_push($folders, $folder);
        }
        $this->logger->trace('found %d folders: %s', count($folders), $folders);
        return $folders;
    }

    /**
     * Returns a list of objects for a folder given by the id.
     *
     * @param string $id
     * @param string $fileExtension
     * @return array
     */
    public function GetObjects($id, $fileExtension) {
        $folder = $this->GetMapiFolder($id);
        $properties = getPropIdsFromStrings($this->GetStoreById($id), ["appttsref" => MapiProps::PROP_APPTTSREF]);
        $table = mapi_folder_getcontentstable($folder);
        $rows = mapi_table_queryallrows($table, array(PR_SOURCE_KEY, PR_LAST_MODIFICATION_TIME, PR_MESSAGE_SIZE, $properties['appttsref']));

        $results = [];
        foreach($rows as $row) {
            $realId = "";
            if (isset($row[$properties['appttsref']])) {
                $realId = $row[$properties['appttsref']];
            } else {
                $realId = bin2hex($row[PR_SOURCE_KEY]);
            }

            $result = [
                'id'            => $realId,
                'uri'           => $realId . $fileExtension,
                'etag'          => '"' . $row[PR_LAST_MODIFICATION_TIME] . '"',
                'lastmodified'  => $row[PR_LAST_MODIFICATION_TIME],
                'size'          => $row[PR_MESSAGE_SIZE], // only approximation
            ];

            if ($fileExtension == KopanoCalDavBackend::FILE_EXTENSION) {
                $result['calendarid'] = $id;
            } elseif ($fileExtension == KopanoCardDavBackend::FILE_EXTENSION) {
                $result['addressbookid'] = $id;
            }
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Create the object and set appttsref.
     *
     * @param string $folder
     * @param string $objectId
     * @return mapiresource
     */
    public function CreateObject($folderId, $folder, $objectId) {
        $mapimessage = mapi_folder_createmessage($folder);
        // we save the objectId in PROP_APPTTSREF so we find it by this id
        $properties = getPropIdsFromStrings($this->GetStoreById($folderId), ["appttsref" => MapiProps::PROP_APPTTSREF]);
        mapi_setprops($mapimessage, array($properties['appttsref'] => $objectId));
        return $mapimessage;
    }

    /**
     * Returns a mapi folder resource for a folderid (PR_SOURCE_KEY).
     *
     * @param string $folderid
     * @return mapiresource
     */
    public function GetMapiFolder($folderid) {
        $this->logger->trace('Id: %s', $folderid);
        $arr = explode(':', $folderid);
        $entryid = mapi_msgstore_entryidfromsourcekey($this->GetStore($arr[0]), hex2bin($arr[1]));
        return mapi_msgstore_openentry($this->GetStore($arr[0]), $entryid);
    }

    public function GetAddressBook() {
        // TODO could be a singleton
        return mapi_openaddressbook($this->session);
    }

    public function GetMapiStore($username = null) {
        $msgstorestable = mapi_getmsgstorestable($this->session);
        $msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID, PR_MDB_PROVIDER));

        $defaultstore = null;
        $publicstore = null;
        foreach ($msgstores as $row) {
            if (isset($row[PR_DEFAULT_STORE]) && $row[PR_DEFAULT_STORE])
                $defaultstore = $row[PR_ENTRYID];
            if (isset($row[PR_MDB_PROVIDER]) && $row[PR_MDB_PROVIDER] == KOPANO_STORE_PUBLIC_GUID)
                $publicstore = $row[PR_ENTRYID];
        }

        /* user's own store or public store */
        if ($username == $this->GetUser() && $defaultstore != null) {
            return mapi_openmsgstore($this->session, $defaultstore);
        } elseif ($username == 'public' && $publicstore != null) {
            return mapi_openmsgstore($this->session, $publicstore);
        }

        /* otherwise other users store */
        $store = mapi_openmsgstore($this->session, $defaultstore);
        if (!$store) {
            return false;
        }
        $otherstore = mapi_msgstore_createentryid($store, $username);
        return mapi_openmsgstore($this->session, $otherstore);
    }


    public function GetStore($storename) {
        if ($storename == null) {
            $storename = $this->GetUser();
        } else {
            $storename = str_replace('principals/', '', $storename);
        }
        $this->logger->trace("storename %s", $storename);


        /* We already got the store */
        if (isset($this->stores[$storename]) && $this->stores[$storename] != null) {
            return $this->stores[$storename];
        }

        $this->stores[$storename] = $this->GetMapiStore($storename);
        if (!$this->stores[$storename]) {
            $this->logger->info("Auth: ERROR - unable to open store for %s", $storename);
            return false;
        }
        return $this->stores[$storename];
    }

    public function GetStoreById($id) {
        $arr = explode(':', $id);
        return $this->GetStore($arr[0]);
    }

    public function GetSession() {
        return $this->session;
    }

    /**
     * Returns a object ID of a mapi object.
     * If set, PROP_APPTTSREF will be preferred. If not the PR_SOURCE_KEY of the message (as hex) will be returned.
     *
     * This order is reflected as well when searching for a message with these ids in KopanoDavBackend->GetMapiMessageForId().
     *
     * @param mapiresource $mapimessage
     * @return string
     */
    public function GetIdOfMapiMessage($folderId, $mapimessage) {
        $this->logger->trace("Finding ID of %s", $mapimessage);
        $properties = getPropIdsFromStrings($this->GetStoreById($folderId), ["appttsref" => MapiProps::PROP_APPTTSREF]);

        // It's one of these, order:
        // - PROP_APPTTSREF (if set)
        // - PR_SOURCE_KEY
        $props = mapi_getprops($mapimessage, array($properties['appttsref'], PR_SOURCE_KEY));
        if (isset($props[$properties['appttsref']])) {
            $this->logger->debug("Found PROP_APPTTSREF: %s", $props[$properties['appttsref']]);
            return $props[$properties['appttsref']];
        }
        // is always available
        else {
            $id = bin2hex($props[PR_SOURCE_KEY]);
            $this->logger->debug("Found PR_SOURCE_KEY: %s", $id);
            return $id;
        }
    }

    /**
     * Finds and opens a MapiMessage from an objectId.
     * The id can be a PROP_APPTTSREF or a PR_SOURCE_KEY (as hex).
     *
     * @param string $calendarId
     * @param string $id
     * @param mapiresource $mapifolder optional
     *
     * @access public
     * @return NULL|mapiresource
     */
    public function GetMapiMessageForId($calendarId, $id, $mapifolder = null) {
        $this->logger->trace("Searching for '%s' in '%s' (%s)", $id, $calendarId, $mapifolder);

        if (!$mapifolder) {
            $mapifolder = $this->GetMapiFolder($calendarId);
        }

        /* The ID can be several different things:
         * - a UID that is saved in PROP_APPTTSREF
         * - a PR_SOURCE_KEY
         *
         * If it's a sourcekey, we can open the message directly.
         * If it's a UID, we:
         *   - search PROP_APPTTSREF with this value AND/OR
         */
        $properties = getPropIdsFromStrings($this->GetStoreById($calendarId), ["appttsref" => MapiProps::PROP_APPTTSREF]);

        $entryid = false;

        if (ctype_xdigit($id)) {
            $this->logger->trace("Is PR_SOURCE_KEY %s", $id);
            $arr = explode(':', $calendarId);
            $entryid = mapi_msgstore_entryidfromsourcekey($this->GetStoreById($arr[0]), hex2bin($arr[1]), hex2bin($id));
            $restriction = false;
        }
        else {
            $restriction = Array(RES_PROPERTY,
                                 Array(RELOP => RELOP_EQ,
                                       ULPROPTAG => $properties["appttsref"],
                                       VALUE => $id
                                     )
                );
        }

        // find the message if we have a restriction
        if ($restriction) {
            $table = mapi_folder_getcontentstable($mapifolder);
            // Get requested properties, plus whatever we need
            $proplist = array(PR_ENTRYID);
            $rows = mapi_table_queryallrows($table, $proplist, $restriction);
            if (count($rows) > 1) {
                $this->logger->warn("Found %d entries for id '%s' searching for message", count($rows), $id);
            }
            if (isset($rows[0]) && isset($rows[0][PR_ENTRYID])) {
                $entryid = $rows[0][PR_ENTRYID];
            }
        }
        if ($entryid) {
            $mapimessage = mapi_msgstore_openentry($this->GetStoreById($calendarId), $entryid);
            if(!$mapimessage) {
                $this->logger->warn("Error, unable to open entry id: 0x%X", $entryid, mapi_last_hresult());
                return null;
            }
            return $mapimessage;
        }
        $this->logger->debug("Nothing found for %s", $id);
        return null;
    }

    /**
     * Returns the objectId from an objectUri. It strips the file extension
     * if it matches the passed one.
     *
     * @param string $objectUri
     * @param string $extension
     *
     * @access public
     * @return string
     */
    public function GetObjectIdFromObjectUri($objectUri, $extension) {
        $extLength = strlen($extension);
        if (substr($objectUri, -$extLength) === $extension) {
            return substr($objectUri, 0, -$extLength);
        }
        return $objectUri;
    }

    /**
     * Checks if the PHP-MAPI extension is available and in a requested version.
     *
     * @param string    $version    the version to be checked ("6.30.10-18495", parts or build number)
     *
     * @access protected
     * @return boolean installed version is superior to the checked string
     */
    protected function checkMapiExtVersion($version = "") {
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
