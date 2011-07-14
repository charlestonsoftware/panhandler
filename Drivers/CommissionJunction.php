<?php

/**
 * This file implements the Panhandler interface for Commission Junction.
 */

if (function_exists('curl_init') === false) {
  throw new PanhandlerMissingRequirement('cURL must be installed to use the Commission Junction driver.');
}
if (function_exists('simplexml_load_string') === false) {
  throw new PanhandlerMissingRequirement('SimpleXML must be installed to use the Commission Junction driver.');
}

final class CommissionJunctionDriver implements Panhandles {

    //// PRIVATE MEMBERS ///////////////////////////////////////

    /**
     * Private Driver Properties
     *     
     * cj_search_url    - The URL for Commission Junction's API.
     * api_key           - Our authorization key for Commission Junction.
     * cj_webid        - Our web ID for Commission Junction.
     * external_defaults- These are the defaults as defined by the Wordpress user.
     * defaults         - This holds all of our default values, as well as a list of the
     *                    parameters that the CJ api can accept.
     */
    private $cj_search_url = 'https://product-search.api.cj.com/v2/product-search';
    private $api_key;
    private $cj_webid;
    private $external_defaults;
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

    /**
     * These are the 2 variables we used to get.
     *  $this->cj_key    = $cj_key;     // NOW api_key
     *  $this->cj_web_id = $cj_web_id;  // NOW cj_webid
     *
     */
    public function __construct($options) {

        // Set the properties of this object based on 
        // the named array we got in on the constructor
        //
        foreach ($options as $name => $value) {
            $this->$name = $value;
        }
    }    
    
    // When wordpress processes shortcode attributes it will produce
    // erroneous results if any of the attribute names contain
    // dashes. However, the params that get sent to CJ are very specific
    // and contain dashes, so this means that these attributes need to be
    // written with underscores in the shortcodes and then converted to
    // dashes for CJ.
    function process_atts($atts) {
      $return_atts = array();
      if (is_array($atts)) {
          foreach ($atts as $key=>$value) {
            $return_atts[str_replace('_', '-', $key)] = $value;
          }
      }
      if ($this->debugging) {
          print __('DEBUG: shortcode attributes',$this->prefix) . "<br/>\n";
          print_r($atts);
      }
      return $return_atts;
    }

    function process_atts_reverse($atts) {
      foreach ($atts as $key=>$value) {
        $return_atts[str_replace('-', '_', $key)] = $value;
      }
      return $return_atts;
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

    public function get_supported_options() {
       return array_merge(
               array_keys($this->defaults),
               array_keys($this->process_atts_reverse($this->defaults))
               );
    }

    public function get_products($options = null) {
      return $this->extract_products(
                 simplexml_load_string(
                   $this->query_for_products(
                     $this->make_request_url($this->process_atts($options))
                     )
                   )
                 );
    }

    public function set_default_option_values($options) {
      $this->external_defaults = $options;
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
          $parameters[$key] = (isset($options[$key]) ? $options[$key] : false) or
          $parameters[$key] = (isset($this->external_defaults[$key]) ? $this->external_defaults[$key] : false) or
          $parameters[$key] = $value or
          $parameters[$key] = null;
      }


      $parameters = array_merge(
                                $parameters,
                                array('website-id' => $this->cj_webid)
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
                    array('Authorization: ' . $this->api_key));

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
        $product->currency    = (string) $node->currency;

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

