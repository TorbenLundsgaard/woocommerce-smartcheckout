<?php

class SmartCheckoutRates extends WC_Shipping_Method {

    public function __construct($instance_id = 0)
    {
        parent::__construct( $instance_id );

        $this->id                 = 'smartcheckout_shipping'; // Id for your shipping method. Should be uunique.
        $this->method_title       = __( 'HomeRunner SmartCheckout Rates' );  // Title shown in admin
        $this->method_description = __( 'Get alle your shipping rates with HomeRunner SmartCheckout' ); // Description shown in admin

        $this->title              = "HomeRunner SmartCheckout"; // This can be added as an setting but for this example its forced.

        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    function init() {
        // Load the settings API
        $this->init_form_fields(); // This is part of the settings API.
        $this->init_settings(); // This is part of the settings API. Loads settings you previously init.

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Init form fields.
     */
    public function init_form_fields()
    {
        $this->instance_form_fields = [
            'title' => [
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => '<a href="https://account.homerunner.com/" target="_blank">' . __('Opret leveringsprodukter', 'csc_textdomain') . '</a>',
                'default' => 'HomeRunner SmartCheckout',
                'desc_tip' => false,
            ],
        ];
    }

    public function calculate_shipping( $package = [] ) {
        global $woocommerce;

        // Get cart data
        $cart_data = $woocommerce->cart->get_cart();

        // Make sure date is correct by setting timezone
        $old_tz = date_default_timezone_get();

        date_default_timezone_set('Europe/Copenhagen');
        $cart_date = strtotime(date('d-m-Y'));
        $cart_time = date('H:i:s');
        $cart_day = date('l');
        date_default_timezone_set($old_tz);

        // Define cart_items and total cart_weight
        $cart_items = array();
        $cart_weight = 0;
        $cart_subtotal = 0;
        $categories = [];

        foreach ($cart_data as $cart_product) {
            $_product =  wc_get_product( $cart_product['data']->get_id());
            $terms = get_the_terms( $_product->get_id(), 'product_cat' );

            if ($_product->get_type() == 'variation') {
                $product_id = wc_get_product( $_product->get_parent_id() )->get_id();
            } else {
                $product_id = $_product->get_id();
            }

            $terms = get_the_terms( $product_id, 'product_cat' );

            if ($terms) {
                foreach ($terms as $term) {
                    $categories[] = $term->term_id;
                }
            }
            
            $cart_items[] = array(
                'item_name' => $_product->get_name(),
                'item_sku' => $_product->get_sku(),
                'item_id' => $_product->get_id(),
                'item_qty' => $cart_product['quantity'],
                'item_price' => $cart_product['line_subtotal'],
                'item_weight' => $_product->get_weight()
            );

            $cart_items = apply_filters( 'homerunner_cart_items', $cart_items, $cart_product, $_product, $terms );

            $cart_weight += (float) $_product->get_weight()*((int) $cart_product['quantity']);
            $cart_subtotal += $cart_product['line_subtotal'];
        }

        // Init shipment data
        $shipment_data = array(
            'receiver_name' => WC()->checkout->get_value('shipping_first_name') . ' ' . WC()->checkout->get_value('shipping_last_name'),
            'receiver_address1' => $package['destination']['address_1'],
            'receiver_address2' => $package['destination']['address_2'],
            'receiver_country' => $package['destination']['country'],
            'receiver_city' => $package['destination']['city'],
            'receiver_zip_code' => $package['destination']['postcode'],
            'receiver_phone' => WC()->checkout->get_value('billing_phone'),
            'receiver_email' => WC()->checkout->get_value('billing_email'),
            'receiver_company' => (WC()->checkout->get_value('shipping_company') != '') ? WC()->checkout->get_value('shipping_company') : '',
            'cart_amount' => $woocommerce->cart->cart_contents_count,
            'cart_weight' => $cart_weight,
            'cart_date' => $cart_date,
            'cart_time' => $cart_time,
            'cart_day' => $cart_day,
            'cart_currency' => get_woocommerce_currency_symbol(),
            'cart_subtotal' => $woocommerce->cart->subtotal,
            'cart_items' => $cart_items,
            'categories' => implode(',', $categories),
        );

        // Check if customer data is set
        if(isset($_POST['post_data'])) {
            // $posted_data is return as url params therefore explode
            $checkout_data = array();
            $vars = explode('&', $_POST['post_data']);
            foreach ($vars as $k => $value){
                $v = explode('=', urldecode($value));
                $checkout_data[$v[0]] = $v[1];
            }

            $shipment_data['receiver_name'] = $checkout_data['billing_first_name'] . ' ' . $checkout_data['billing_last_name'];
            $shipment_data['receiver_company'] = $checkout_data['shipping_company'];
            $shipment_data['receiver_email'] = $checkout_data['billing_email'];
            $shipment_data['receiver_phone'] = $checkout_data['billing_phone'];
        }

        // Check for free shipping coupon
        $free_shipping = false;

        if(count($package['applied_coupons']) > 0) {
            $coupons = $package['applied_coupons'];
            foreach ($coupons as $coupon) {
                $coupon = new WC_Coupon($coupon);

                if($coupon->get_free_shipping()) {
                    $free_shipping = true;
                }
            }
        }

        $shipment_data = apply_filters( 'homerunner_shipment_data', $shipment_data );

        if (wp_get_environment_type() != 'production' ) {
            error_log(print_r($shipment_data, 1));
        }

        $products = $this->validata_data($shipment_data);

        if (apply_filters('homerunner_order_by_priority', false)) {
            usort($products, function ($a, $b) {
                return ($a->conditions[0]->priority > $b->conditions[0]->priority) ? 1 : -1;
            });
        }

        foreach ($products as $product) {
            $rate = array(
                'id' => $product->carrier.'_'.$product->carrier_product.'_'.$product->carrier_service,
                'label' => $product->title,
                'cost' => ($free_shipping) ? 0 : $product->conditions[0]->price,
                'calc_tax' => 'per_order',
                'meta_data' => [
                    'carrier' => $product->carrier,
                    'product' => $product->carrier_product,
                    'service' => $product->carrier_service
                ]
            );

            // Register the rate
            $this->add_rate($rate);
        }

    }

    private function validata_data($shipment_data) {
        $smartcheckout = new \SmartCheckoutSDK\Validate();
        $products = json_decode($smartcheckout->handle_ecommerce($shipment_data, get_option('csc_shop_token')));

        return $products;
    }
}
