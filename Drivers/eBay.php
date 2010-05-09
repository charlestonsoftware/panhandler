<?php

/**
 * This file implements the Panhandler interface for eBay.
 */

require_once('../Panhandler.php');

if (function_exists('simplexml_load_string') === false) {
    die("SimpleXML must be installed to use eBayPanhandler");
}
if (function_exists('curl_init') === false) {
    die("cURL must be installed to use eBayPanhandler");
}

final class eBayPanhandler implements Panhandles {

    //// PRIVATE MEMBERS ///////////////////////////////////////

    /**
     * URL for invoking eBay's services.
     */
    private $ebay_service_url = "http://svcs.ebay.com/services/search/FindingService/v1";

    /**
     * The AppID given to us by eBay.
     */
    private $app_id;

    /**
     * The number of products that we return.  The value can be
     * changed by set_maximum_product_count().
     */
    private $maximum_product_count = 10;

    /**
     * The page of results we want to show.
     */
    private $results_page = 1;

    //// CONSTRUCTOR ///////////////////////////////////////////

    /**
     * We have to pass in the AppID that eBay gives us, as we need
     * this to fetch product information.
     */
    public function __construct($app_id) {
        $this->app_id = $app_id;
    }

    //// INTERFACE METHODS /////////////////////////////////////

    public function get_products_by_keywords($keywords, $options = null) {
        return $this->extract_products(
            $this->get_response_xml($keywords)
        );
    }

    public function set_maximum_product_count($count) {
        $this->maximum_product_count = $count;
    }

    public function set_results_page($page_number) {
        $this->results_page = $page_number;
    }

    //// PRIVATE METHODS ///////////////////////////////////////

    /**
     * Takes an array of keywords and returns the URL we need to send
     * an HTTP request to in order to get products matching those
     * keywords.
     */
    private function make_request_url($keywords) {
        $options = array(
            'OPERATION-NAME'       => 'findItemsByKeywords',
            'SERVICE-VERSION'      => '1.0.0',
            'SECURITY-APPNAME'     => $this->app_id,
            'RESPONSE-DATA-FORMAT' => 'XML',
            'REST-PAYLOAD'         => null,
            'paginationInput.entriesPerPage' => $this->maximum_product_count,
            'paginationInput.pageNumber' => $this->results_page,
            'keywords'             => urlencode(implode(' ', $keywords))
        );

        return sprintf(
            "%s?%s",
            $this->ebay_service_url,
            http_build_query($options)
        );
    }

    /**
     * Makes a GET request to the given URL and returns the result as
     * a string.
     */
    private function http_get($url) {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($handle);
        curl_close($handle);

        return $response;
    }

    /**
     * Returns a SimpleXML object representing the search results for
     * the given keywords.
     */
    private function get_response_xml($keywords) {
        return simplexml_load_string(
            $this->http_get(
                $this->make_request_url($keywords)
            )
        );
    }

    /**
     * Takes a SimpleXML object representing an <item> node in search
     * results and returns a PanhandlerProduct object for that item.
     */
    private function convert_item($item) {
        $product            = new PanhandlerProduct();
        $product->name       = (string) $item->title;
        $product->price      = (string) $item->sellingStatus->currentPrice;
        $product->web_urls   = array((string) $item->viewItemURL);
        $product->image_urls = array((string) $item->galleryURL);
        return $product;
    }

    /**
     * Takes a SimpleXML object representing all keyword search
     * results and returns an array of PanhandlerProduct objects
     * representing every item in the results.
     */
    private function extract_products($xml) {
        $products = array();

        foreach ($xml->searchResult->item as $item) {
            $products[] = $this->convert_item($item);
        }

        return $products;
    }

}

?>