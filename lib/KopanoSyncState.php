<?php
/***********************************************
* File      :   KopanoSyncState.php
* Project   :   KopanoDAV
* Descr     :   Class for handling sync state
*
* Created   :   15.05.2018
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

class KopanoSyncState {
    private $db;

    public function __construct($logger, $dbstring) {
        $this->logger = $logger;
        $this->logger->trace("Using db %s", $dbstring);
        $this->db = new \PDO($dbstring);

        $query = "CREATE TABLE IF NOT EXISTS sync_state (
             id INTEGER PRIMARY KEY, calendarid VARCHAR(255), value TEXT)";
        $this->db->exec($query);
    }

    public function getState($calendarid, $id) {
        $query = "SELECT value FROM sync_state WHERE calendarid = :calendarid AND id = :id";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":calendarid", $calendarid);
        $statement->bindParam(":id", $id);
        $statement->execute();
        $result = $statement->fetch();
        if (!$result)
            return null;
        return $result['value'];
    }

    public function setState($calendarid, $id, $value) {
        $query = "REPLACE INTO sync_state (id, calendarid, value) VALUES(:id, :calendarid, :value)";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":calendarid", $calendarid);
        $statement->bindParam(":id", $id);
        $statement->bindParam(":value", $value);
        $statement->execute();
    }
}
