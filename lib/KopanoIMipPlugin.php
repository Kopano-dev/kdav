<?php
/***********************************************
* File      :   KopanoIMipPlugin.php
* Project   :   KopanoDAV
* Descr     :   Sends meeting invitations.
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

class KopanoIMipPlugin extends \Sabre\CalDAV\Schedule\IMipPlugin {
    public function __construct(KopanoDavBackend $kDavBackend, KLogger $klogger) {
        $this->kDavBackend = $kDavBackend;
        $this->logger = $klogger;
    }

    /**
     * Sends out meeting invitation.
     *
     * Using the information in iTipMessage to send out a meeting
     * invitation.
     *
     * @param \Sabre\VObject\ITip\Message $iTipMessage
     * @return void
     */

    public function schedule(\Sabre\VObject\ITip\Message $iTipMessage) {
        $this->logger->trace("method: %s - recipient: %s - significantChange: %d - scheduleStatus: %s - message: %s", $iTipMessage->method, $iTipMessage->recipient, $iTipMessage->significantChange, $iTipMessage->scheduleStatus, $iTipMessage->message->serialize());

        if (!$iTipMessage->significantChange) {
            if (!$iTipMessage->scheduleStatus) {
                $iTipMessage->scheduleStatus = "1.0;We got the message, but it's not significant enough to warrant an email";
            }
            return;
        }

        $recipient = preg_replace('!^mailto:!i', '', $iTipMessage->recipient);
        $session = $this->kDavBackend->GetSession();
        $addrbook = $this->kDavBackend->GetAddressBook();
        $store = $this->kDavBackend->GetStore();
        $storeprops = mapi_getprops($store, array(PR_IPM_OUTBOX_ENTRYID, PR_IPM_SENTMAIL_ENTRYID));
        if (!isset($storeprops[PR_IPM_OUTBOX_ENTRYID]) || !isset($storeprops[PR_IPM_SENTMAIL_ENTRYID])) {
            /* handle error */
            $this->logger->error("No outbox!");
            return;
        }

        /* create message and convert */
        $outbox = mapi_msgstore_openentry($store, $storeprops[PR_IPM_OUTBOX_ENTRYID]);
        $newmessage = mapi_folder_createmessage($outbox);
        mapi_icaltomapi($session, $store, $addrbook, $newmessage, $iTipMessage->message->serialize(), false);
        mapi_setprops($newmessage, array(PR_SENTMAIL_ENTRYID => $storeprops[PR_IPM_SENTMAIL_ENTRYID], PR_DELETE_AFTER_SUBMIT => false));

        /* clean the recipients */
        $recipientTable = mapi_message_getrecipienttable($newmessage);
        $recipientRows = mapi_table_queryallrows($recipientTable, array(PR_EMAIL_ADDRESS, PR_ROWID));
        $removeRecipients = array();
        foreach ($recipientRows as $key => $recip) {
            if ($recip[PR_EMAIL_ADDRESS] != $recipient) {
                $removeRecipients[] = $recip;
            }
        }
        mapi_message_modifyrecipients($newmessage, MODRECIP_REMOVE, $removeRecipients);

        /* save message and send */
        mapi_savechanges($newmessage);
        mapi_message_submitmessage($newmessage);
        $iTipMessage->scheduleStatus = '1.1;Scheduling message sent via iMip';
    }
}
