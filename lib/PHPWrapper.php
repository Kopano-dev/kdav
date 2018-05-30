<?php
/***********************************************
* File      :   PHPWrapper.php
* Project   :   KopanoDAV
* Descr     :   PHP wrapper class for ICS
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

class PHPWrapper {
    private $store;
    private $logger;
    private $props;
    private $fileext;
    private $added;
    private $modified;
    private $deleted;

    public function __construct($store, $logger, $props, $fileext, $syncstate, $folderid) {
        $this->store = $store;
        $this->logger = $logger;
        $this->props = $props;
        $this->fileext = $fileext;
        $this->syncstate = $syncstate;
        $this->folderid = $folderid;

        $this->added = array();
        $this->modified = array();
        $this->deleted = array();
    }

    /**
     * Accessor for $this->added.
     *
     * @access public
     * @return array
     */
    public function GetAdded() {
        return $this->added;
    }

    /**
     * Accessor for $this->modified.
     *
     * @access public
     * @return array
     */
    public function GetModified() {
        return $this->modified;
    }

    /**
     * Accessor for $this->deleted.
     *
     * @access public
     * @return array
     */
    public function GetDeleted() {
        return $this->deleted;
    }

    /**
     * Returns total changes.
     *
     * @access public
     * @return integer
     */
    public function Total() {
        return count($this->added) + count($this->modified) + count($this->deleted);
    }

    /**
     * Imports a single message.
     *
     * @param array         $props
     * @param long          $flags
     * @param object        $retmapimessage
     *
     * @access public
     * @return long
     */
    public function ImportMessageChange($props, $flags, $retmapimessage) {
        $mapimessage = mapi_msgstore_openentry($this->store, $props[PR_ENTRYID]);
        $messageProps = mapi_getprops($mapimessage, array(PR_SOURCE_KEY, $this->props["appttsref"]));
        $this->logger->trace("got %s (appttsref: %s), flags: %d\n", bin2hex($messageProps[PR_SOURCE_KEY]), $messageProps[$this->props["appttsref"]], $flags);
        if (isset($messageProps[$this->props["appttsref"]])) {
            $appttsref = $messageProps[$this->props["appttsref"]];
            $this->syncstate->rememberAppttsref($this->folderid, bin2hex($messageProps[PR_SOURCE_KEY]), $appttsref);
            $url = $appttsref;
        } else {
            $url = bin2hex($messageProps[PR_SOURCE_KEY]);
        }

        if ($flags == SYNC_NEW_MESSAGE)
            $this->added[] = $url . $this->fileext;
        else
            $this->modified[] = $url . $this->fileext;

        return SYNC_E_IGNORE;
    }

    /**
     * Imports a list of messages to be deleted.
     *
     * @param long          $flags
     * @param array         $sourcekeys     array with sourcekeys
     *
     * @access public
     * @return
     */
    public function ImportMessageDeletion($flags, $sourcekeys) {
        foreach($sourcekeys as $sourcekey) {
            $this->logger->trace("got %s", bin2hex($sourcekey));
            $appttsref = $this->syncstate->getAppttsref($this->folderid, bin2hex($sourcekey));
            if ($appttsref != null) {
                $this->deleted[] = $appttsref . $this->fileext;
            } else {
                $this->deleted[] = bin2hex($sourcekey) . $this->fileext;
            }
        }
    }

    /** Implement MAPI interface */
    public function Config($stream, $flags = 0) {}
    public function GetLastError($hresult, $ulflags, &$lpmapierror) {}
    public function UpdateState($stream) {}
    public function ImportMessageMove($sourcekeysrcfolder, $sourcekeysrcmessage, $message, $sourcekeydestmessage, $changenumdestmessage) { }
    public function ImportPerUserReadStateChange($readstates) { }
    public function ImportFolderChange($props) {return 0;}
    public function ImportFolderDeletion($flags, $sourcekeys) {return 0;}
}
