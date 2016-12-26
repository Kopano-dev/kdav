<?php
/***********************************************
* File      :   KopanoCalDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano CalDAV backend class which
*               handles caldendar related activities.
*
* Created   :   26.12.2016
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

class KopanoCalDavBackend extends \Sabre\CalDAV\Backend\AbstractBackend {
    /*
     * TODO IMPLEMENT
     *
     * SyncSupport,
     * SubscriptionSupport,
     * SchedulingSupport,
     * SharingSupport,
     * add ICSExportPlugin to allow export of an ics file (all events in one file)
     *
     */


    protected $kDavBackend;

    public function __construct(KopanoDavBackend $kDavBackend) {
        $this->kDavBackend = $kDavBackend;
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
        return $this->kDavBackend->GetFolders($principalUri, 'IPF.Appointment');
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
       // TODO implement, returns id
    }

    /**
     * Delete a calendar and all it's objects
     *
     * @param string $calendarId
     * @return void
     */
    function deleteCalendar($calendarId) {
        // TODO implement
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
        // TODO: retrival (search & opening) should be done in KopanoDavBackend and be used in CardDav as well
        $folder = $this->kDavBackend->GetMapiFolder($calendarId);

        $table = mapi_folder_getcontentstable($folder);
        $rows = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_LAST_MODIFICATION_TIME));

        $result = [];
        foreach($rows as $row) {
            $result[] = $this->getCalendarObject($calendarId, bin2hex($row[PR_ENTRYID]).'.ics');
        }
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
     * @return array|null
     */
    function getCalendarObject($calendarId, $objectUri) {
        // TODO Check if retrival (search & opening) could be done in KopanoDavBackend and be used in CardDav as well

        // cut off '.ics'
        $objectId = substr($objectUri, 0, -4);
        if (!$this->kDavBackend->IsOurId($objectId)) {
            error_log("getCalendarObject: not our ID: ". $objectUri);
            return null;
        }


        // this should be cached or moved to kDavBackend
        $store = $this->kDavBackend->GetStore();
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $msg = mapi_msgstore_openentry($store, hex2bin($objectId));

        $ics = mapi_mapitoical($session, $ab, $msg, array());
        $props = mapi_getprops($msg, array(PR_LAST_MODIFICATION_TIME));


        $r = [
                'id'            => $objectId,
                'uri'           => $objectUri,
                'etag'          => '"' . $props[PR_LAST_MODIFICATION_TIME] . '"',
                'lastmodified'  => $props[PR_LAST_MODIFICATION_TIME],
                'calendarid'    => $objectId,
                'size'          => strlen($ics),
                'calendardata'  => $ics,
        ];
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
        // TODO create & save should be done in KopanoDavBackend, the actually setting of data could be done here - use same functionality as in updateCalendarObject
        error_log("createCalendarObject($calendarId, $objectUri, $calendarData)");
        $folder = $this->kDavBackend->GetMapiFolder($calendarId);
        $msg = mapi_folder_createmessage($folder);
        return $this->setData($msg, $calendarData);
    }

    /**
     * Updates an existing calendarobject, based on it's uri.
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
        // TODO open & save should be implemneted in KopanoDavBackend, the actually setting of data should be done here - use same functionality as in createCalendarObject
        //$folder = $this->kDavBackend->GetMapiFolder($calendarId);
        // cut off '.ics'
        $objectId = substr($objectUri, 0, -4);
        $store = $this->kDavBackend->GetStore();
        $msg = mapi_msgstore_openentry($store, hex2bin($objectId));
        return $this->setData($msg, $calendarData);
    }

    private function setData($msg, $ics) {
        // this should be cached or moved to kDavBackend
        $store = $this->kDavBackend->GetStore();
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $ok = mapi_icaltomapi($session, $store, $ab, $msg, $ics, false);
        if ($ok) {
            mapi_message_savechanges($msg);
            $props = mapi_getprops($msg, array(PR_LAST_MODIFICATION_TIME));
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
        // TODO should be implemented in KopanoDavBackend and be used also for CardDav
        $folder = $this->kDavBackend->GetMapiFolder($calendarId);
        $objectId = substr($objectUri, 0, -4);
        mapi_folder_deletemessages($folder, array(hex2bin($objectId)));
    }

}