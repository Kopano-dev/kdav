<?php
/***********************************************
* File      :   KopanoCalDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano CalDAV backend class which
*               handles calendar related activities.
*
* Created   :   26.12.2016
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

class KopanoCalDavBackend extends \Sabre\CalDAV\Backend\AbstractBackend implements \Sabre\CalDAV\Backend\SchedulingSupport, \Sabre\CalDAV\Backend\SyncSupport {
    /*
     * TODO IMPLEMENT
     *
     * SubscriptionSupport,
     * SharingSupport,
     *
     */

    private $logger;
    protected $kDavBackend;

    const FILE_EXTENSION = '.ics';
    const CONTAINER_CLASS = 'IPF.Appointment';
    const CONTAINER_CLASSES = array('IPF.Appointment', 'IPF.Task');

    public function __construct(KopanoDavBackend $kDavBackend, KLogger $klogger) {
        $this->kDavBackend = $kDavBackend;
        $this->logger = $klogger;
    }

    /**
     * Publish free/busy information.
     *
     * Uses the FreeBusyPublish class to publish the information
     * about free/busy status.
     *
     * @param mapiresource $calendar
     * @return void
     */
    private function UpdateFB($calendarId, $calendar) {
        $session = $this->kDavBackend->GetSession();
        $store = $this->kDavBackend->GetStoreById($calendarId);
        $weekUnixTime = 7 * 24 * 60 * 60;
        $start = time() - $weekUnixTime;
        $range = strtotime("+7 weeks");
        $storeProps = mapi_getprops($store, array(PR_MAILBOX_OWNER_ENTRYID));
        if (!isset($storeProps[PR_MAILBOX_OWNER_ENTRYID])) {
            return;
        }
        $pub = new \FreeBusyPublish($session, $store, $calendar, $storeProps[PR_MAILBOX_OWNER_ENTRYID]);
        $pub->publishFB($start, $range);
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
    public function getCalendarsForUser($principalUri) {
        $this->logger->trace("principalUri: %s", $principalUri);
        return $this->kDavBackend->GetFolders($principalUri, static::CONTAINER_CLASSES);
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
    public function createCalendar($principalUri, $calendarUri, array $properties) {
        $this->logger->trace("principalUri: %s - calendarUri: %s - properties: %s", $principalUri, $calendarUri, $properties);
        // TODO Add displayname
        return $this->kDavBackend->CreateFolder($principalUri, $calendarUri, static::CONTAINER_CLASS, "");
    }

    /**
     * Delete a calendar and all its objects.
     *
     * @param string $calendarId
     * @return void
     */
    public function deleteCalendar($calendarId) {
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
    public function getCalendarObjects($calendarId) {
        $result = $this->kDavBackend->GetObjects($calendarId, static::FILE_EXTENSION);
        $this->logger->trace("calendarId: %s found %d objects", $calendarId, count($result));
        return $result;
    }

    /**
     * Performs a calendar-query on the contents of this calendar.
     *
     * The calendar-query is defined in RFC4791 : CalDAV. Using the
     * calendar-query it is possible for a client to request a specific set of
     * object, based on contents of iCalendar properties, date-ranges and
     * iCalendar component types (VTODO, VEVENT).
     *
     * This method should just return a list of (relative) urls that match this
     * query.
     *
     * The list of filters are specified as an array. The exact array is
     * documented by \Sabre\CalDAV\CalendarQueryParser.
     *
     * Note that it is extremely likely that getCalendarObject for every path
     * returned from this method will be called almost immediately after. You
     * may want to anticipate this to speed up these requests.
     *
     * This method provides a default implementation, which parses *all* the
     * iCalendar objects in the specified calendar.
     *
     * This default may well be good enough for personal use, and calendars
     * that aren't very large. But if you anticipate high usage, big calendars
     * or high loads, you are strongly adviced to optimize certain paths.
     *
     * The best way to do so is override this method and to optimize
     * specifically for 'common filters'.
     *
     * Requests that are extremely common are:
     *   * requests for just VEVENTS
     *   * requests for just VTODO
     *   * requests with a time-range-filter on either VEVENT or VTODO.
     *
     * ..and combinations of these requests. It may not be worth it to try to
     * handle every possible situation and just rely on the (relatively
     * easy to use) CalendarQueryValidator to handle the rest.
     *
     * Note that especially time-range-filters may be difficult to parse. A
     * time-range filter specified on a VEVENT must for instance also handle
     * recurrence rules correctly.
     * A good example of how to interprete all these filters can also simply
     * be found in \Sabre\CalDAV\CalendarQueryFilter. This class is as correct
     * as possible, so it gives you a good idea on what type of stuff you need
     * to think of.
     *
     * @param mixed $calendarId
     * @param array $filters
     * @return array
     */
    public function calendarQuery($calendarId, array $filters) {
        $start = $end = null;
        $types = array();
        foreach ($filters['comp-filters'] as $filter) {
            $this->logger->trace("got filter: %s", $filter);

            if ($filter['name'] == 'VEVENT') {
                $types[] = 'IPM.Appointment';
            }
            elseif ($filter['name'] == 'VTODO') {
                $types[] = 'IPM.Task';
            }

            /* will this work on tasks? */
            if (is_array($filter['time-range']) && isset($filter['time-range']['start'], $filter['time-range']['end'])) {
                $start = $filter['time-range']['start']->getTimestamp();
                $end = $filter['time-range']['end']->getTimestamp();
            }
        }

        $objfilters = array();
        if ($start != null && $end != null) {
            $objfilters["start"] = $start;
            $objfilters["end"] = $end;
        }
        if (!empty($types)) {
            $objfilters["types"] = $types;
        }

        $objects = $this->kDavBackend->GetObjects($calendarId, static::FILE_EXTENSION, $objfilters);
        $result = [];
        foreach ($objects as $object) {
            $result[] = $object['uri'];
        }
        return $result;
    }

    /**
     * Returns information from a single calendar object, based on its object uri.
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
     * @param ressource $mapifolder     optional mapifolder resource, used if available
     * @return array|null
     */
    public function getCalendarObject($calendarId, $objectUri, $mapifolder = null) {
        $this->logger->trace("calendarId: %s - objectUri: %s - mapifolder: %s", $calendarId, $objectUri, $mapifolder);

        if (!$mapifolder) {
            $mapifolder = $this->kDavBackend->GetMapiFolder($calendarId);
        }

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId, $mapifolder);
        if (!$mapimessage) {
            $this->logger->error("Object NOT FOUND");
            return null;
        }

        $realId = $this->kDavBackend->GetIdOfMapiMessage($calendarId, $mapimessage);

        // this should be cached or moved to kDavBackend
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $ics = mapi_mapitoical($session, $ab, $mapimessage, array());
        if (!$ics && mapi_last_hresult()) {
            $this->logger->error("Error generating ical, error code: 0x%08X", mapi_last_hresult());
            return null;
        }
        if (!$ics) {
            $this->logger->error("Error generating ical, unknown error");
            return null;
        }

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
    public function createCalendarObject($calendarId, $objectUri, $calendarData) {
        $this->logger->trace("calendarId: %s - objectUri: %s - calendarData: %s", $calendarId, $objectUri, $calendarData);
        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $folder = $this->kDavBackend->GetMapiFolder($calendarId);
        $mapimessage = $this->kDavBackend->CreateObject($calendarId, $folder, $objectId);
        $retval = $this->setData($calendarId, $mapimessage, $calendarData);
        if (!$retval) {
            return null;
        }
        $this->UpdateFB($calendarId, $folder);
        return '"' . $retval . '"';
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
    public function updateCalendarObject($calendarId, $objectUri, $calendarData) {
        $this->logger->trace("calendarId: %s - objectUri: %s - calendarData: %s", $calendarId, $objectUri, $calendarData);

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);
        $folder = $this->kDavBackend->GetMapiFolder($calendarId);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId);
        $retval = $this->setData($calendarId, $mapimessage, $calendarData);
        if (!$retval) {
            return null;
        }
        $this->UpdateFB($calendarId, $folder);
        return '"' . $retval . '"';
    }

    private function setData($calendarId, $mapimessage, $ics) {
        $this->logger->trace("mapimessage: %s - ics: %s", $mapimessage, $ics);
        // this should be cached or moved to kDavBackend
        $store = $this->kDavBackend->GetStoreById($calendarId);
        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $ok = mapi_icaltomapi($session, $store, $ab, $mapimessage, $ics, false);
        if (!$ok && mapi_last_hresult()) {
            $this->logger->error("Error updating mapi object, error code: 0x%08X", mapi_last_hresult());
            return null;
        }
        if (!$ok) {
            $this->logger->error("Error updating mapi object, unknown error");
            return null;
        }

        mapi_savechanges($mapimessage);
        $props = mapi_getprops($mapimessage, array(PR_LAST_MODIFICATION_TIME));
        return $props[PR_LAST_MODIFICATION_TIME];
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
    public function deleteCalendarObject($calendarId, $objectUri) {
        $this->logger->trace("calendarId: %s - objectUri: %s", $calendarId, $objectUri);

        $mapifolder = $this->kDavBackend->GetMapiFolder($calendarId);
        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($objectUri, static::FILE_EXTENSION);

        // to delete we need the PR_ENTRYID of the message
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($calendarId, $objectId, $mapifolder);
        $props = mapi_getprops($mapimessage, array(PR_ENTRYID));
        mapi_folder_deletemessages($mapifolder, array($props[PR_ENTRYID]));
    }

    /**
     * Return a single scheduling object.
     *
     * TODO: Add implementation.
     *
     * @param string $principalUri
     * @param string $objectUri
     * @return array
     */
    public function getSchedulingObject($principalUri, $objectUri) {
        $this->logger->trace("principalUri: %s - objectUri: %s", $principalUri, $objectUri);
        return array();
    }

    /**
     * Returns scheduling objects for the principal URI.
     *
     * TODO: Add implementation.
     *
     * @param string $principalUri
     * @return array
     */
    public function getSchedulingObjects($principalUri) {
        $this->logger->trace("principalUri: %s", $principalUri);
        return array();
    }

    /**
     * Delete scheduling object.
     *
     * TODO: Add implementation.
     *
     * @param string $principalUri
     * @param string $objectUri
     * @return void
     */
    public function deleteSchedulingObject($principalUri, $objectUri) {
        $this->logger->trace("principalUri: %s - objectUri: %s", $principalUri, $objectUri);
    }

    /**
     * Create a new scheduling object.
     *
     * TODO: Add implementation.
     *
     * @param string $principalUri
     * @param string $objectUri
     * @param string $objectData
     * @return void
     */
    public function createSchedulingObject($principalUri, $objectUri, $objectData) {
        $this->logger->trace("principalUri: %s - objectUri: %s - objectData: %s", $principalUri, $objectUri, $objectData);
    }

    /**
     * Return CTAG for scheduling inbox.
     *
     * TODO: Add implementation.
     *
     * @param string $principalUri
     * @return string
     */
    public function getSchedulingInboxCtag($principalUri) {
        $this->logger->trace("principalUri: %s", $principalUri);
        return "empty";
    }

    /**
     * The getChanges method returns all the changes that have happened, since
     * the specified syncToken in the specified calendar.
     *
     * This function should return an array, such as the following:
     *
     * [
     *   'syncToken' => 'The current synctoken',
     *   'added'   => [
     *      'new.txt',
     *   ],
     *   'modified'   => [
     *      'modified.txt',
     *   ],
     *   'deleted' => [
     *      'foo.php.bak',
     *      'old.txt'
     *   ]
     * );
     *
     * The returned syncToken property should reflect the *current* syncToken
     * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
     * property This is * needed here too, to ensure the operation is atomic.
     *
     * If the $syncToken argument is specified as null, this is an initial
     * sync, and all members should be reported.
     *
     * The modified property is an array of nodenames that have changed since
     * the last token.
     *
     * The deleted property is an array with nodenames, that have been deleted
     * from collection.
     *
     * The $syncLevel argument is basically the 'depth' of the report. If it's
     * 1, you only have to report changes that happened only directly in
     * immediate descendants. If it's 2, it should also include changes from
     * the nodes below the child collections. (grandchildren)
     *
     * The $limit argument allows a client to specify how many results should
     * be returned at most. If the limit is not specified, it should be treated
     * as infinite.
     *
     * If the limit (infinite or not) is higher than you're willing to return,
     * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
     *
     * If the syncToken is expired (due to data cleanup) or unknown, you must
     * return null.
     *
     * The limit is 'suggestive'. You are free to ignore it.
     *
     * @param string $calendarId
     * @param string $syncToken
     * @param int $syncLevel
     * @param int $limit
     * @return array
     */
    public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null) {
        //TODO - implement limit
        $this->logger->trace("calendarId: %s - syncToken: %s - syncLevel: %d - limit: %d", $calendarId, $syncToken, $syncLevel, $limit);
        return $this->kDavBackend->Sync($calendarId, $syncToken, static::FILE_EXTENSION);
    }
}
