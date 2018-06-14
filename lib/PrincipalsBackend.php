<?php
/***********************************************
* File      :   PrincipalsBackend.php
* Project   :   KopanoDAV
* Descr     :   Kopano DAV Principals backend class.
*
* Created   :   20.12.2016
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

class PrincipalsBackend implements \Sabre\DAVACL\PrincipalBackend\BackendInterface {

    protected $kdavBackend;

    /**
     * Constructor.
     *
     * @param KopanoDavBackend $kdavBackend
     */
    public function __construct(KopanoDavBackend $kdavBackend) {
        $this->kdavBackend = $kdavBackend;
    }

    /**
     * Returns a list of principals based on a prefix.
     *
     * This prefix will often contain something like 'principals'. You are only
     * expected to return principals that are in this base path.
     *
     * You are expected to return at least a 'uri' for every user, you can
     * return any additional properties if you wish so. Common properties are:
     *   {DAV:}displayname
     *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
     *     field that's actually injected in a number of other properties. If
     *     you have an email address, use this property.
     *
     * @param string $prefixPath
     * @return array
     */
    public function getPrincipalsByPrefix($prefixPath) {
        $principals = array();
        if ($prefixPath === 'principals') {
            $principals[] = $this->getPrincipalByPath($prefixPath);
            $principals[] = $this->getPrincipalByPath('principals/public');
        }
        return $principals;
    }

    /**
     * Returns a specific principal, specified by it's path.
     * The returned structure should be the exact same as from
     * getPrincipalsByPrefix.
     *
     * @param string $path
     * @return array
     */
    public function getPrincipalByPath($path) {
        if ($path === 'principals/public') {
            return array(
                'id' => 'public',
                'uri' => 'principals/public',
                '{DAV:}displayname' => 'Public',
                '{http://sabredav.org/ns}email-address' => 'postmaster@localhost'
            );
        }
        if ($path === 'principals') {
            $username = $this->kdavBackend->GetUser();
        }
        else {
            $username = str_replace('principals/', '', $path);
        }
        $userinfo = mapi_zarafa_getuser_by_name($this->kdavBackend->GetStore($username), $username);
        if (!$userinfo) {
            return false;
        }
        $emailaddress = (isset($userinfo['emailaddress']) && $userinfo['emailaddress']) ? $userinfo['emailaddress'] : false;
        $fullname = (isset($userinfo['fullname']) && $userinfo['fullname']) ? $userinfo['fullname'] : false;

        return array(
                    'id'                                        => $username,
                    'uri'                                       => 'principals/' . $username,
                    '{DAV:}displayname'                         => $fullname,
                    '{http://sabredav.org/ns}email-address'     => $emailaddress,
                    // TODO 'vcardurl' shoudl be set, see here: http://sabre.io/dav/principals/
            );
    }

    /**
     * Updates one ore more webdav properties on a principal.
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
     * @param string $path
     * @param \Sabre\DAV\PropPatch $propPatch
     * @return void
     */
    public function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {

    }

    /**
     * This method is used to search for principals matching a set of
     * properties.
     *
     * This search is specifically used by RFC3744's principal-property-search
     * REPORT.
     *
     * The actual search should be a unicode-non-case-sensitive search. The
     * keys in searchProperties are the WebDAV property names, while the values
     * are the property values to search on.
     *
     * By default, if multiple properties are submitted to this method, the
     * various properties should be combined with 'AND'. If $test is set to
     * 'anyof', it should be combined using 'OR'.
     *
     * This method should simply return an array with full principal uri's.
     *
     * If somebody attempted to search on a property the backend does not
     * support, you should simply return 0 results.
     *
     * You can also just return 0 results if you choose to not support
     * searching at all, but keep in mind that this may stop certain features
     * from working.
     *
     * @param string $prefixPath
     * @param array $searchProperties
     * @param string $test
     * @return array
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {

    }

    /**
     * Finds a principal by its URI.
     *
     * This method may receive any type of uri, but mailto: addresses will be
     * the most common.
     *
     * Implementation of this API is optional. It is currently used by the
     * CalDAV system to find principals based on their email addresses. If this
     * API is not implemented, some features may not work correctly.
     *
     * This method must return a relative principal path, or null, if the
     * principal was not found or you refuse to find it.
     *
     * @param string $uri
     * @param string $principalPrefix
     * @return string
     */
    public function findByUri($uri, $principalPrefix) {

    }

    /**
     * Returns the list of members for a group-principal
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMemberSet($principal) {
        return array();
    }

    /**
     * Returns the list of groups a principal is a member of
     *
     * @param string $principal
     * @return array
     */
    public function getGroupMembership($principal) {
        return array();
    }

    /**
     * Updates the list of group members for a group principal.
     *
     * The principals should be passed as a list of uri's.
     *
     * @param string $principal
     * @param array $members
     * @return void
     */
    public function setGroupMemberSet($principal, array $members) {

    }

}
