<?php

/**
 * This is a test script for the eBay driver.  If you run this script
 * from the command line you will see a list of twenty 'Love Hina'
 * related products.
 */

require_once('../Drivers/eBay.php');

$ebay     = new eBayPanhandler("CyberSpr-e973-4a45-ad8b-430a8ee3b190");
$keywords = array('love hina', 'anime');

$ebay->set_maximum_product_count(5);
$ebay->set_results_page(2);

$products = $ebay->get_products_by_keywords($keywords);

foreach ($products as $p) {
    echo $p->name,"\n";
}

?>