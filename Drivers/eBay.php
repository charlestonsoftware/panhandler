<?php
/************************************************************************
*
* file: Drivers/eBay.php
*
* The eBay Driver. 
* 
************************************************************************/


/**
 * This file implements the Panhandler interface for eBay.
 */

if (function_exists('simplexml_load_string') === false) {
    throw new PanhandlerMissingRequirement("SimpleXML must be installed to use eBayPanhandler");
}
if (function_exists('curl_init') === false) {
    throw new PanhandlerMissingRequirement("cURL must be installed to use eBayPanhandler");
}

final class eBayDriver implements Panhandles {

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
     * Support options.
     */
    private $supported_options = array(
        'affiliate_info',
        'country_listed_in',
        'detailed_listings',
        'product_count',
        'keywords',
        'category_id',
        'min_price',
        'max_price',
        'money_prefix',
        'search_description',
        'sellers',
        'show_bin_price',
        'sort_order',
    );

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
     * An array of seller IDs whose products we want to display.  Each
     * ID in the array is a string.  This array can be empty if the
     * products do not need to come from any particular seller.
     */
    private $sellers = null;

    /**
     * An array of any keywords we may be using to narrow our product
     * search results.
     */
    private $keywords = null;

    /**
     * A hash of affiliate information.  Possible keys are:
     *
     *     1. 'custom_id'
     *     2. 'network_id'
     *     3. 'tracking_id'
     *
     * For details on their values see the documentation at
     *
     *     http://developer.ebay.com/DevZone/finding/CallRef/findItemsAdvanced.html#Request.affiliate
     *
     */
    private $affiliate_info = null;

    /**
     * A string representing any sorting to apply to the search.  For
     * details on the possible values, see the documentation at
     *
     *     http://developer.ebay.com/DevZone/finding/CallRef/types/SortOrderType.html
     *
     */
    private $sort_order = null;

    private $money_prefix = '$';        // Character to prefix money output
    private $show_bin_price = false;    // Show the BIN price next to the BIN flag

    //// CONSTRUCTOR ///////////////////////////////////////////

    /**
     * We have to pass in the AppID that eBay gives us, as we need
     * this to fetch product information.
     */
    public function __construct($options) {

        // Make sure we create all of these member variables so as to
        // prevent php warnings on reference
        foreach ($this->supported_options as $name) {
            $this->$name = null;
        }

        // Set overridable properties
         $this->detailed_listings = false;

        // Set the properties of this object based on
        // the named array we got in on the constructor
        //
        foreach ($options as $name => $value) {
            $this->$name = $value;
        }


        // Add filters and hooks
        //
        if ($this->pro_pack_enabled) {
            add_filter($this->prefix.'_money_prefix',array($this,'money_prefix_filter'));
        }
    }


    /**
     * Change the money prefix in wpCSL default to whatever the user picked.
     *
     * @param string $prefix
     * @return string
     */
    function money_prefix_filter($prefix='') {
        return $this->money_prefix;
    }


    //// INTERFACE METHODS /////////////////////////////////////

    /**
     * Returns the supported $options that get_products() accepts.
     */
    public function get_supported_options() {
        return $this->supported_options;
    }

