<?php

/**
 * This file implements the Panhandler interface for CafePress.
 */

if (function_exists('simplexml_load_string') === false) {
    throw new PanhandlerMissingRequirement("SimpleXML must be installed to use CafePressPanhandler");
}

final class CafePressDriver implements Panhandles {

    //// PRIVATE MEMBERS ///////////////////////////////////////

    /**
     * URL for invoking CafePress' services.
     */
    private $cafepress_service_url = 'http://open-api.cafepress.com/product.listByStoreSection.cp';
    private $cafepress_api_version = '3';

    /**
     * The APIKey given to us by CafePress.
     */
    private $api_key;

    /**
     * Support options.
     */
    private $supported_options = array(
        'api_key',
        'http_handler',
        'storeid',
        'sectionid',
        'wait_for'
    );

    /**
     * The store ID we want to show.
     */
    private $storeid = 'cybersprocket';

    /**
     * The section ID we want to show.
     */
    private $sectionid = 0;

    /**
     * The number of products that we return.  The value can be
     * changed by set_maximum_product_count().
     */
    private $maximum_product_count = 10;

    /**
     * The page of results we want to show.
     */
    private $results_page = 1;

    /**
     * A hash of affiliate information.
     */
    private $affiliate_info = null;

    // How long to wait before we time out a request to CafePress
    //
    private $wait_for = 30;


    //// CONSTRUCTOR ///////////////////////////////////////////

    /**
     * We have to pass in the API Key that CafePress gives us, as we need
     * this to fetch product information.
     */
    public function __construct($options) {

        // Set the properties of this object based on 
        // the named array we got in on the constructor
        //
        foreach ($options as $name => $value) {
            $this->$name = $value;
        }
    }

    //// INTERFACE METHODS /////////////////////////////////////

    /**
     * Returns the supported $options that get_products() accepts.
     */
    public function get_supported_options() {
        return $this->supported_options;
    }


    public function set_default_option_values($options) {
        $this->parse_options($options);
    }


    /**
     * Fetch products from CafePress.
     */
    public function get_products($options = null) {
        if (! is_null($options) && ($options != '')) {
            foreach (array_keys($options) as $name) {
                if (in_array($name, $this->supported_options) === false) {
                    throw new PanhandlerNotSupported("Received unsupported option $name");
                }
            }

            $this->parse_options($options);
        }

        return $this->extract_products(
              $this->get_response_xml()
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
     * Called by the interface methods which take an $options hash.
     * This method sets the appropriate private members of the object
     * based on the contents of hash.  It looks for the keys in
     * $supported_options * and assigns the value to the private
     * members with the same names.  See the documentation for each of
     * those members for a description of their acceptable values,
     * which this method does not try to enforce.
     *
     * Returns no value.
     */
    private function parse_options($options) {
        foreach ($this->supported_options as $name) {
            if (isset($options[$name])) {
                $this->$name = $options[$name];
            }
        }
    }

    /**
     * Returns the URL that we need to make an HTTP GET request to in
     * order to get product information.
     * http://open-api.cafepress.com/product.listByStoreSection.cp?
     * appKey=$x&storeId=$y&sectionId=$z&v=$v
     */
    private function make_request_url() {
        $options = array(
            'v'             => $this->cafepress_api_version,
            'appKey'        => $this->api_key,
            'storeId'       => $this->storeid,
            'sectionId'     => $this->sectionid,
        );

        return sprintf(
            "%s?%s",
            $this->cafepress_service_url,
            http_build_query($options)
        );
    }


    /**
     * method (private): get_response_xml
     *
     * Parameters:
     * None - builds url string from object properties previous set.
     *
     * Return Values:
     * OK  : SimpleXML object representing the search results.
     * NOK :Boolean false 
     *      consistent with the return value of simplexml_load_string on fail.
     *
     */
    private function get_response_xml() {

        // Fetch The organization info from the AMRS API
        //
        if (isset($this->http_handler)) {
            $the_url =  $this->make_request_url();
            $result = $this->http_handler->request( 
                            $the_url, 
                            array('timeout' => $this->wait_for) 
                            );            

            if ($this->http_result_is_ok($result)) {
                return simplexml_load_string($result['body']);
            }
        }
        return false;
    }

    /**
     * Takes a SimpleXML object representing an <item> node in search
     * results and returns a PanhandlerProduct object for that item.
     */
    private function convert_item($item) {
        $product            = new PanhandlerProduct();
        $product->name       = (string) $item['name'];
        $product->price      = (string) $item['sellPrice'];
        $product->web_urls   = array((string) $item['storeUri']);
        $product->image_urls = array((string) $item['defaultProductUri']);
        $product->description = (string) $item['description'];
        return $product;
    }

    /**
     * Takes a SimpleXML object representing all keyword search
     * results and returns an array of PanhandlerProduct objects
     * representing every item in the results.
     */
    private function extract_products($xml) {
        $products = array();

        if ($this->is_valid_xml_response($xml) === false) {
            return array();
        }

        foreach ($xml->product as $item) {
            $products[] = $this->convert_item($item);
        }

        return $products;
    }

    /**
     * method: http_result_is_ok()
     *
     * Determine if the http_request result that came back is valid.
     *
     * params:
     *  $result (required, object) - the http result
     *
     * returns:
     *   (boolean) - true if we got a result, false if we got an error
     */
    private function http_result_is_ok($result) {

        // Yes - we can make a very long single logic check
        // on the return, but it gets messy as we extend the
        // test cases. This is marginally less efficient but
        // easy to read and extend.
        //
        if ( is_a($result,'WP_Error') ) { return false; }
        if ( !isset($result['body'])  ) { return false; }
        if ( $result['body'] == ''    ) { return false; }
        if ( isset($result['headers']['x-mashery-error-code']) ) { return false; }

        return true;
    }


    /**
     * Takes a SimpleXML object representing a response from CafePress and
     * returns a boolean indicating whether or not the response was
     * successful.
     *
     * From the old code, unfortunately error codes are note well defined in the API
     *
     *     (preg_match('/<help>\s+<exception-message>(.*?)<\/exception-message>/',$xml,$error) > 0) ||
     */
    private function is_valid_xml_response($xml) {
        return (
            $xml && (string) $xml->help === ''
          );
    }
}

?>
