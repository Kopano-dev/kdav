<?php
/***********************************************
* File      :   KopanoCardDavBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano Card DAV backend class which
*               handles contact related activities.
*
* Created   :   20.12.2016
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

use \Sabre\VObject;

class KopanoCardDavBackend extends \Sabre\CardDAV\Backend\AbstractBackend {
    private $logger;
    protected $kDavBackend;

    const FILE_EXTENSION = '.vcf';
    const CONTAINER_CLASS = 'IPF.Contact';
    const CONTAINER_CLASSES = array('IPF.Contact');

    public function __construct(KopanoDavBackend $kDavBackend, KLogger $klogger) {
        $this->kDavBackend = $kDavBackend;
        $this->logger = $klogger;
    }

    /**
     * Returns the list of addressbooks for a specific user.
     *
     * Every addressbook should have the following properties:
     *   id - an arbitrary unique id
     *   uri - the 'basename' part of the url
     *   principaluri - Same as the passed parameter
     *
     * Any additional clark-notation property may be passed besides this. Some
     * common ones are :
     *   {DAV:}displayname
     *   {urn:ietf:params:xml:ns:carddav}addressbook-description
     *   {http://calendarserver.org/ns/}getctag
     *
     * @param string $principalUri
     * @return array
     */
    public function getAddressBooksForUser($principalUri) {
        $this->logger->trace("principalUri: %s", $principalUri);
        return $this->kDavBackend->GetFolders($principalUri, static::CONTAINER_CLASSES);
    }

    /**
     * Updates properties for an address book.
     *
     * The list of mutations is stored in a Sabre\DAV\PropPatch object.
     * To do the actual updates, you must tell this object which properties
     * you're going to process with the handle() method.
     *
     * Calling the handle method is like telling the PropPatch object "I
     * promise I can handle updating this property".
     *
     * Read the PropPatch documenation for more info and examples.
     *
     * @param string $addressBookId
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
    public function updateAddressBook($addressBookId, \Sabre\DAV\PropPatch $propPatch) {
        // TODO is our logger able to log this object? It probably needs to be adapted.
        $this->logger->trace("addressBookId: %s - proppatch: %s", $addressBookId, $propPatch);
    }

    /**
     * Creates a new address book.
     *
     * This method should return the id of the new address book. The id can be
     * in any format, including ints, strings, arrays or objects.
     *
     * @param string $principalUri
     * @param string $url Just the 'basename' of the url.
     * @param array $properties
     * @return mixed
     */
    public function createAddressBook($principalUri, $url, array $properties) {
        $this->logger->trace("principalUri: %s - url: %s - properties: %s", $principalUri, $url, $properties);
        // TODO Add displayname
        return $this->kDavBackend->CreateFolder($url, static::CONTAINER_CLASS, "");
    }

    /**
     * Deletes an entire addressbook and all its contents
     *
     * @param mixed $addressBookId
     * @return void
     */
    public function deleteAddressBook($addressBookId) {
        $this->logger->trace("addressBookId: %s", $addressBookId);
        $success = $this->kDavBackend->DeleteFolder($addressBookId);
        // TODO evaluate $success
   }

    /**
     * Returns all cards for a specific addressbook id.
     *
     * This method should return the following properties for each card:
     *   * carddata - raw vcard data
     *   * uri - Some unique url
     *   * lastmodified - A unix timestamp
     *
     * It's recommended to also return the following properties:
     *   * etag - A unique etag. This must change every time the card changes.
     *   * size - The size of the card in bytes.
     *
     * If these last two properties are provided, less time will be spent
     * calculating them. If they are specified, you can also ommit carddata.
     * This may speed up certain requests, especially with large cards.
     *
     * @param mixed $addressbookId
     * @return array
     */
    public function getCards($addressbookId) {
        $this->logger->trace("addressbookId: %s", $addressbookId);
        $folder = $this->kDavBackend->GetMapiFolder($addressbookId);
        $properties = getPropIdsFromStrings($this->kDavBackend->GetStore(), ["appttsref" => MapiProps::PROP_APPTTSREF, "goid" => MapiProps::PROP_GOID]);
        $table = mapi_folder_getcontentstable($folder);
        $rows = mapi_table_queryallrows($table, array(PR_SOURCE_KEY, PR_LAST_MODIFICATION_TIME, $properties['appttsref'], $properties['goid']));

        $result = [];
        foreach($rows as $row) {
            $realId = "";
            if (isset($row[$properties['appttsref']]))
                $realId = $row[$properties['appttsref']];
            elseif (isset($row[$properties['goid']]))
                $realId = bin2hex($row[$properties['goid']]);
            if (strlen($realId) == 0)
                $realId = bin2hex($row[PR_SOURCE_KEY]);

            $result[] = [
                'id'            => $realId,
                'uri'           => $realId . static::FILE_EXTENSION,
                'etag'          => '"' . $row[PR_LAST_MODIFICATION_TIME] . '"',
                'lastmodified'  => $row[PR_LAST_MODIFICATION_TIME],
                'addressbookid' => $addressbookId,
            ];
        }
        $this->logger->trace("found %d objects", count($result));
        return $result;
    }

    /**
     * Returns a specfic card.
     *
     * The same set of properties must be returned as with getCards. The only
     * exception is that 'carddata' is absolutely required.
     *
     * If the card does not exist, you must return false.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param ressource $mapifolder     optional mapifolder resource, used if avialable
     * @return array
     */
    public function getCard($addressBookId, $cardUri, $mapifolder = null) {
        $this->logger->trace("addressBookId: %s - cardUri: %s", $addressBookId, $cardUri);

        if (!$mapifolder) {
            $mapifolder = $this->kDavBackend->GetMapiFolder($addressBookId);
        }

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($cardUri, static::FILE_EXTENSION);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($addressBookId, $objectId, $mapifolder);
        if (!$mapimessage) {
            $this->logger->debug("Object NOT FOUND");
            return null;
        }

        $realId = $this->kDavBackend->GetIdOfMapiMessage($mapimessage);

        $session = $this->kDavBackend->GetSession();
        $ab = $this->kDavBackend->GetAddressBook();

        $vcf = mapi_mapitovcf($session, $ab, $mapimessage, array());
        $props = mapi_getprops($mapimessage, array(PR_LAST_MODIFICATION_TIME));
        $r = [
            'id' => $realId,
            'uri' => $realId . static::FILE_EXTENSION,
            'etag' => '"' . $props[PR_LAST_MODIFICATION_TIME] . '"',
            'lastmodified'  => $props[PR_LAST_MODIFICATION_TIME],
            'carddata' => $vcf,
            'size' => strlen($vcf),
            'addressbookid' => $addressBookId,
        ];

        $this->logger->trace("returned data id: %s - size: %d - etag: %s", $r['id'], $r['size'], $r['etag']);

        return $r;
    }

    /**
     * Creates a new card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag is for the
     * newly created resource, and must be enclosed with double quotes (that
     * is, the string itself must contain the double quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    public function createCard($addressBookId, $cardUri, $cardData) {
        $this->logger->trace("addressBookId: %s - cardUri: %s - cardData: %s", $addressBookId, $cardUri, $cardData);

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($cardUri, static::FILE_EXTENSION);
        $store = $this->kDavBackend->GetStore();

        $folder = $this->kDavBackend->GetMapiFolder($addressBookId);
        $mapimessage = mapi_folder_createmessage($folder);

        // we save the objectId in PROP_APPTTSREF so we find it by this id
        $properties = getPropIdsFromStrings($store, ["appttsref" => MapiProps::PROP_APPTTSREF]);
        mapi_setprops($mapimessage, array($properties['appttsref'] => $objectId));
        $retval = $this->setData($mapimessage, $cardData);
        if (!$retval)
            return null;
        return '"' . $retval . '"';
    }

    /**
     * Updates a card.
     *
     * The addressbook id will be passed as the first argument. This is the
     * same id as it is returned from the getAddressBooksForUser method.
     *
     * The cardUri is a base uri, and doesn't include the full path. The
     * cardData argument is the vcard body, and is passed as a string.
     *
     * It is possible to return an ETag from this method. This ETag should
     * match that of the updated resource, and must be enclosed with double
     * quotes (that is: the string itself must contain the actual quotes).
     *
     * You should only return the ETag if you store the carddata as-is. If a
     * subsequent GET request on the same card does not have the same body,
     * byte-by-byte and you did return an ETag here, clients tend to get
     * confused.
     *
     * If you don't return an ETag, you can just return null.
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @param string $cardData
     * @return string|null
     */
    public function updateCard($addressBookId, $cardUri, $cardData) {
        $this->logger->trace("addressBookId: %s - cardUri: %s - cardData: %s", $addressBookId, $cardUri, $cardData);

        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($cardUri, static::FILE_EXTENSION);
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($addressBookId, $objectId);
        $retval = $this->setData($mapimessage, $cardData);
        if (!$retval)
            return null;
        return '"' . $retval . '"';
    }

    private function setData($mapimessage, $vcf) {
        $this->logger->trace("mapimessage: %s - vcf: %s", $mapimessage, $vcf);
        $store = $this->kDavBackend->GetStore();
        $session = $this->kDavBackend->GetSession();

        $ok = mapi_vcftomapi($session, $store, $mapimessage, $vcf);
        if ($ok) {
            mapi_savechanges($mapimessage);
            $props = mapi_getprops($mapimessage, array(PR_LAST_MODIFICATION_TIME));
            return $props[PR_LAST_MODIFICATION_TIME];
        }
        return null;
    }


    /**
     * Deletes a card
     *
     * @param mixed $addressBookId
     * @param string $cardUri
     * @return bool
     */
    public function deleteCard($addressBookId, $cardUri) {
        $this->logger->trace("addressBookId: %s - cardUri: %s", $addressBookId, $cardUri);
        $mapifolder = $this->kDavBackend->GetMapiFolder($addressBookId);
        $objectId = $this->kDavBackend->GetObjectIdFromObjectUri($cardUri, static::FILE_EXTENSION);

        // to delete we need the PR_ENTRYID of the message
        $mapimessage = $this->kDavBackend->GetMapiMessageForId($addressBookId, $objectId, $mapifolder);
        $props = mapi_getprops($mapimessage, array(PR_ENTRYID));
        mapi_folder_deletemessages($mapifolder, array($props[PR_ENTRYID]));
    }
}
