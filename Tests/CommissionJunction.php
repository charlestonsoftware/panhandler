<?php

require_once('../Drivers/CommissionJunction.php');

$web_id = "3431922";
$cj_key = "00c7706a078e54be160c0044c825954982bbc36aa85831dff4899592b02ec9e8ccddfefa6fe146bd95eec2e4a815be52b662109fc6cec061bb32bd9d524b892711/00a7220ca9025cffe327f17db2a0d4478358687e544f7c946ab075f934ce310476568115c15aa83be40103cb6008a5cfe5d8f2e543707e0b0ecab1776cea7ba755";

$keywords = array('cakes');

$cj = new CommissionJunctionDriver($cj_key, $web_id);
$cj->set_maximum_product_count(5);
$cj->set_results_page(3);

var_dump($cj->get_products_by_keywords($keywords));


?>