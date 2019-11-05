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

    /**
     * Constructor.
     *
     * @param KLogger $klogger
     */
    public function __construct(KLogger $klogger) {
        $this->logger = $klogger;
        $this->syncstate = new KopanoSyncState($klogger, SYNC_DB);
    }

    /**
     * Connect to Kopano and create session.
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
     * Create a folder with MAPI class.
     *
     * @param mixed $principalUri
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
     * Delete a folder with MAPI class.
     *
     * @param mixed $id
     * @return bool
     */
    public function DeleteFolder($id) {
        $folder = $this->GetMapiFolder($id);
        if (!$folder) {
            return false;
        }

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

        $rootprops = mapi_getprops($rootfolder, array(PR_IPM_CONTACT_ENTRYID, PR_IPM_APPOINTMENT_ENTRYID));
        foreach ($rows as $row) {
            if ($row[PR_FOLDER_TYPE] == FOLDER_SEARCH) {
                continue;
            }

            $folder = [
                'id'           => $principalUri . ":" . bin2hex($row[PR_SOURCE_KEY]),
                'uri'          => $row[PR_DISPLAY_NAME],
                'principaluri' => $principalUri,
                '{http://sabredav.org/ns}sync-token' => '0000000000',
                '{DAV:}displayname' => $row[PR_DISPLAY_NAME],
                '{http://calendarserver.org/ns/}getctag' => isset($row[PR_LOCAL_COMMIT_TIME_MAX]) ? strval($row[PR_LOCAL_COMMIT_TIME_MAX]) : '0000000000',
            ];

            // ensure default contacts folder is put first, some clients
            // i.e. Apple Addressbook only supports one contact folder,
            // therefore it is desired that folder is the default one.
            if (in_array("IPF.Contact", $classes) && isset($rootprops[PR_IPM_CONTACT_ENTRYID]) && $row[PR_ENTRYID] == $rootprops[PR_IPM_CONTACT_ENTRYID]) {
                array_unshift($folders, $folder);
            }
            // ensure default calendar folder is put first,
            // before the tasks folder.
            elseif (in_array('IPF.Appointment', $classes) && isset($rootprops[PR_IPM_APPOINTMENT_ENTRYID]) && $row[PR_ENTRYID] == $rootprops[PR_IPM_APPOINTMENT_ENTRYID]) {
                array_unshift($folders, $folder);
            }
            else {
                array_push($folders, $folder);
            }
        }
        $this->logger->trace('found %d folders: %s', count($folders), $folders);
        return $folders;
    }

    /**
     * Returns a list of objects for a folder given by the id.
     *
     * @param string $id
     * @param string $fileExtension
     * @param array $filters
     * @return array
     */
    public function GetObjects($id, $fileExtension, $filters = array()) {
        $folder = $this->GetMapiFolder($id);
        $properties = $this->GetCustomProperties($id);
        $table = mapi_folder_getcontentstable($folder, MAPI_DEFERRED_ERRORS);

        $restrictions = array();
        if (isset($filters['start'], $filters['end'])) {
            $this->logger->trace("got start: %d and end: %d", $filters['start'], $filters['end']);
            $subrestriction = $this->GetCalendarRestriction($this->GetStoreById($id), $filters['start'], $filters['end']);
            $restrictions[] = $subrestriction;
        }
        if (isset($filters['types'])) {
            $this->logger->trace("got types: %s", $filters['types']);
            $arr = array();
            foreach ($filters['types'] as $filter) {
                $arr[] = array(RES_PROPERTY,
                      array(RELOP => RELOP_EQ,
                            ULPROPTAG => PR_MESSAGE_CLASS,
                            VALUE => $filter
                      )
                );
            }
            $restrictions[] = array(RES_OR, $arr);
        }
        if (!empty($restrictions)) {
            $restriction = array(RES_AND, $restrictions);
            $this->logger->trace("Got restriction: %s", $restriction);
            mapi_table_restrict($table, $restriction);
        }

        $rows = mapi_table_queryallrows($table, array(PR_SOURCE_KEY, PR_LAST_MODIFICATION_TIME, PR_MESSAGE_SIZE, $properties['appttsrefb'], $properties['appttsrefs']));

        $results = [];
        foreach($rows as $row) {
            $realId = "";
            if (isset($row[$properties['appttsrefb']])) {
                $realId = $row[$properties['appttsrefb']];
            }
            elseif (isset($row[$properties['appttsrefs']])) {
                $realId = $row[$properties['appttsrefs']];
            }
            else {
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
            }
            elseif ($fileExtension == KopanoCardDavBackend::FILE_EXTENSION) {
                $result['addressbookid'] = $id;
            }
            $results[] = $result;
        }
        return $results;
    }

    /**
     * Create the object and set appttsref.
     *
     * @param mixed $folderId
     * @param string $folder
     * @param string $objectId
     * @return mapiresource
     */
    public function CreateObject($folderId, $folder, $objectId) {
        $mapimessage = mapi_folder_createmessage($folder);
        // we save the objectId in PROP_APPTTSREF so we find it by this id
        $properties = $this->GetCustomProperties($folderId);
        mapi_setprops($mapimessage, array($properties['appttsrefb'] => $objectId));
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

    /**
     * Returns MAPI addressbook.
     *
     * @return MAPIAddressbook
     */
    public function GetAddressBook() {
        // TODO should be a singleton
        return mapi_openaddressbook($this->session);
    }

    /**
     * Opens MAPI store for the user.
     *
     * @param string $username
     * @return MAPIStore|false if store not available
     */
    public function OpenMapiStore($username = null) {
        $msgstorestable = mapi_getmsgstorestable($this->session);
        $msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID, PR_MDB_PROVIDER));

        $defaultstore = null;
        $publicstore = null;
        foreach ($msgstores as $row) {
            if (isset($row[PR_DEFAULT_STORE]) && $row[PR_DEFAULT_STORE]) {
                $defaultstore = $row[PR_ENTRYID];
            }
            if (isset($row[PR_MDB_PROVIDER]) && $row[PR_MDB_PROVIDER] == KOPANO_STORE_PUBLIC_GUID) {
                $publicstore = $row[PR_ENTRYID];
            }
        }

        /* user's own store or public store */
        if ($username == $this->GetUser() && $defaultstore != null) {
            return mapi_openmsgstore($this->session, $defaultstore);
        }
        if ($username == 'public' && $publicstore != null) {
            return mapi_openmsgstore($this->session, $publicstore);
        }

        /* otherwise other user's store */
        $store = mapi_openmsgstore($this->session, $defaultstore);
        if (!$store) {
            return false;
        }
        $otherstore = mapi_msgstore_createentryid($store, $username);
        return mapi_openmsgstore($this->session, $otherstore);
    }


    /**
     * Returns store for the user.
     *
     * @param string $storename
     * @return MAPIStore|false if the store is not available
     */
    public function GetStore($storename) {
        if ($storename == null) {
            $storename = $this->GetUser();
        }
        else {
            $storename = str_replace('principals/', '', $storename);
        }
        $this->logger->trace("storename %s", $storename);


        /* We already got the store */
        if (isset($this->stores[$storename]) && $this->stores[$storename] != null) {
            return $this->stores[$storename];
        }

        $this->stores[$storename] = $this->OpenMapiStore($storename);
        if (!$this->stores[$storename]) {
            $this->logger->info("Auth: ERROR - unable to open store for %s (0x%08X)", $storename, mapi_last_hresult());
            return false;
        }
        return $this->stores[$storename];
    }

    /**
     * Returns store from the id.
     * @param mixed $id
     * @return \Kopano\DAV\MAPIStore|false on error
     */
    public function GetStoreById($id) {
        $arr = explode(':', $id);
        return $this->GetStore($arr[0]);
    }

    /**
     * Returns logon session.
     * @return MAPISession
     */
    public function GetSession() {
        return $this->session;
    }

    /**
     * Returns an object ID of a mapi object.
     * If set, PROP_APPTTSREF will be preferred. If not the PR_SOURCE_KEY of the message (as hex) will be returned.
     *
     * This order is reflected as well when searching for a message with these ids in KopanoDavBackend->GetMapiMessageForId().
     *
     * @param string $folderId
     * @param mapiresource $mapimessage
     * @return string
     */
    public function GetIdOfMapiMessage($folderId, $mapimessage) {
        $this->logger->trace("Finding ID of %s", $mapimessage);
        $properties = $this->GetCustomProperties($folderId);

        // It's one of these, order:
        // - PROP_APPTTSREF (if set)
        // - PR_SOURCE_KEY
        $props = mapi_getprops($mapimessage, array($properties['appttsrefb'], $properties['appttsrefs'], PR_SOURCE_KEY));
        if (isset($props[$properties['appttsrefb']])) {
            $this->logger->debug("Found binary PROP_APPTTSREF: %s", $props[$properties['appttsrefb']]);
            return $props[$properties['appttsrefb']];
        }
        if (isset($props[$properties['appttsrefs']])) {
            $this->logger->debug("Found string PROP_APPTTSREF: %s", $props[$properties['appttsrefs']]);
            return $props[$properties['appttsrefs']];
        }
        // PR_SOURCE_KEY is always available
        $id = bin2hex($props[PR_SOURCE_KEY]);
        $this->logger->debug("Found PR_SOURCE_KEY: %s", $id);
        return $id;
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
            if (strpos($id, '%40') !== false) {
                $this->logger->debug("The id contains '%40'. Use urldecode.");
                $id = urldecode($id);
            }
            $restriction = array();
            $restriction[] = array(RES_PROPERTY, array(RELOP => RELOP_EQ, ULPROPTAG => $properties["appttsrefb"], VALUE => $id));
            $restriction[] = array(RES_PROPERTY, array(RELOP => RELOP_EQ, ULPROPTAG => $properties["appttsrefs"], VALUE => $id));
        }

        // find the message if we have a restriction
        if ($restriction) {
            $table = mapi_folder_getcontentstable($mapifolder, MAPI_DEFERRED_ERRORS);
            mapi_table_restrict($table, array(RES_OR, $restriction));
            // Get requested properties, plus whatever we need
            $proplist = array(PR_ENTRYID);
            $rows = mapi_table_queryallrows($table, $proplist);
            if (count($rows) > 1) {
                $this->logger->warn("Found %d entries for id '%s' searching for message", count($rows), $id);
            }
            if (isset($rows[0]) && isset($rows[0][PR_ENTRYID])) {
                $entryid = $rows[0][PR_ENTRYID];
            }
        }
        if ($entryid) {
            $mapimessage = mapi_msgstore_openentry($this->GetStoreById($calendarId), $entryid);
            if (!$mapimessage) {
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
     * Get named (custom) properties. Currently only PROP_APPTTSREF.
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
            $properties = getPropIdsFromStrings($store, ["appttsrefb" => MapiProps::PROP_APPTTSREFB, "appttsrefs" => MapiProps::PROP_APPTTSREFS]);
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

        $restriction = array(RES_OR,
             array(
                   // OR
                   // item.end > window.start && item.start < window.end
                   array(RES_AND,
                         array(
                               array(RES_PROPERTY,
                                     array(RELOP => RELOP_LE,
                                           ULPROPTAG => $props["starttime"],
                                           VALUE => $end
                                           )
                                     ),
                               array(RES_PROPERTY,
                                     array(RELOP => RELOP_GE,
                                           ULPROPTAG => $props["endtime"],
                                           VALUE => $start
                                           )
                                     )
                               )
                         ),
                   // OR
                   array(RES_OR,
                         array(
                               // OR
                               // (EXIST(recurrence_enddate_property) && item[isRecurring] == true && recurrence_enddate_property >= start)
                               array(RES_AND,
                                     array(
                                           array(RES_EXIST,
                                                 array(ULPROPTAG => $props["recurrenceend"],
                                                       )
                                                 ),
                                           array(RES_PROPERTY,
                                                 array(RELOP => RELOP_EQ,
                                                       ULPROPTAG => $props["isrecurring"],
                                                       VALUE => true
                                                       )
                                                 ),
                                           array(RES_PROPERTY,
                                                 array(RELOP => RELOP_GE,
                                                       ULPROPTAG => $props["recurrenceend"],
                                                       VALUE => $start
                                                       )
                                                 )
                                           )
                                     ),
                               // OR
                               // (!EXIST(recurrence_enddate_property) && item[isRecurring] == true && item[start] <= end)
                               array(RES_AND,
                                     array(
                                           array(RES_NOT,
                                                 array(
                                                       array(RES_EXIST,
                                                             array(ULPROPTAG => $props["recurrenceend"]
                                                                   )
                                                             )
                                                       )
                                                 ),
                                           array(RES_PROPERTY,
                                                 array(RELOP => RELOP_LE,
                                                       ULPROPTAG => $props["starttime"],
                                                       VALUE => $end
                                                       )
                                                 ),
                                           array(RES_PROPERTY,
                                                 array(RELOP => RELOP_EQ,
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
     * @param int $limit
     *
     * @access public
     * @return array
     */
    public function Sync($folderId, $syncToken, $fileExtension, $limit = null) {
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
        }
        else {
            $value = $this->syncstate->getState($arr[1], $syncToken);
            if ($value == null) {
                $this->logger->error("Unable to get value from token: %s - folderId: %s", $syncToken, $folderId);
                return null;
            }
            mapi_stream_write($stream, hex2bin($value));
        }

        // The last parameter in mapi_exportchanges_config is buffer size for mapi_exportchanges_synchronize - how many
        // changes will be processed in its call. Setting it to MAX_SYNC_ITEMS won't export more items than is set in
        // the config. If there are more changes than MAX_SYNC_ITEMS the client will eventually catch up and sync
        // the rest on the subsequent sync request(s).
        $bufferSize = ($limit !== null && $limit > 0) ? $limit : MAX_SYNC_ITEMS;
        mapi_exportchanges_config($exporter, $stream, SYNC_NORMAL | SYNC_UNICODE, $mapiimporter, null, false, false, $bufferSize);
        $changesCount = mapi_exportchanges_getchangecount($exporter);
        $this->logger->debug("Exporter found %d changes, buffer size for mapi_exportchanges_synchronize %d", $changesCount, $bufferSize);
        while ((is_array(mapi_exportchanges_synchronize($exporter)))) {
            if ($changesCount > $bufferSize) {
                $this->logger->info("There were too many changes to be exported in this request. Total changes %d, exported %d.", $changesCount, $phpwrapper->Total());
                break;
            }
        }
        $exportedChanges = $phpwrapper->Total();
        $this->logger->debug("Exported %d changes, pending %d", $exportedChanges, $changesCount - $exportedChanges);

        mapi_exportchanges_updatestate($exporter, $stream);
        mapi_stream_seek($stream, 0, STREAM_SEEK_SET);
        $state = "";
        while (true) {
            $data = mapi_stream_read($stream, 4096);
            if (strlen($data) > 0) {
                $state .= $data;
            }
            else {
                break;
            }
        }

        $newtoken = ($phpwrapper->Total() > 0) ? uniqid() : $syncToken;

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
