<?php
/***********************************************
* File      :   KopanoCardDavBackendTest.php
* Project   :   KopanoDAV
* Descr     :   Tests forKopano Card DAV backend class which
*               handles contact related activities.
*
* Created   :   27.12.2016
*
* Copyright 2016 Kopano b.v.
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

class KopanoCardDavBackendTest extends \PHPUnit_Framework_TestCase {

    public function testConstruct() {
        $kDavBackendMock = $this->getMockBuilder(KopanoDavBackend::class)
                     ->setMethods()
                     ->getMock();
        $kCardDavBackend = new KopanoCardDavBackend($kDavBackendMock);
        $this->assertTrue(is_object($kCardDavBackend));
    }
}
