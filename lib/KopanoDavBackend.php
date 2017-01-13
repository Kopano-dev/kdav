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
     * Returns a list of folders for a MAPI class.
     *
     * @param string $principalUri
     * @param string $class
     * @return array
     */
    public function GetFolders($principalUri, $class) {
        $this->logger->trace("principal '%s', class '%s'", $principalUri, $class);
        $folders = array();

        // TODO limit the output to subfolders of the principalUri?

        $rootfolder = mapi_msgstore_openentry($this->store);
        $hierarchy =  mapi_folder_gethierarchytable($rootfolder, CONVENIENT_DEPTH);
        // TODO also filter hidden folders
        $restriction = array(RES_PROPERTY, array(RELOP => RELOP_EQ, ULPROPTAG => PR_CONTAINER_CLASS, VALUE => $class));

        mapi_table_restrict($hierarchy, $restriction);

        // TODO how to handle hierarchies?
        $rows = mapi_table_queryallrows($hierarchy, array(PR_DISPLAY_NAME, PR_ENTRYID, PR_SOURCE_KEY, PR_PARENT_SOURCE_KEY, PR_CONTAINER_CLASS));

        foreach ($rows as $row) {
            $folders[] = [
                'id'           => bin2hex($row[PR_SOURCE_KEY]),
                'uri'          => $row[PR_DISPLAY_NAME],
                'principaluri' => $principalUri,
            ];
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
         *   - search PROP_GOID with the encapsulated value (Utils::GetOLUidFromICalUid())
         * If it's a GOID, we search PROP_GOID directly
         */
        $properties = getPropIdsFromStrings($this->store, ["appttsref" => MapiProps::PROP_APPTTSREF, "goid" => MapiProps::PROP_GOID]);

        $entryid = false;

        // an encoded vCal-uid or directly an UUID
        if (Utils::IsEncodedVcalUid($id) || Utils::IsValidUUID($id)) {
            $uid = Utils::GetICalUidFromOLUid($id);

            if (Utils::isOutlookUid($id)) {
                $goid = $id;
            }
            else {
                $goid = Utils::GetOLUidFromICalUid($id);
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
        elseif (Utils::IsOutlookUid($id)) {
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
}