<?php
/***********************************************
* File      :   KopanoCardDavBackendTest.php
* Project   :   KopanoDAV
* Descr     :   Tests forKopano Card DAV backend class which
*               handles contact related activities.
*
* Created   :   27.12.2016
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

class KopanoCardDavBackendTest extends \PHPUnit_Framework_TestCase {
    private $kDavBackendMock;
    private $kCardDavBackend;


    /**
     *
     * {@inheritDoc}
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp() {
        $kloggerMock = $this->getMockBuilder(KLogger::class)->disableOriginalConstructor()->getMock();
        $this->kDavBackendMock = $this->getMockBuilder(KopanoDavBackend::class)->disableOriginalConstructor()->getMock();
        $this->kCardDavBackend = new KopanoCardDavBackend($this->kDavBackendMock, $kloggerMock);
    }

    /**
     *
     * {@inheritDoc}
     * @see PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown() {
        $this->kCardDavBackend = null;
        $this->kDavBackendMock = null;
    }

    /**
     * Tests if the constructor is created without errors.
     *
     * @access public
     * @return void
     */
    public function testConstruct() {
        $this->assertTrue(is_object($this->kCardDavBackend));
    }
}
