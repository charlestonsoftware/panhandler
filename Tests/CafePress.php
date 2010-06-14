<?php

/**
 * This is a test script for the eBay driver.
 */

require_once('../Panhandler.php');
require_once('../Drivers/CafePress.php');

$cafepress = new CafePressPanhandler("5bh5cfgayv83xdxefxvjchma");

echo "Cafepress Driver suports options...\n";

var_dump($cafepress->get_supported_options());

echo "\nFetching by seller'cybersprocket'...\n";

$products = $cafepress->get_products( array('storeid' => 'cybersprocket') );

foreach ($products as $p) {
    echo $p->name,"\n";
}


?>