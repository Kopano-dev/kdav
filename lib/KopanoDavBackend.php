<?php
/***********************************************
* File      :   KopanoDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano DAV backend class which
*               handles Kopano related activities.
*
* Created   :   15.12.2016
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

class KopanoDavBackend {
    private $logger;
    protected $session;
    protected $stores;
    protected $user;
    protected $customprops;
    protected $syncstate;

    public function __construct(KLogger $klogger) {
        $this->logger = $klogger;
        $this->syncstate = new KopanoSyncState($klogger, SYNC_DB);
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
        $hierarchy =  mapi_folder_gethierarchytable($rootfolder, CONVENIENT_DEPTH | MAPI_DEFERRED_ERRORS);
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
                '{http://sabredav.org/ns}sync-token' => isset($row[PR_LOCAL_COMMIT_TIME_MAX]) ? strval($row[PR_LOCAL_COMMIT_TIME_MAX]) : '0000000000',
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
     * @param long $start
     * @param long $end
     * @return array
     */
    public function GetObjects($id, $fileExtension, $filters = array()) {
        $folder = $this->GetMapiFolder($id);
        $properties = $this->GetCustomProperties($id);
        $table = mapi_folder_getcontentstable($folder, MAPI_DEFERRED_ERRORS);

        $restrictions = Array();
        if (isset($filters['start'], $filters['end'])) {
            $this->logger->trace("got start: %d and end: %d", $filters['start'], $filters['end']);
            $subrestriction = $this->GetCalendarRestriction($this->GetStoreById($id), $filters['start'], $filters['end']);
            $restrictions[] = $subrestriction;
        }
        if (isset($filters['types'])) {
            $this->logger->trace("got types: %s", $filters['types']);
            $arr = Array();
            foreach ($filters['types'] as $filter) {
                $arr[] = Array(RES_PROPERTY,
                      Array(RELOP => RELOP_EQ,
                            ULPROPTAG => PR_MESSAGE_CLASS,
                            VALUE => $filter
                      )
                );
            }
            $restrictions[] = Array(RES_OR, $arr);
        }
        if (!empty($restrictions)) {
            $restriction = Array(RES_AND, $restrictions);
            $this->logger->trace("Got restriction: %s", $restriction);
            mapi_table_restrict($table, $restriction);
        }

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
        $properties = $this->GetCustomProperties($folderId);
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
        $properties = $this->GetCustomProperties($folderId);

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
        $entryid = false;
        $restriction = false;

        if (ctype_xdigit($id)) {
            $this->logger->trace("Try PR_SOURCE_KEY %s", $id);
            $arr = explode(':', $calendarId);
            $entryid = mapi_msgstore_entryidfromsourcekey($this->GetStoreById($arr[0]), hex2bin($arr[1]), hex2bin($id));
        }
        if (!$entryid) {
            $this->logger->trace("Try APPTTSREF %s", $id);
            $properties = $this->GetCustomProperties($calendarId);
            $restriction = Array(RES_PROPERTY,
                                 Array(RELOP => RELOP_EQ,
                                       ULPROPTAG => $properties["appttsref"],
                                       VALUE => $id
                                     )
                );
        }

        // find the message if we have a restriction
        if ($restriction) {
            $table = mapi_folder_getcontentstable($mapifolder, MAPI_DEFERRED_ERRORS);
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

    /**
     * Get named (custom) properties. Currently only PROP_APPTTSREF
     *
     * @param string    $id    the folder id
     *
     * @access protected
     * @return mixed
     */
    protected function GetCustomProperties($id) {
        if (!isset($this->customprops[$id])) {
            $this->logger->trace("Fetching properties id:%s", $id);
            $store = $this->GetStoreById($id);
            $properties = getPropIdsFromStrings($store, ["appttsref" => MapiProps::PROP_APPTTSREF]);
            $this->customprops[$id] = $properties;
        }
        return $this->customprops[$id];
    }

    /**
     * Create a MAPI restriction to use in the calendar which will
     * return future calendar items (until $end), plus those since $start.
     * Origins: Z-Push
     *
     * @param MAPIStore  $store         the MAPI store
     * @param long       $start         Timestamp since when to include messages
     * @param long       $end           Ending timestamp
     *
     * @access public
     * @return array
     */
    //TODO getting named properties
    public function GetCalendarRestriction($store, $start, $end) {
        $props = MAPIProps::GetAppointmentProperties();
        $props = getPropIdsFromStrings($store, $props);

        // ATTENTION: ON CHANGING THIS RESTRICTION, MAPIUtils::IsInCalendarSyncInterval() also needs to be changed
        $restriction = Array(RES_OR,
             Array(
                   // OR
                   // item.end > window.start && item.start < window.end
                   Array(RES_AND,
                         Array(
                               Array(RES_PROPERTY,
                                     Array(RELOP => RELOP_LE,
                                           ULPROPTAG => $props["starttime"],
                                           VALUE => $end
                                           )
                                     ),
                               Array(RES_PROPERTY,
                                     Array(RELOP => RELOP_GE,
                                           ULPROPTAG => $props["endtime"],
                                           VALUE => $start
                                           )
                                     )
                               )
                         ),
                   // OR
                   Array(RES_OR,
                         Array(
                               // OR
                               // (EXIST(recurrence_enddate_property) && item[isRecurring] == true && recurrence_enddate_property >= start)
                               Array(RES_AND,
                                     Array(
                                           Array(RES_EXIST,
                                                 Array(ULPROPTAG => $props["recurrenceend"],
                                                       )
                                                 ),
                                           Array(RES_PROPERTY,
                                                 Array(RELOP => RELOP_EQ,
                                                       ULPROPTAG => $props["isrecurring"],
                                                       VALUE => true
                                                       )
                                                 ),
                                           Array(RES_PROPERTY,
                                                 Array(RELOP => RELOP_GE,
                                                       ULPROPTAG => $props["recurrenceend"],
                                                       VALUE => $start
                                                       )
                                                 )
                                           )
                                     ),
                               // OR
                               // (!EXIST(recurrence_enddate_property) && item[isRecurring] == true && item[start] <= end)
                               Array(RES_AND,
                                     Array(
                                           Array(RES_NOT,
                                                 Array(
                                                       Array(RES_EXIST,
                                                             Array(ULPROPTAG => $props["recurrenceend"]
                                                                   )
                                                             )
                                                       )
                                                 ),
                                           Array(RES_PROPERTY,
                                                 Array(RELOP => RELOP_LE,
                                                       ULPROPTAG => $props["starttime"],
                                                       VALUE => $end
                                                       )
                                                 ),
                                           Array(RES_PROPERTY,
                                                 Array(RELOP => RELOP_EQ,
                                                       ULPROPTAG => $props["isrecurring"],
                                                       VALUE => true
                                                       )
                                                 )
                                           )
                                     )
                               )
                         ) // EXISTS OR
                   )
             );        // global OR

        return $restriction;
    }

    /**
     * Performs ICS based sync used from getChangesForAddressBook
     * / getChangesForCalendar.
     *
     * @param string $folderId
     * @param string $syncToken
     * @param string $fileExtension
     *
     * @access public
     * @return array
     */
    public function Sync($folderId, $syncToken, $fileExtension) {
        $arr = explode(':', $folderId);
        $phpwrapper = new PHPWrapper($this->GetStoreById($folderId), $this->logger, $this->GetCustomProperties($folderId), $fileExtension, $this->syncstate, $arr[1]);
        $mapiimporter = mapi_wrap_importcontentschanges($phpwrapper);

        $mapifolder = $this->GetMapiFolder($folderId);
        $exporter = mapi_openproperty($mapifolder, PR_CONTENTS_SYNCHRONIZER, IID_IExchangeExportChanges, 0, 0);
        if (!$exporter) {
            $this->logger->error("Unable to get exporter");
            return null;
        }

        $stream = mapi_stream_create();
        if ($syncToken == null) {
            mapi_stream_write($stream, hex2bin("0000000000000000"));
        } else {
            $value = $this->syncstate->getState($arr[1], $syncToken);
            if ($value == null) {
                $this->logger->error("Unable to get value from token: %s - folderId: %s", $syncToken, $folderId);
                return null;
            }
            mapi_stream_write($stream, hex2bin($value));
        }

        mapi_exportchanges_config($exporter, $stream, SYNC_NORMAL | SYNC_UNICODE, $mapiimporter, null, false, false, 0);
        $syncresult = mapi_exportchanges_synchronize($exporter);
        $this->logger->trace("sync result %s", $syncresult);

        mapi_exportchanges_updatestate($exporter, $stream);
        mapi_stream_seek($stream, 0, STREAM_SEEK_SET);
        $state = "";
        while (true) {
            $data = mapi_stream_read($stream, 4096);
            if (strlen($data) > 0)
                $state .= $data;
            else
                break;
        }

        $props = mapi_getprops($mapifolder, array(PR_LOCAL_COMMIT_TIME_MAX));
        $newtoken = $props[PR_LOCAL_COMMIT_TIME_MAX];
        $this->syncstate->setState($arr[1], $newtoken, bin2hex($state));

        $result = array(
            "syncToken" => $newtoken,
            "added" => $phpwrapper->GetAdded(),
            "modified" => $phpwrapper->GetModified(),
            "deleted" => $phpwrapper->GetDeleted()
        );

        $this->logger->trace("Returning %s", $result);

        return $result;
    }
}