    public function get_products($options = null) {
        if ($options) {
            foreach (array_keys($options) as $name) {
                if (in_array($name, $this->supported_options) === false) {
                    throw new PanhandlerNotSupported("Received unsupported option $name");
                }
            }
        }

        $this->parse_options($options);

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

    public function set_default_option_values($options) {
        $this->parse_options($options);
    }


    //// PRIVATE METHODS ///////////////////////////////////////

    /**
     * Called by the interface methods which take an $options hash.
     * This method sets the appropriate private members of the object
     * based on the contents of hash.  It looks for the keys in
     * $supported_options and assigns the value to the private members
     * with the same names.  See the documentation for each of those
     * members for a description of their acceptable values, which
     * this method does not try to enforce.
     *
     * Returns no value.
     */
    private function parse_options($options) {
        foreach ($this->supported_options as $name) {
            if (isset($options[$name])) {
                $this->$name = $options[$name];
            }
        }

        if (is_array($this->keywords) === false) {
            $this->keywords = preg_split('/[\s,]+/', $this->keywords);
        }
    }

    /**
     * Returns the URL that we need to make an HTTP GET request to in
     * order to get product information.
     */
    private function make_request_url() {

        if ($this->product_count) {
            self::set_maximum_product_count($this->product_count);
        }

        $options = array(
            'OPERATION-NAME'       => 'findItemsAdvanced',
            'SERVICE-VERSION'      => '1.0.0',
            'SECURITY-APPNAME'     => $this->app_id,
            'RESPONSE-DATA-FORMAT' => 'XML',
            'REST-PAYLOAD'         => null,
            'paginationInput.entriesPerPage' => $this->maximum_product_count,
            'paginationInput.pageNumber' => $this->results_page
        );

        if ($this->keywords) {
            $options['keywords'] = urlencode(implode(' ', $this->keywords));
        }

        if ($this->sort_order) {
            $options['sortOrder'] = $this->sort_order;
        }

        if ($this->category_id) {
            $options['categoryId'] = $this->category_id;
        }


        /*----------------------------
         * Pro Pack Options
         */
        if ($this->pro_pack_enabled) {
            if (isset($this->search_description)){
                $options['descriptionSearch'] = $this->search_description;
            }
        }


        $options = $this->apply_filters($options);
        $options = $this->apply_affiliate_info($options);

        return sprintf(
            "%s?%s",
            $this->ebay_service_url,
            http_build_query($options)
        );
    }

    /**
     * Takes a hash of options used to build the request URI and adds
     * any affiliate information that may be present.
     */
    private function apply_affiliate_info($options) {
        if ($this->affiliate_info) {
            $key_to_ebay_option = array(
                'custom_id'   => 'affiliate.customId',
                'network_id'  => 'affiliate.networkId',
                'tracking_id' => 'affiliate.trackingId'
            );

            foreach ($key_to_ebay_option as $key => $ebay_option) {
                $options[$ebay_option] = $this->affiliate_info[$key];
            }
        }

        return $options;
    }


    /**
     * Takes a hash of options used to build the request URL and adds
     * any applicable item filters.  In this context, 'item filters'
     * means the parameters that eBay looks for in the form of
     * 'itemFilter(x)'.
     */
    private function apply_filters($options) {
        $filterCount = 0;
        if ($this->sellers) {
            $options[sprintf('itemFilter(%d).name',$filterCount)] = 'Seller';
            $options[sprintf('itemFilter(%d).value(0)',$filterCount)] = $this->sellers;
            $filterCount++;
        }

        /*----------------------------
         * Pro Pack Options
         */
        if ($this->pro_pack_enabled) {
            if (isset($this->min_price) && ($this->min_price > 0)) {
                $options[sprintf('itemFilter(%d).name',$filterCount)] = 'MinPrice';
                $options[sprintf('itemFilter(%d).value(0)',$filterCount)] = $this->min_price;
                $filterCount++;
            }
            if (isset($this->max_price) && ($this->max_price > 0)) {
                $options[sprintf('itemFilter(%d).name',$filterCount)] = 'MaxPrice';
                $options[sprintf('itemFilter(%d).value(0)',$filterCount)] = $this->max_price;
                $filterCount++;
            }
            if (isset($this->country_listed_in) && ($this->country_listed_in)) {
                $options[sprintf('itemFilter(%d).name',$filterCount)] = 'ListedIn';
                $options[sprintf('itemFilter(%d).value(0)',$filterCount)] = $this->country_listed_in;
                $filterCount++;
            }
        }

        return $options;
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
     * Returns a SimpleXML object representing the search results.
     */
    private function get_response_xml() {
        return simplexml_load_string(
            $this->http_get(
                $this->make_request_url()
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
        $product->description = $this->create_description($item);
        return $product;
    }

    /**
     * Takes information out of an <item> node and returns a string
     * representing the description of the product, since we don't get
     * a full one back from eBay.
     */
    private function create_description($item) {
        $theDesc = '<div class="csl_themes-prod_desc">';

        // Standard Description
        //
        $binYesStr = 'Yes' .
                      (($this->pro_pack_enabled && $this->show_bin_price) ?
                        ' '. $this->money_prefix . (string) $item->listingInfo->buyItNowPrice :
                        ''
                      )
                ;
        $theDesc .= $this->FormatListEntry('Buy It Now',
                            ((string) $item->listingInfo->buyItNowAvailable === 'true') ?
                                $binYesStr :
                                'No'
                            );
        $theDesc .= $this->FormatListEntry('Number of Bids',(string) ($item->listingInfo->bidCount > 0) ? $item->listingInfo->bidCount : '0');

        /*----------------------------
         * Pro Pack Options
         */
        if ($this->pro_pack_enabled  && isset($this->detailed_listings) && $this->detailed_listings) {
            $theDesc .= $this->FormatListEntry('Item ID',(string) $item->itemId);
            $theDesc .= $this->FormatListEntry('Returns Allowed: ',((string) $item->listingInfo->returnsAccepted === 'true') ? 'Yes' : 'No');
        }

        $theDesc .= '</div><div class="csl_themes-row"></div>';

        //$theDesc .= '<pre>' . print_r($item,true) . '</pre>';

        // $theDesc .= '<pre>' . print_r($item,true) . '</pre>';

        return $theDesc;
    }

    /**
    * method: ForamtListEntry
    * takes a label and value and returns the formatted div.
    *
    */
    private function FormatListEntry($label,$value) {
        return sprintf(
                '<div class="csl_themes-prod_desc_entry">' .
                    '<div class="csl_themes-prod_desc_label">' . __($label,$this->prefix) . ':</div>' .
                    '<div class="csl_themes-prod_desc_value">%s</div>'.
                '</div>',
                (string) $value
                );
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

        foreach ($xml->searchResult->item as $item) {
            $products[] = $this->convert_item($item);
        }

        return $products;
    }

    /**
     * Takes a SimpleXML object representing a response from eBay and
     * returns a boolean indicating whether or not the response was
     * successful.
     */
    private function is_valid_xml_response($xml) {
        return (
            $xml && (string) $xml->ack === 'Success'
        );
    }
}

?>