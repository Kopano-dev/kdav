<?php
/***********************************************
* File      :   KopanoSchedulePlugin.php
* Project   :   KopanoDAV
* Descr     :   Checks Free/Busy information of
*               requested recipients.
*
* Created   :   14.02.2018
*
* Copyright 2016 - 2018 Kopano b.v.
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

class KopanoSchedulePlugin extends \Sabre\CalDAV\Schedule\Plugin {
    public function __construct(KopanoDavBackend $kDavBackend, KLogger $klogger) {
        $this->kDavBackend = $kDavBackend;
        $this->logger = $klogger;
    }

    /**
     * Get the Free/Busy information for a recipient.
     *
     * Given email, start and end time the function will return
     * the freebusy blocks.
     *
     * @param string $email
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @param \Sabre\VObject\Component $request
     * @return array
     */

    protected function getFreeBusyForEmail($email, \DateTimeInterface $start, \DateTimeInterface $end, \Sabre\VObject\Component $request) {
        $this->logger->trace("email: %s - start: %d - end: %d", $email, $start->getTimestamp(), $end->getTimestamp());

        $addrbook = $this->kDavBackend->GetAddressBook();
        $fbsupport = mapi_freebusysupport_open($this->kDavBackend->GetSession());
        $email = preg_replace('!^mailto:!i', '', $email);
        $search = array( array( PR_DISPLAY_NAME => $email ) );
        $userarr = mapi_ab_resolvename($addrbook, $search, EMS_AB_ADDRESS_LOOKUP);
        if (!$userarr) {
            return array(
                'request-status' => '3.7;Could not find principal',
                'href' => 'mailto:' . $email,
            );
        }

        $fbDataArray = mapi_freebusysupport_loaddata($fbsupport, array($userarr[0][PR_ENTRYID]));
        if (!$fbDataArray || !$fbDataArray[0]) {
            return array(
                'calendar-data' => null,
                'request-status' => '2.0;Success',
                'href' => 'mailto:' . $email,
            );
        }

        $enumblock = mapi_freebusydata_enumblocks($fbDataArray[0], $start->getTimestamp(), $end->getTimestamp());
        $result = mapi_freebusyenumblock_ical($addrbook, $enumblock, 100, $start->getTimestamp(), $end->getTimestamp(), $email, $email, "");
        if ($result) {
            $vcalendar = \Sabre\VObject\Reader::read($result, \Sabre\VObject\Reader::OPTION_FORGIVING);
            return array(
                'calendar-data' => $vcalendar,
                'request-status' => '2.0;Success',
                'href' => 'mailto:' . $email,
            );
        }
    }
}
