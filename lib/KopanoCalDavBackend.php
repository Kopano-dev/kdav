<?php
/***********************************************
* File      :   KopanoCalDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano CalDAV backend class which
*               handles calendar related activities.
*
* Created   :   26.12.2016
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

class KopanoCalDavBackend extends \Sabre\CalDAV\Backend\AbstractBackend {
    /*
     * TODO IMPLEMENT
     *
     * implements \Sabre\CalDAV\Backend\SyncSupport,
     * SubscriptionSupport,
     * SchedulingSupport,
     * SharingSupport,
     * add ICSExportPlugin to allow export of an ics file (all events in one file)
     *
     */

    private $logger;
    protected $kDavBackend;

    const FILE_EXTENSION = '.ics';
    const CONTAINER_CLASS = 'IPF.Appointment';

    public function __construct(KopanoDavBackend $kDavBackend, KLogger $klogger) {
        $this->kDavBackend = $kDavBackend;
        $this->logger = $klogger;
    }

    /**
     * Returns a list of calendars for a principal.
     *
     * Every project is an array with the following keys:
     *  * id, a unique id that will be used by other functions to modify the
     *    calendar. This can be the same as the uri or a database key.
     *  * uri. This is just the 'base uri' or 'filename' of the calendar.
     *  * principaluri. The owner of the calendar. Almost always the same as
     *    principalUri passed to this method.
     *
     * Furthermore it can contain webdav properties in clark notation. A very
     * common one is '{DAV:}displayname'.
     *
     * Many clients also require:
     * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
     * For this property, you can just return an instance of
     * Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet.
     *
     * If you return {http://sabredav.org/ns}read-only and set the value to 1,
     * ACL will automatically be put in read-only mode.
     *
     * @param string $principalUri
     * @return array
     */
    function getCalendarsForUser($principalUri) {
        $this->logger->trace("principalUri: %s", $principalUri);
        return $this->kDavBackend->GetFolders($principalUri, static::CONTAINER_CLASS);
    }

    /**
     * Creates a new calendar for a principal.
     *
     * If the creation was a success, an id must be returned that can be used
     * to reference this calendar in other methods, such as updateCalendar.
     *
     * @param string $principalUri
     * @param string $calendarUri
     * @param array $properties
     * @return string
     */
    function createCalendar($principalUri, $calendarUri, array $properties) {
        $this->logger->trace("principalUri: %s - calendarUri: %s - properties: %s", $principalUri, $calendarUri, $properties);
        // TODO Add displayname
        return $this->kDavBackend->CreateFolder($calendarUri, static::CONTAINER_CLASS, "");
    }

    /**
     * Delete a calendar and all it's objects
     *
     * @param string $calendarId
     * @return void
     */
    function deleteCalendar($calendarId) {
        $this->logger->trace("calendarId: %s", $calendarId);
        $success = $this->kDavBackend->DeleteFolder($calendarId);
        // TODO evaluate $success
    }

    /**
     * Returns all calendar objects within a calendar.
     *
     * Every item contains an array with the following keys:
     *   * calendardata - The iCalendar-compatible calendar data
     *   * uri - a unique key which will be used to construct the uri. This can
     *     be any arbitrary string, but making sure it ends with '.ics' is a
     *     good idea. This is only the basename, or filename, not the full
     *     path.
     *   * lastmodified - a timestamp of the last modification time
     *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
     *   '  "abcdef"')
     *   * size - The size of the calendar objects, in bytes.
     *   * component - optional, a string containing the type of object, such
     *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
     *     the Content-Type header.
     *
     * Note that the etag is optional, but it's highly encouraged to return for
     * speed reasons.
     *
     * The calendardata is also optional. If it's not returned
     * 'getCalendarObject' will be called later, which *is* expected to return
     * calendardata.
     *
     * If neither etag or size are specified, the calendardata will be
     * used/fetched to determine these numbers. If both are specified the
     * amount of times this is needed is reduced by a great degree.
     *
     * @param string $calendarId
     * @return array
     */
    function getCalendarObjects($calendarId) {
        $this->logger->trace("calendarId: %s", $calendarId);

        $folder = $this->kDavBackend->GetMapiFolder($calendarId);

        $table = mapi_folder_getcontentstable($folder);
        $rows = mapi_table_queryallrows($table, array(PR_SOURCE_KEY, PR_LAST_MODIFICATION_TIME));

        $result = [];
        foreach($rows as $row) {
            // at the end, the calendar objects might have a different ID, but $this->getCalendarObject() is able to handle the PR_SOURCE_KEY
            $obj = $this->getCalendarObject($calendarId, bin2hex($row[PR_SOURCE_KEY]) . static::FILE_EXTENSION, $folder);
            if (is_array($obj)) {
                $result[] = $obj;
            }
        }
        $this->logger->trace("found %d objects", count($result));
        return $result;

    }

    /**
     * Returns information from a single calendar object, based on it's object
     * uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * The returned array must have the same keys as getCalendarObjects. The
     * 'calendardata' object is required here though, while it's not required
     * for getCalendarObjects.
     *
     * This method must return null if the object did not exist.
     *
     * @param string $calendarId
     * @param string $objectUri
     * @param ressource $mapifolder     optional mapifolder resource, used if avialable
     * @return array|null
     */
    function getCalendarObject($calendarId, $objectUri, $mapifolder = null) {
        $this->logger->trace("calendarId: %s - objectUri: %s - mapifolder: %s", $calendarId, $objectUri, $mapifolder);

        if (!$mapifolder) {
            $mapifolder = $this->kDavBackend->GetMapiFolder($calendarId);
        }

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId, $mapifolder);
        if (!$mapimessage) {
            $this->logger->debug("Object NOT FOUND");
            return null;
        }

        $realId = $this->kDavBackend->GetIdOfMapiMessage($mapimessage);

        // this should be cached or moved to kDavBackend
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $ics = mapi_mapitoical($session, $ab, $mapimessage, array());
        $props = mapi_getprops($mapimessage, array(PR_LAST_MODIFICATION_TIME));

        $r = [
                'id'            => $realId,
                'uri'           => $realId . static::FILE_EXTENSION,
                'etag'          => '"' . $props[PR_LAST_MODIFICATION_TIME] . '"',
                'lastmodified'  => $props[PR_LAST_MODIFICATION_TIME],
                'calendarid'    => $calendarId,
                'size'          => strlen($ics),
                'calendardata'  => $ics,
        ];
        $this->logger->trace("returned data id: %s - size: %d - etag: %s", $r['id'], $r['size'], $r['etag']);
        return $r;
    }

    /**
     * Creates a new calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
    function createCalendarObject($calendarId, $objectUri, $calendarData) {
        $this->logger->trace("calendarId: %s - objectUri: %s - calendarData: %s", $calendarId, $objectUri, $calendarData);

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $store = $this->kDavBackend->GetStore();

        $folder = $this->kDavBackend->GetMapiFolder($calendarId);
        $mapimessage = mapi_folder_createmessage($folder);

        // we save the objectId in PROP_APPTTSREF so we find it by this id
        $properties = getPropIdsFromStrings($store, ["appttsref" => MapiProps::PROP_APPTTSREF]);
        mapi_setprops($mapimessage, array($properties['appttsref'] => $objectId));

        return $this->setData($mapimessage, $calendarData);
    }

    /**
     * Updates an existing calendarobject, based on its uri.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * It is possible return an etag from this function, which will be used in
     * the response to this PUT request. Note that the ETag must be surrounded
     * by double-quotes.
     *
     * However, you should only really return this ETag if you don't mangle the
     * calendar-data. If the result of a subsequent GET to this object is not
     * the exact same as this request body, you should omit the ETag.
     *
     * @param mixed $calendarId
     * @param string $objectUri
     * @param string $calendarData
     * @return string|null
     */
    function updateCalendarObject($calendarId, $objectUri, $calendarData) {
        $this->logger->trace("calendarId: %s - objectUri: %s - calendarData: %s", $calendarId, $objectUri, $calendarData);

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId);
        return $this->setData($mapimessage, $calendarData);
    }

    private function setData($mapimessage, $ics) {
        $this->logger->trace("mapimessage: %s - ics: %s", $mapimessage, $ics);
        // this should be cached or moved to kDavBackend
        $store = $this->kDavBackend->GetStore();
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $ok = mapi_icaltomapi($session, $store, $ab, $mapimessage, $ics, false);
        if ($ok) {
            mapi_message_savechanges($mapimessage);
            $props = mapi_getprops($mapimessage, array(PR_LAST_MODIFICATION_TIME));
            return $props[PR_LAST_MODIFICATION_TIME];
        }
        return null;
    }

    /**
     * Deletes an existing calendar object.
     *
     * The object uri is only the basename, or filename and not a full path.
     *
     * @param string $calendarId
     * @param string $objectUri
     * @return void
     */
    function deleteCalendarObject($calendarId, $objectUri) {
        $this->logger->trace("calendarId: %s - objectUri: %s", $calendarId, $objectUri);

        $mapifolder = $this->kDavBackend->GetMapiFolder($calendarId);
        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);

        // to delete we need the PR_ENTRYID of the message
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId, $mapifolder);
        $props = mapi_getprops($mapimessage, array(PR_ENTRYID));
        mapi_folder_deletemessages($mapifolder, array($props[PR_ENTRYID]));
    }
}
