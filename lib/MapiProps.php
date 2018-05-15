<?php
/***********************************************
* File      :   MapiProps.php
* Project   :   KopanoDAV
* Descr     :   MAPI Property difinitions.
*
* Created   :   03.01.2017
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

define('PSETID_Kopano_CalDav',                  makeguid("{77536087-CB81-4dc9-9958-EA4C51BE3486}"));

class MapiProps {
    const PROP_APPTTSREF = "PT_STRING8:PSETID_Kopano_CalDav:0x0025"; // dispidApptTsRef

    /**
     *
     * Returns appointment specific MAPI properties
     * Origins: Z-Push
     *
     * @access public
     *
     * @return array
     */
    public static function GetAppointmentProperties() {
        return array(
            "sourcekey"             => PR_SOURCE_KEY,
            "representingentryid"   => PR_SENT_REPRESENTING_ENTRYID,
            "representingname"      => PR_SENT_REPRESENTING_NAME,
            "sentrepresentingemail" => PR_SENT_REPRESENTING_EMAIL_ADDRESS,
            "sentrepresentingaddt"  => PR_SENT_REPRESENTING_ADDRTYPE,
            "sentrepresentinsrchk"  => PR_SENT_REPRESENTING_SEARCH_KEY,
            "reminderset"           => "PT_BOOLEAN:PSETID_Common:0x8503",
            "remindertime"          => "PT_LONG:PSETID_Common:0x8501",
            "meetingstatus"         => "PT_LONG:PSETID_Appointment:0x8217",
            "isrecurring"           => "PT_BOOLEAN:PSETID_Appointment:0x8223",
            "recurringstate"        => "PT_BINARY:PSETID_Appointment:0x8216",
            "timezonetag"           => "PT_BINARY:PSETID_Appointment:0x8233",
            "timezonedesc"          => "PT_STRING8:PSETID_Appointment:0x8234",
            "recurrenceend"         => "PT_SYSTIME:PSETID_Appointment:0x8236",
            "responsestatus"        => "PT_LONG:PSETID_Appointment:0x8218",
            "commonstart"           => "PT_SYSTIME:PSETID_Common:0x8516",
            "commonend"             => "PT_SYSTIME:PSETID_Common:0x8517",
            "reminderstart"         => "PT_SYSTIME:PSETID_Common:0x8502",
            "duration"              => "PT_LONG:PSETID_Appointment:0x8213",
            "private"               => "PT_BOOLEAN:PSETID_Common:0x8506",
            "uid"                   => "PT_BINARY:PSETID_Meeting:0x23",
            "sideeffects"           => "PT_LONG:PSETID_Common:0x8510",
            "flagdueby"             => "PT_SYSTIME:PSETID_Common:0x8560",
            "icon"                  => PR_ICON_INDEX,
            "mrwassent"             => "PT_BOOLEAN:PSETID_Appointment:0x8229",
            "endtime"               => "PT_SYSTIME:PSETID_Appointment:0x820e",//this is here for calendar restriction, tnef and ical
            "starttime"             => "PT_SYSTIME:PSETID_Appointment:0x820d",//this is here for calendar restriction, tnef and ical
            "clipstart"             => "PT_SYSTIME:PSETID_Appointment:0x8235", //ical only
            "recurrencetype"        => "PT_LONG:PSETID_Appointment:0x8231",
            "body"                  => PR_BODY,
            "rtfcompressed"         => PR_RTF_COMPRESSED,
            "html"                  => PR_HTML,
            "rtfinsync"             => PR_RTF_IN_SYNC,
        );
    }
}
