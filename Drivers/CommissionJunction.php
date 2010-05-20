<?php

if (function_exists('curl_init') === false) {
  throw new PanhandlerMissingRequirement('cURL must be installed to use the Commission Junction driver.');
}
if (function_exists('simplexml_load_string') === false) {
  throw new PanhandlerMissingRequirement('SimpleXML must be installed to use the Commission Junction driver.');
}

final class CommissionJunctionDriver implements Panhandles {

    //// PRIVATE MEMBERS ///////////////////////////////////////

    /**
     * The URL for Commission Junction's API.
     */
    private $cj_search_url = 'https://product-search.api.cj.com/v2/product-search';

    /**
     * Our authorization key for Commission Junction.
     */
    private $cj_key;

    /**
     * Our web ID for Commission Junction.
     */
    private $cj_web_id;

    /**
     * Maximum number of results to return.  This value is set by
     * calling set_maximum_product_count().  Comission Junction does
     * not allow this value to be greater than 1,000.  If it is larger
     * than that, then only 1,000 results will be returned.
     */
    private $maximum_product_count = 50;

    /**
     * The page number of results to return.  According to the
     * Commission Junction documentation, the page count starts out
     * zero.  But in practice this does not appear to be the case.
     * Setting 'page-number' to zero in the request returns no
     * results.  So we default to one as the value for the page
     * number.
     */
    private $results_page = 1;

    //// CONSTRUCTOR ///////////////////////////////////////////

    public function __construct($cj_key, $cj_web_id) {
        $this->cj_key    = $cj_key;
        $this->cj_web_id = $cj_web_id;
    }

    //// INTERFACE METHODS /////////////////////////////////////

    /**
     * $options can include 'advertiser-ids', whose value should be a
     * string of advertiser IDs separated by commas.
     */
    public function get_products_by_keywords($keywords, $options = null) {
        $advertisers = @$options['advertiser-ids']
            or $advertisers = array();

        return $this->extract_products(
            simplexml_load_string(
                $this->query_for_products(
                    $this->make_request_url($keywords, $advertisers)
                )
            )
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
     * Returns the URL we need to send an HTTP GET request to in order
     * to get product search results.  Accepts an array of keywords to
     * search for, and an optional array of advertiser IDs to use in
     * order to restrict the search.
     */
    private function make_request_url($keywords, $advertisers = array()) {
        $parameters = array(
            'website-id'       => $this->cj_web_id,
            'serviceable-area' => 'US',
            'currency'         => 'USD',
            'records-per-page' => $this->maximum_product_count,
            'page-number'      => $this->results_page,
            'keywords'         => implode(' ', $keywords)
        );

        if (count($advertisers)) {
            $parameters = array_merge(
                $parameters,
                array('advertiser-ids' => implode(' ', $advertisers))
            );
        }

        return sprintf(
            '%s?%s',
            $this->cj_search_url,
            http_build_query($parameters)
        );
    }

    /**
     * Returns as a string the response from querying Commission
     * Junction at the given URL.  The URL is assumed to have all the
     * necessary GET paramaters in it.  See make_request_url() for
     * this purpose.
     */
    private function query_for_products($url) {
        $handle = curl_init($url);

        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER,
                    array('Authorization: ' . $this->cj_key));

        $response = curl_exec($handle);
        curl_close($handle);

        return $response;
    }

    /**
     * Takes a <product> node from search results and returns a
     * PanhandlerProduct object.
     */
    private function convert_product($node) {
        $product             = new PanhandlerProduct();
        $product->name        = (string) $node->name;
        $product->description = (string) $node->description;
        $product->web_urls    = array((string) $node->{'buy-url'});
        $product->image_urls  = array((string) $node->{'image-url'});
        $product->price       = (string) $node->price;
        return $product;
    }

    /**p
     * Extracts all <product> nodes from search results and returns an
     * array of PanhandlerProduct objects representing the results.
     */
    private function extract_products($xml) {
        $products = array();

        foreach ($xml->xpath("//product") as $product) {
            $products[] = $this->convert_product($product);
        }

        return $products;
    }

}

?>