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

        $query = "CREATE TABLE IF NOT EXISTS kdav_sync_state (
             id INTEGER, folderid VARCHAR(255), value TEXT,
             PRIMARY KEY (id, folderid));";
        $this->db->exec($query);

        $query = "CREATE TABLE IF NOT EXISTS kdav_sync_appttsref (
             sourcekey VARCHAR(255), folderid VARCHAR(255),
             appttsref VARCHAR(255),
             PRIMARY KEY (sourcekey, folderid));";
        $this->db->exec($query);
    }

    /**
     * Fetch state information for a folderId (e.g. calenderId) and an id (PR_LOCAL_COMMIT_TIME_MAX).
     *
     * @param string $folderid
     * @param string $id
     *
     * @access public
     * @return string
     */
    public function getState($folderid, $id) {
        $query = "SELECT value FROM kdav_sync_state WHERE folderid = :folderid AND id = :id";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":folderid", $folderid);
        $statement->bindParam(":id", $id);
        $statement->execute();
        $result = $statement->fetch();
        if (!$result)
            return null;
        return $result['value'];
    }

    /**
     * Set state information for a folderId (e.g. calenderId) and an id (PR_LOCAL_COMMIT_TIME_MAX).
     * The state information is the sync token for ICS.
     *
     * @param string $folderid
     * @param string $id
     *
     * @access public
     * @return void
     */
    public function setState($folderid, $id, $value) {
        $query = "REPLACE INTO kdav_sync_state (id, folderid, value) VALUES(:id, :folderid, :value)";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":folderid", $folderid);
        $statement->bindParam(":id", $id);
        $statement->bindParam(":value", $value);
        $statement->execute();
    }

    /**
     * Set the APPTTSREF (custom URL) for a folderId and source key.
     * This is needed for detecting the URL of deleted items reported by ICS.
     *
     * @param string $folderid
     * @param string $id
     * @param string $appttsref
     *
     * @access public
     * @return void
     */
    public function rememberAppttsref($folderid, $sourcekey, $appttsref) {
        $query = "REPLACE INTO kdav_sync_appttsref (folderid, sourcekey, appttsref) VALUES(:folderid, :sourcekey, :appttsref)";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":folderid", $folderid);
        $statement->bindParam(":sourcekey", $sourcekey);
        $statement->bindParam(":appttsref", $appttsref);
        $statement->execute();
    }

    /**
     * Get the APPTTSREF (custom URL) for a folderId and source key.
     * This is needed for detecting the URL of deleted items reported by ICS.
     *
     * @param string $folderid
     * @param string $id
     *
     * @access public
     * @return string
     */
    public function getAppttsref($folderid, $sourcekey) {
        $query = "SELECT appttsref FROM kdav_sync_appttsref WHERE folderid = :folderid AND sourcekey = :sourcekey";
        $statement = $this->db->prepare($query);
        $statement->bindParam(":folderid", $folderid);
        $statement->bindParam(":sourcekey", $sourcekey);
        $statement->execute();
        $result = $statement->fetch();
        if (!$result)
            return null;
        return $result['appttsref'];
    }
}
