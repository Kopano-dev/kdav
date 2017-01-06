<?php
/***********************************************
* File      :   Utils.php
* Project   :   KopanoDAV
* Descr     :   Several utility functions.
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

class Utils {

    /**
     * Checks if the PHP-MAPI extension is available and in a requested version.
     *
     * @param string    $version    the version to be checked ("6.30.10-18495", parts or build number)
     *
     * @access public
     * @return boolean installed version is superior to the checked string
     */
    public static function CheckMapiExtVersion($version = "") {
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
     * Format bytes to a more human readable value.
     * @param int $bytes
     * @param int $precision
     *
     * @access public
     * @return void|string
     */
    public static function FormatBytes($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';

        $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB');
        $base = log ($bytes, 1024);
        $fBase = floor($base);
        $pow = pow(1024, $base - $fBase);
        return sprintf ("%.{$precision}f %s", $pow, $units[$fBase]);
    }

    /**
     * Parses and returns an ecoded vCal-Uid from an OL compatible GlobalObjectID.
     *
     * @param string    $olUid      an OL compatible GlobalObjectID as HEX
     *
     * @access public
     * @return string   the vCal-Uid if available in the olUid, else the original olUid
     */
    public static function GetICalUidFromOLUid($olUid){
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
     * @return boolean
     */
    public static function IsEncodedVcalUid($uid) {
        return ctype_xdigit($uid) && stristr(hex2bin($uid), "vCal-Uid") !== false;
    }

    /**
     * Indicates if a guiven UID is an OL GOID or not.
     *
     * @param string $uid
     * @return boolean
     */
    public static function IsOutlookUid($uid) {
        return 0 === stripos($uid, '040000008200E00074C5B7101A82E008');
    }

    /**
     * Checks if it's a valid UUID as specified in RFC4122.
     *
     * @param string    $uuid
     *
     * @access public
     * @return boolean
     */
    public static function IsValidUUID($uuid) {
        return !!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }


    /**
     * Checks the given UID if it is an OL compatible GlobalObjectID
     * If not, the given UID is encoded inside the GlobalObjectID
     *
     * @param string    $icalUid    an appointment uid as HEX
     *
     * @access public
     * @return string   an OL compatible GlobalObjectID
     *
     */
    public static function GetOLUidFromICalUid($icalUid) {
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