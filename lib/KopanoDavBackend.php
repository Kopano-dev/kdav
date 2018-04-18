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
    protected $store;
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

        $this->store = GetDefaultStore($this->session);
        if (!$this->store) {
            $this->logger->info("Auth: ERROR - unable to open store for %s", $user);
            return false;
        }

        $this->user = $user;
        $this->logger->debug("Auth: OK - user %s - store %s - session %s", $this->user, $this->store, $this->session);
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
    public function CreateFolder($url, $class, $displayname) {
        $props = mapi_getprops($this->store, array(PR_IPM_SUBTREE_ENTRYID));
        $folder = mapi_msgstore_openentry($this->store, $props[PR_IPM_SUBTREE_ENTRYID]);
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
    public function DeleteFolder($url) {
        $folder = $this->GetMapiFolder($url);
        if (!$folder)
            return false;

        $props = mapi_getprops($folder, array(PR_ENTRYID, PR_PARENT_ENTRYID));
        $parentfolder = mapi_msgstore_openentry($this->store, $props[PR_PARENT_ENTRYID]);
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

        $rootfolder = mapi_msgstore_openentry($this->store);
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
                'id'           => bin2hex($row[PR_SOURCE_KEY]),
                'uri'          => $row[PR_DISPLAY_NAME],
                'principaluri' => $principalUri,
                '{DAV:}displayname' => $row[PR_DISPLAY_NAME],
                '{http://calendarserver.org/ns/}getctag' => isset($row[PR_LOCAL_COMMIT_TIME_MAX]) ? strval($row[PR_LOCAL_COMMIT_TIME_MAX]) : '0000000000',
            ];

            // ensure default contacts folder is put first, some clients
            // i.e. Apple Addressbook only supports one contact folder,
            // therefore it is desired that folder is the default one.
            if (in_array("IPF.Contact", $classes) && $row[PR_ENTRYID] == $rootprops[PR_IPM_CONTACT_ENTRYID])
                array_unshift($folders, $folder);
            else
                array_push($folders, $folder);
        }
        $this->logger->trace('found %d folders', count($folders));
        return $folders;
    }

    /**
     * Returns a mapi folder resource for a folderid (PR_SOURCE_KEY).
     *
     * @param string $folderid
     * @return mapiresource
     */
    public function GetMapiFolder($folderid) {
        $this->logger->trace('Id: %s', $folderid);
        $entryid = mapi_msgstore_entryidfromsourcekey($this->store, hex2bin($folderid));
        return mapi_msgstore_openentry($this->store, $entryid);
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

    /**
     * Returns a object ID of a mapi object.
     * If set, PROP_APPTTSREF will be preferred. If not, the PROP_GOID will be used if available.
     * If both are not set, the PR_SOURCE_KEY of the message (as hex) will be returned.
     *
     * This order is reflected as well when searching for a message with these ids in KopanoDavBackend->GetMapiMessageForId().
     *
     * @param mapiresource $mapimessage
     * @return string
     */
    public function GetIdOfMapiMessage($mapimessage) {
        $this->logger->trace("Finding ID of %s", $mapimessage);
        $properties = getPropIdsFromStrings($this->store, ["appttsref" => MapiProps::PROP_APPTTSREF, "goid" => MapiProps::PROP_GOID]);

        // It's one of these, order:
        // - PROP_APPTTSREF (if set)
        // - PROP_GOID (if set)
        // - PR_SOURCE_KEY
        $props = mapi_getprops($mapimessage, array($properties['appttsref'], $properties['goid'], PR_SOURCE_KEY));
        if (isset($props[$properties['appttsref']])) {
            $this->logger->debug("Found PROP_APPTTSREF: %s", $props[$properties['appttsref']]);
            return $props[$properties['appttsref']];
        }
        elseif (isset($props[$properties['goid']])) {
            $id = bin2hex($props[$properties['goid']]);
            $this->logger->debug("Found PROP_GOID: %s", $id);
            return $id;
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
     * The id can be a vCal-Uid, an OL-GOID or a PR_SOURCE_KEY (as hex).
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
         * - a PROP_GOID (containing a vCal-Uid or not)
         * - a PR_SOURCE_KEY
         *
         * If it's a sourcekey, we can open the message directly.
         * If it's a UID, we:
         *   - search PROP_APPTTSREF with this value AND/OR
         *   - search PROP_GOID with the encapsulated value ($this->getOLUidFromICalUid())
         * If it's a GOID, we search PROP_GOID directly
         */
        $properties = getPropIdsFromStrings($this->store, ["appttsref" => MapiProps::PROP_APPTTSREF, "goid" => MapiProps::PROP_GOID]);

        $entryid = false;

        // an encoded vCal-uid or directly an UUID
        if ($this->isEncodedVcalUid($id) || $this->isValidUUID($id)) {
            $uid = $this->getICalUidFromOLUid($id);

            if ($this->isOutlookUid($id)) {
                $goid = $id;
            }
            else {
                $goid = $this->getOLUidFromICalUid($id);
            }

            // build a restriction that looks for the id in PROP_APPTTSREF or encoded in PROP_GOID
            $restriction =  Array(RES_OR,
                                Array (
                                    Array(RES_PROPERTY,
                                        Array(RELOP => RELOP_EQ,
                                            ULPROPTAG => $properties["appttsref"],
                                            VALUE => $uid
                                        )
                                    ),
                                    Array(RES_PROPERTY,
                                        Array(RELOP => RELOP_EQ,
                                            ULPROPTAG => $properties["goid"],
                                            VALUE => hex2bin($goid)
                                            )
                                        )
                                    )
                                );
            $this->logger->trace("Is vCal-Uid '%s' - GOID %s", $uid, $goid);
        }
        // it's a real OL UID (without vCal uid)
        elseif ($this->isOutlookUid($id)) {
            // build a restriction that looks for the id in PROP_GOID
            $restriction =  Array(RES_PROPERTY,
                                    Array(RELOP => RELOP_EQ,
                                            ULPROPTAG => $properties["goid"],
                                            VALUE => hex2bin($id)
                                            )
                                    );
            $this->logger->trace("Is OL-GOID %s", $id);
        }
        // it's just hex, so it's a sourcekey
        elseif (ctype_xdigit($id)) {
            $this->logger->trace("Is PR_SOURCE_KEY %s", $id);
            $entryid = mapi_msgstore_entryidfromsourcekey($this->store, hex2bin($calendarId), hex2bin($id));
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
            $mapimessage = mapi_msgstore_openentry($this->store, $entryid);
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

    /**
     * Parses and returns an ecoded vCal-Uid from an OL compatible GlobalObjectID.
     *
     * @param string    $olUid      an OL compatible GlobalObjectID as HEX
     *
     * @access protected
     * @return string   the vCal-Uid if available in the olUid, else the original olUid
     */
    protected function getICalUidFromOLUid($olUid){
        if (ctype_xdigit($olUid)) {
            // get a binary representation of it
            $icalUidBi = hex2bin($olUid);
            // check if "vCal-Uid" is somewhere in outlookid case-insensitive
            $icalUid = stristr($icalUidBi, "vCal-Uid");
            if ($icalUid !== false) {
                //get the length of the ical id - go back 4 position from where "vCal-Uid" was found
                $begin = unpack("V", substr($icalUidBi, strlen($icalUid) * (-1) - 4, 4));
                //remove "vCal-Uid" and packed "1" and use the ical id length
                return substr($icalUid, 12, ($begin[1] - 13));
            }
        }
        return $olUid;
    }

    /**
     * Indicates if a given UID contains a vCal-Uid or not.
     *
     * @param string $uid (as hex)
     *
     * @access protected
     * @return boolean
     */
    protected function isEncodedVcalUid($uid) {
        return ctype_xdigit($uid) && stristr(hex2bin($uid), "vCal-Uid") !== false;
    }

    /**
     * Indicates if a guiven UID is an OL GOID or not.
     *
     * @param string $uid
     *
     * @access protected
     * @return boolean
     */
    protected static function isOutlookUid($uid) {
        return 0 === stripos($uid, '040000008200E00074C5B7101A82E008');
    }


    /**
     * Checks if it's a valid UUID as specified in RFC4122.
     *
     * @param string    $uuid
     *
     * @access protected
     * @return boolean
     */
    protected function isValidUUID($uuid) {
        return !!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Checks the given UID if it is an OL compatible GlobalObjectID.
     * If not, the given UID is encoded inside the GlobalObjectID.
     *
     * @param string    $icalUid    an appointment uid as HEX
     *
     * @access protected
     * @return string   an OL compatible GlobalObjectID
     *
     */
    protected function getOLUidFromICalUid($icalUid) {
        if (strlen($icalUid) <= 64) {
            $len = 13 + strlen($icalUid);
            $OLUid = pack("V", $len);
            $OLUid .= "vCal-Uid";
            $OLUid .= pack("V", 1);
            $OLUid .= $icalUid;
            return "040000008200E00074C5B7101A82E0080000000000000000000000000000000000000000". bin2hex($OLUid). "00";
        }
        else
            return $icalUid;
    }
}
