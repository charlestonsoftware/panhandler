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
         'AssociateTag'     => 'charlesof-20',
         'keywords'         => 'WordPress',
         'Operation'        => '',
         'ResponseGroup'    => '',
         'SearchIndex'      => 'Books',
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
        'wait_for',
        'searchindex',
        'keywords',
        'associatetag'        
        );
    
    /**
     * Request Parameters
     *
     * List of options that are passed along to the Amazon API.
     *
     * This serves simply as a list of keys to lookup values in the
     * options named array when building our Amazon Request.
     *
     * The mapping is mixed case because the WPCSL-generic lib always
     * sends inline shortcode attributed in lower case, so we need to
     * pick those up and map them to the mixed case Amazon expects.
     *
     */
     private $request_params = array (
         'AWSAccessKeyId'   => 'AWSAccessKeyId',
         'AssociateTag'     => 'associatetag',
         'Keywords'         => 'keywords',
         'Operation'        => 'Operation',
         'ResponseGroup'    => 'ResponseGroup',
         'SearchIndex'      => 'searchindex',
         'Service'          => 'Service',
         'Timestamp'        => 'Timestamp',
         'Version'          => 'Version'
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
        $this->options['amazon_site'] = get_option(MP_AMZ_PREFIX.'-amazon_site');
        
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
        
        // This will be static for get_products
        //
        $this->options['Operation']     = 'ItemSearch';
        $this->options['Service']       = 'AWSECommerceService';
        $this->options['ResponseGroup'] = 'Medium,Images,Variations,EditorialReview';
        

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
        foreach ($this->request_params as $amazon_key => $option_key) {
            if (isset($this->options[$option_key])  && 
                $this->options[$option_key] != ''       ) {
                $request_parameters[$amazon_key] = $this->options[$option_key];
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
                    if ($this->options['debugging']) {
                        print "<pre>Error Response:\n".print_r($result,true).'</pre>';;
                    }
                    
                    return '';
                }

                // Normal response debug
                //
                if ($this->options['debugging']) {
                    $xmlDoc = DOMDocument::loadXML($result['body']);
                    $xmlDoc->formatOutput = true;
                    print '<pre>'.htmlentities($xmlDoc->saveXML()).'</pre>';
                }
                                
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
            if ($this->options['debugging']) {
                _e('No HTTP Handler available, cannot communicate with remote server.',
                    MP_AMZ_PREFIX);
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
        
        // Name
        //
        $product->name          = (string) $item->ItemAttributes->Title;
        
        // Price
        //        
        // This is the default dataset for pricing, but it doesn't
        // always exist
        if (isset($item->Offers->Offer->OfferListing->Price->FormattedPrice)) {
            $product->price = $this->clean_price($item->Offers->Offer->OfferListing->Price->FormattedPrice);
            
        // Alternatively, we're going to get our pricing info from
        // the LowestNewPrice offer
        } elseif (isset($item->OfferSummary->LowestNewPrice->FormattedPrice)) {
            $product->price = $this->clean_price($item->OfferSummary->LowestNewPrice->FormattedPrice);
            
        // Not Available?            
        } else {
            $product->price = 'This item is currently unavailable';
        }
        
        // List Price
        if (isset($item->ItemAttributes->ListPrice->FormattedPrice)) {
            $product->listprice = $this->clean_price($item->ItemAttributes->ListPrice->FormattedPrice);
        }        
                
        
        // Image Sets (Small, Medium, Large)
        //
        $product->image_set = $this->formatImages($item);

        // Default Image URLs
        if (isset($product->image_set['default'])) {
                $product->image_urls[]    = $product->image_set['default']['Medium'];
        }                
        
        // Description
        //
        $product->description   = $this->formatDescription($item);

        // Web URL
        //
        $product->web_urls      = array((string) $item->DetailPageURL);

        return $product;
    }

    /*---------------------------------------------------------
     * method: extract_products()
     * 
     * Takes a SimpleXML object representing all keyword search
     * results and returns an array of PanhandlerProduct objects
     * representing every item in the results.
     */
    private function extract_products($xml) {
        $products = array();

        if ($this->is_valid_xml_response($xml) === false) {
            return array();
        }

        // Valid Results Returned
        //
        if ((string)$xml->Items->Request->IsValid === 'True') {
            foreach ($xml->Items->Item as $item) {
                $products[] = $this->convert_item($item);
            }
            
            if ($this->options['debugging']) {
                print count($products) . ' products have been located.<br/>';
            }
    
            return $products;
            
        // Invalid Result
        //
        } else {
            if ($this->options['debugging']) {
                print 'Amazon reports the item request is invalid.<br/>';
            }
        }
        
        
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
    
    
    /*------------------------------------
     * method: clean_price
     * 
     * clean up the price string with the pre-formatted dollar sign
     */
     private function clean_price($price_obj) {
         $newprice = (int) preg_replace('/\D/','',$price_obj[0]);
         return (string) ($newprice/100);
     }


    /*-------------------------------------
     * method: formatDescription
     * Create an Amazon Description
     */
    function formatDescription($item) {
        $description = '';

        // Add Reviews
        //
        $reviews = $this->formatReviews($item);        
        foreach ($reviews as $review) {
            $description .= $review;
        }

        // Add Details
        //        
        $details = $this->formatDetails($item);
        foreach ($details as $detail => $value) {
            $description .= "$detail: $value<br/>";
        }
        
        
        return $description;
    }
    
    /**
     * Creates a simple array of formatted values to be displayed in
     * the Product Details section. These values are culled from
     * various places and use numerous differing formats.
     */
    function formatReviews($item) {      
        $reviews = array();
        
        if (isset($item->EditorialReviews)) {
            foreach ($item->EditorialReviews as $review) {
                $reviews[] = "<div class='review'>".
                    "<div class='review_source'>".
                        $review->EditorialReview->Source .
                    '</div>' .
                    "<div class='review_details'>" .
                        $review->EditorialReview->Content .                    
                    '</div>'.
                    '</div>';
            }
        }

        // Filter out values that are empty or null
        $reviews = array_filter($reviews);

        return $reviews;
    }    
    
    
    /**
     * Creates a simple array of formatted values to be displayed in
     * the Product Details section. These values are culled from
     * various places and use numerous differing formats.
     */
    function formatDetails($item) {
        if (isset($item->ItemAttributes->ItemDimensions->Height)) {
            $dimensions[] =
                ($item->ItemAttributes->ItemDimensions->Height / 100) . ' x ' .
                ($item->ItemAttributes->ItemDimensions->Width / 100) . ' x ' .
                ($item->ItemAttributes->ItemDimensions->Length / 100) . ' inches';
        }

        if ($this->convertWeight($item->ItemAttributes->ItemDimensions->Weight))
            $dimensions[] = (string) $this->convertWeight($item->ItemAttributes->ItemDimensions->Weight);

        if (isset($dimensions)) {
            $details['item_dimensions'] = implode('; ',array_filter($dimensions));
        }

        $details['shipping_weight'] = $this->convertWeight($item->ItemAttributes->PackageDimensions->Weight);

        $details['ASIN'] = (string) $item->ASIN;

        // Filter out values that are empty or null
        $details = array_filter($details);

        return $details;
    }    
     
     
    /**
     * Attempts to find pertinent image sets for an item and assigns a
     * default set to use.
     */
    function formatImages($item) {
        $imageSet = array();


        // This is where the standard images will be coming from
        if (isset($item->SmallImage)) {
            $this->BuildImageArray($item, null, false, $imageSet);
        }

        // This will load in various image 'sets'
        if (isset($item->ImageSets->ImageSet)) {
            $sets = (array)$item->ImageSets;
            $this->BuildImageArray($sets['ImageSet'], 'sets', true, $imageSet);
        }

        // Items with variations are set up a little differently so we
        // have to go through them all.
        if (isset($item->Variations)) {
            foreach ($item->Variations->Item as $variation_item) {
                $this->BuildImageArray($variation_item, null, false, $imageSet);
            }
        }

        if (!isset($imageSet['default'])) {
            $imageSet['default'] = false;
        }
        
        return $imageSet;
    }
    
    	/**    
    	 * BuildImageArray()
         *
         * This is used to pull image URLs into an array and will also
         * set a default if one has not already been set. Images will
         * be put into a named array using an item's ASIN as an index
         * by default unless one is provided.
         *
         * $new is the resource containing all of the images.
         * $set_index is the optional array index that the extracted
         * images will be placed in.
         * $keey_array is used to determine whether or not to keep a
         * single set within an array. By default, if there is only a
         * single set it will be collapsed instead of using index[0].
         *
         **/
        function BuildImageArray($new, $set_index = null, $keep_array = false, &$imageSet)  {
            
            // If no params are provided, we bail
            if (!isset($new)) return false;
            $set_index = $set_index or $set_index = (string)$new->ASIN;

            // We're going to treat everything as if it were an array
            // from this point on, so if it's not one already, we need
            // to create one.
            if (!is_array($new)) {
                $new_array[] = $new;
            } else $new_array = $new;

            // Run through each 'item' and add it to the new set
            $index = 0;
            $newSet = array();
            foreach ($new_array as $set) {
                if (isset($set->SmallImage)) {
                    $newSet[$index]['Small'] = (string) $set->SmallImage->URL;
                    $newSet[$index]['Medium'] = (string) $set->MediumImage->URL;
                    $newSet[$index]['Large'] = (string) $set->LargeImage->URL;
                    $index++;
                }
            }

            // Now we take the new set and append it to the proper
            // index and set a default if one hasn't already been
            // selected.
            if (count($newSet) > 1 || $keep_array) {
                $imageSet[$set_index] = $newSet;
                $imageSet['default'] = $imageSet[$set_index][0];
            } else if (count($newSet) > 0) {
                $imageSet[$set_index] = $newSet[0];
                $imageSet['default'] = $imageSet[$set_index];
            }
        }         

    /**
     * Amazon stores weight (for the US anyway) in hundreths of a
     * pound. Here we convert this into either pounds or ounces if the
     * value is less than a pound. Nothing is returned if the value is
     * less than zero.
     */
    function convertWeight($value) {
        if ($value > 0) {
            if ($value / 100 < 1) {
                $weight = (($value / 100) * 16) . ' ounces';
            } else {
                $weight = ($value /100) . ' pounds';
            }
            return $weight;
        }
    }
        
        
}


