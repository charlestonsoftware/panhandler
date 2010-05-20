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
     * This holds all of our default values, as well as a list of the
     * parameters that the CJ api can accept.
     */
    private $defaults = array(
                              'advertiser-ids' => 'joined',
                              'keywords' => '',
                              'serviceable-area' => 'US',
                              'isbn' => '',
                              'upc' => '',
                              'manufacturer-name' => '',
                              'manufacturer-sku' => '',
                              'advertiser-sku' => '',
                              'low-price' => '',
                              'high-price' => '',
                              'low-sale-price' => '',
                              'high-sale-price' => '',
                              'currency' => 'USD',
                              'sort-by' => '',
                              'sort-order' => '',

                              /**
                               * The page number of results to return.  According to the
                               * Commission Junction documentation, the page count starts out
                               * zero.  But in practice this does not appear to be the case.
                               * Setting 'page-number' to zero in the request returns no
                               * results.  So we default to one as the value for the page
                               * number.
                               */
                              'page-number' => '1',

                              /**
                               * Maximum number of results to return.  This value is set by
                               * calling set_maximum_product_count().  Comission Junction does
                               * not allow this value to be greater than 1,000.  If it is larger
                               * than that, then only 1,000 results will be returned.
                               */
                              'records-per-page' => '50',
                              );


    //// CONSTRUCTOR ///////////////////////////////////////////

    public function __construct($cj_key, $cj_web_id) {
        $this->cj_key    = $cj_key;
        $this->cj_web_id = $cj_web_id;
    }

    //// INTERFACE METHODS /////////////////////////////////////

    public function get_products_from_vendor($vendor, $options = array()) {

      return $this->get_products(array_merge(
                                             array('advertiser-ids' => $vendor),
                                             $options
                                             ));
    }

    /**
     * $options can include 'advertiser-ids', whose value should be a
     * string of advertiser IDs separated by commas.
     */
    public function get_products_by_keywords($keywords, $options = array()) {

      return $this->get_products(array_merge(
                                             array('keywords' => implode(',', $keywords)),
                                             $options
                                             ));
    }

    public function get_products($options = null) {
      return $this->extract_products(
                                     simplexml_load_string(
                                                           $this->query_for_products(
                                                                                     $this->make_request_url($options)
                                                                                     )
                                                           )
                                     );
    }

    public function set_maximum_product_count($count) {
      $this->defaults['records-per-page'] = $count;
    }

    public function set_results_page($page_number) {
      $this->defaults['page_number'] = $count;
    }


    //// PRIVATE METHODS ///////////////////////////////////////

    /**
     * Returns the URL we need to send an HTTP GET request to in order
     * to get product search results.  Accepts an array of keywords to
     * search for, and an optional array of advertiser IDs to use in
     * order to restrict the search.
     */
    private function make_request_url($options) {
      foreach ($this->defaults as $key=>$value) {
        $parameters[$key] = $options[$key] or
          $parameters[$key] = get_option('api_'.$key) or
          $parameters[$key] = $value or
          $parameters[$key] = null;
      }


      $parameters = array_merge(
                                $parameters,
                                array('website-id' => $this->cj_web_id)
                                );

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

    /**
     * Extracts all <product> nodes from search results and returns an
     * array of PanhandlerProduct objects representing the results. If
     * an error message is encountered this will instead return a new
     * PanhandlerError object containing the error message.
     */
    private function extract_products($xml) {
        $products = array();

        foreach ($xml->xpath("//product") as $product) {
            $products[] = $this->convert_product($product);
        }

        if ($error_message = $xml->xpath("//error-message")) {
          return new PanhandlerError((string)$error_message[0]);
        }

        return $products;
    }

}

?>