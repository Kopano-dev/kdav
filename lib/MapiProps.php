<?php
/***********************************************
* File      :   MapiProps.php
* Project   :   KopanoDAV
* Descr     :   MAPI Property difinitions.
*
* Created   :   03.01.2017
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

define('PSETID_Kopano_CalDav',                  makeguid("{77536087-CB81-4dc9-9958-EA4C51BE3486}"));

class MapiProps {
    const PROP_APPTTSREF = "PT_STRING8:PSETID_Kopano_CalDav:0x0025"; // dispidApptTsRef
    const PROP_GOID = "PT_BINARY:PSETID_Meeting:0x3";
}