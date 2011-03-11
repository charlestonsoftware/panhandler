<?php

/**
 * This file implements the Panhandler interface for Amazon.
 */
 
if (function_exists('simplexml_load_string') === false) {
    throw new PanhandlerMissingRequirement("SimpleXML must be installed to use Amazon Panhandler");
}

final class AmazonDriver implements Panhandles {

    //// PRIVATE MEMBERS ///////////////////////////////////////

   
    /**
     * Options
     *
     * The private state variables, includes supported options and
     * other settings we may need to make this driver go.
     *
     * debugging              - The debugging output flag.
     * amazon_site            - Which country for the Amazon service URL?
     * secret_access_key      - Amazon provided key for the user
     * wait_for               - HTTP Request timeout
     *
     */
    private $options = array (
        'debugging'         => false,
        'http_hander'       => null,
        
        'amazon_site'            => '',
        'secret_access_key' => '',
        'wait_for'          => 30,        
        
         'AWSAcessKeyId'    => '',
         'AssociateTag'     => '',
         'Keywords'         => '',
         'Operation'        => '',
         'ResponseGroup'    => '',
         'SearchIndex'      => '',
         'Service'          => '',
         'Timestamp'                
        );

    /**
     * Supported Options
     *
     * Things you can push in via the calling application.
     *
     */
    private $supported_options = array(
        'amazon_site',
        'secret_access_key',
        'wait_for'
        );
    
    /**
     * Request Parameters
     *
     * List of options that are passed along to the Amazon API.
     *
     * This serves simply as a list of keys to lookup values in the
     * options named array when building our Amazon Request.
     *
     */
     private $request_params = array (
         'AWSAccessKeyId',
         'AssociateTag',
         'Keywords',
         'Operation',
         'ResponseGroup',
         'SearchIndex',
         'Service',
         'Timestamp',
         'Version'
         );

    


    //// CONSTRUCTOR ///////////////////////////////////////////

    /**-------------------------------------
     ** method: constructor
     **
     * We have to pass in the API Key, as we need
     * this to fetch product information.
     */
    public function __construct($options) {

        // Presets
        //
        $this->options['amazon_site'] = get_option(MPAMZ_PREFIX.'-amazon_site');
        
        // Set the properties of this object based on 
        // the named array we got in on the constructor
        //
        foreach ($options as $name => $value) {
            $this->options[$name] = $value;
        }

    }

    //// INTERFACE METHODS /////////////////////////////////////

    /**-------------------------------------
     ** method: get_supported_options
     **
     * Returns the supported options that get_products() accepts.
     */
    public function get_supported_options() {
        return $this->supported_options;
    }


    /**-------------------------------------
     ** method: set_default_option_values
     **
     **/
    public function set_default_option_values($default_options) {
        $this->parse_options($default_options);
    }


    /**-------------------------------------
     ** method: get_products
     **
     **
     ** Process flow:
     ** get_products <- extract_products <- get_reponse_xml
     **                                                  ^
     **                                                  |
     **                                          buildAmazonQuery
     **
     **/
    public function get_products($prod_options = null) {        
        if (! is_null($prod_options) && ($prod_options != '')) {
            foreach (array_keys($prod_options) as $name) {
                if (in_array($name, $this->supported_options) === false) {
                    throw new PanhandlerNotSupported("Received unsupported option $name");
                }
            }

            $this->parse_options($prod_options);
        }
        
        
        // Parameters We'll Accept...eventually
        //
        $this->options['SearchIndex']   = 'Books';
        $this->options['Keywords']      = 'WordPress';
        $this->options['AWSAccessKeyId']= '11BAEVC51K0CFFCQJE82';
        $this->options['AssociateTag']  = 'cybsprlab-20';
        
        // This will be static for get_products
        //
        $this->options['Operation']     = 'ItemSearch';
        $this->options['Service']       = 'AWSECommerceService';
        $this->options['ResponseGroup'] = 'Medium,Images,Variations';
        

        return $this->extract_products(
              $this->get_response_xml()
        );
    }


    /**-------------------------------------
     ** method: set_maximum_product_count
     **
     **/
    public function set_maximum_product_count($count) {
        $this->return = $count;
    }

    /**-------------------------------------
     ** method: set_results_page
     **
     **/
    public function set_results_page($page_number) {
        $this->results_page = $page_number;
    }

