<?php

/**
 * Test cases for the eBay Panhandler driver.  You can run these tests
 * using the command
 *
 *     $ phpunit Tests
 *
 * from the top-level Panhandler directory.
 */

require_once("PHPUnit/Framework.php");

require_once("Panhandler.php");
require_once("Drivers/eBay.php");

class eBayDriverTest extends PHPUnit_Framework_TestCase {
    protected $driver;

    protected function setUp() {
        $this->driver = new eBayDriver("CyberSpr-e973-4a45-ad8b-430a8ee3b190");
    }

    public function testMaximumProductCount() {
        $this->driver->set_maximum_product_count(3);

        $products = $this->driver->get_products(
            array('keywords' => array('love hina'))
        );

        $this->assertEquals(3, count($products));
    }

    /**
     * @expectedException PanhandlerNotSupported
     */
    public function testUnsupportedOptions() {
        $this->driver->get_products(
            array('pimp-id' => 'Lobby C. Jones')
        );
    }
}

/* ?> */