    //// PRIVATE METHODS ///////////////////////////////////////

    /**-------------------------------------
     * method: parse_options
     *
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
    private function parse_options($incoming_options) {
        foreach ($this->supported_options as $name) {
            if (isset($incoming_options[$name])) {
                $this->options[$name] = $incoming_options[$name];
            }
        }
    }

    /**-------------------------------------
     * method: buildAmazonQuery
     *
     * Takes an array of parameters to be sent to amazon and returns a
     * full url query with a signature attached.
     */
    function buildAmazonQuery() {
        
        // Make sure we actually have our necessary param
        //        
        if ($this->options['secret_access_key'] == '') return false;
        
        // Set a last minute timestamp
        //
        $this->options['Timestamp'] = date('c');                
        
        // Map pre-set driver options into the request parameter array
        //
        $request_parameters = array();
        foreach ($this->request_params as $option_key) {
            if (isset($this->options[$option_key])  && 
                $this->options[$option_key] != ''       ) {
                $request_parameters[$option_key] = $this->options[$option_key];
            }
        }
        
        // Amazon requires the keys to be sorted
        //
        ksort($request_parameters);
      
        // We'll be using this string to generate our signature
        $query       = http_build_query($request_parameters);
        $hash_string = "GET\n" . $this->options['amazon_site'] . "\n/onca/xml\n" . 
                        $query;
                       
        // Generate a sha256 HMAC using the private key
        $hash = base64_encode(
                            hash_hmac(
                                'sha256', 
                                $hash_string, 
                                $this->options['secret_access_key'], 
                                true
                                )
                            );
        
        // Put together the final query
        return 'http://' . $this->options['amazon_site'] . '/onca/xml?' .                 
                    $query . '&Signature=' . urlencode($hash);
    }


    /**-------------------------------------
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

        // Fetch the XML data
        //
        if (isset($this->options['http_handler'])) {
            $the_url =  $this->buildAmazonQuery();
            if ($this->options['debugging']) {
                print 'Requesting product list from:<br/>' .
                      '<a href="' . $the_url . '">'.$the_url.'</a><br/>';
            }
            $result = $this->options['http_handler']->request( 
                            $the_url, 
                            array('timeout' => $this->options['wait_for']) 
                            );            

            // We got a result with no errors, parse it out.
            //
            if ($this->http_result_is_ok($result)) {
                
                // 400 Error
                //
                if ($result['response']['code'] > 400) {
                    throw new PanhandlerError($result['body']);
                    if ($this->options['debugging'] == 'on') {
                        print $result['body']."<br/>\n";
                    }
                    
                    return '';
                }                              
                
                
                print "<pre>"; print_r($result); print "</pre>";
                
                // OK - Continue parsing
                //
                return simplexml_load_string($result['body']);

            // Catch some known problems and report on them.
            //
            } else {

                // WordPress Error from the HTTP handler
                //
                if (is_a($result,'WP_Error')) {

                    // Timeout, the wait_for setting is too low
                    // 
                    if ( preg_match('/Operation timed out/',$result->get_error_message()) ) {
                        throw new PanhandlerError(
                         'Did not get a response within '. $this->wait_for . ' seconds.<br/> '.
                         'Ask the webmaster to increase the "Wait For" setting in the admin panel.'
                         );
                    }
                }
            }
        
        // No HTTP Handler
        //
        } else {
            if ($this->options['debugging'] == 'on') {
                _e('No HTTP Handler available, cannot communicate with remote server.',
                    MPAMZ_PREFIX);
                print "<br/>\n";
            }
        }
        return false;
    }

    /**-------------------------------------
     * method: convert_item
     *
     * Takes a SimpleXML object representing an <item> node in search
     * results and returns a PanhandlerProduct object for that item.
     */
    private function convert_item($item) {
        $product                = new PanhandlerProduct();
        $product->name          = (string) $item['name'];
        $product->price         = (string) $item['sellPrice'];
        $product->image_urls    = array((string) $item['defaultProductUri']);
        $product->description   = (string) $item['description'];
        $product->web_urls      = array((string) $item['storeUri']);
        
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
        if ($this->options['debugging']) {
            print count($products) . ' products have been located.<br/>';
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

