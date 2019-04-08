<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

/*
Plugin Name: افزونه حمل و نقل پست رسان
Plugin URI: http://postresan.com
Author: Ramin Bazghandi
Version: 1.0.2
 */
if(in_array('woocommerce/woocommerce.php',apply_filters('active_plugins',get_option('active_plugins')))) {
    $pw_options = get_option('PW_Options');
    /*
    if(isset($pw_options['enable_iran_cities'])) {
        $pw_options['enable_iran_cities'] = 'no';
        update_option('PW_Options',$pw_options);
    }
    */
    if ( function_exists( 'PW' ) && PW()->get_options( 'enable_iran_cities' ) != 'no' ) {
        $settings                       = PW()->get_options();
        $settings['enable_iran_cities'] = 'no';
        update_option( 'PW_Options', $settings );
    }
    require_once 'helper/helper.php';

    function postresan_shipping_method_init()
    {
        if (!class_exists('WC_Postresan_Shipping_Method')) {
            class WC_Postresan_Shipping_Method extends WC_Shipping_Method
            {

                public function __construct()
                {
                    $this->id = 'postresan_shipping';
                    $this->title = __('پست رسان');
                    $this->method_title = __('پست رسان');
                    $this->method_description = __('ارسال توسط پست رسان');
                    $this->init();

                }

                public function init()
                {
                    $this->init_form_fields();
                    $this->init_settings();
                    $this->enabled		= $this->get_option('enabled');
                    add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
                }

                public function init_form_fields()
                {
                    $this->form_fields = array(
                        'enabled' => array(
                            'title' 		=> __( 'Enable/Disable', 'woocommerce' ),
                            'type' 			=> 'checkbox',
                            'label' 		=> __( 'Enable this shipping method', 'woocommerce' ),
                            'default' 		=> 'no',
                        ),
                        'user' => array(
                            'title' => __('نام کاربری', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('postresan username', 'woocommerce'),
                            'default' => __('نام کاربری'),
                            'desc_tip' => true
                        ),
                        'password' => array(
                            'title' => __('رمز عبور', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('postresan password', 'woocommerce'),
                            'default' => __('رمز عبور'),
                            'desc_tip' => true
                        ),
                        'pishtaz_enable' => array(
                            'title'         => 'روش های ارسال',
                            'label'         => __('پست پیشتاز','woocommerce'),
                            'type'          => 'checkbox',
                            'default'       => 'yes',
                        ),
                        'sefareshi_enable' => array(
                            'label'         => __('پست سفارشی','woocommerce'),
                            'type'          => 'checkbox',
                            'default'       => 'yes',
                        ),
                        'online_enable' => array(
                            'title'         => 'روش های پرداخت',
                            'label'         => __('پرداخت نقدی','woocommerce'),
                            'type'          => 'checkbox',
                            'default'       => 'yes',
                        ),
                        'cod_enable' => array(
                            'label'         => __('پرداخت در محل','woocommerce'),
                            'type'          => 'checkbox',
                            'default'       => 'yes',
                        ),
                        'fixed_pishtaz' => array(
                            'label' => __('هزینه ثابت پیشتاز','woocommerce'),
                            'type' => 'text',
                            'description' => __('هزینه ثابت پست پیشتاز به شهرستان ها','woocommerce'),
                            'default' => __('هزینه ثابت پست پیشتاز'),
                            'desc_tip' => true
                        ),
                        'fixed_sefareshi' => array(
                            'label' => __('هزینه ثابت سفارشی ','woocommerce'),
                            'type' => 'text',
                            'description' => __('هزینه ثابت ارسال سفارشی به شهرستان ها','woocommerce'),
                            'default' => __('هزینه ثابت ارسال سفارشی'),
                            'desc_tip' => true

                        ),'city_style' => array(
                            'title' 		=> 'نحوه نمایش شهر',
                            'type' 			=> 'checkbox',
                            'label' 		=> 'نمایش استان و شهر در یک سطر',
                            'default' 		=> 'no',
                        ),
                    );

                    global $username;
                    global $password;
                    global $city_style;
                    $username = $this->get_option('user');
                    $password = $this->get_option('password');
                    $city_style = $this->get_option('city_style');
                }
                /**
                 * calculate shipping
                 *
                 * @param $package
                 *
                 * @return bool
                 * @throws Exception
                 */
                #محاسبه هزینه پست برای حالت تحویل در منزل یا آنلاین
                public function calculate_shipping ($package = array())
                {

                    $options = get_option('woocommerce_postresan_shipping_settings');

                    if ($options['enabled'] == 'no')
                        return false;

                    /**
                     * @var WooCommerce $woocommerce
                     */
                    global $woocommerce;
                    # جمع کل سبد خرید
                    $total_price = convertToRial(floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->get_cart_contents_total())));
                    $total_weight = 0;
                    $emptyBasket = true;
                    $total_packing = 0;
                    foreach ($package['contents'] as $product) {
                        # محصولات مجازی را نمی توان در فروتل ثبت کرد
                        if ($product['data']->virtual != 'no')
                            continue;
                        $emptyBasket = false;
                        $packing = $product['quantity']*intval(get_post_meta($product['data']->id,'packing',true));
                        $total_packing += $packing;
                        if ($product['data']->weight>0)
                            $total_weight += $product['quantity']*$product['data']->get_weight();
                        else
                            $total_weight += $product['quantity']*$options['default_weight'];
                    }
                    if ($emptyBasket)
                        return false;
                    $city = 0;

                    if (isset($_POST['post_data']))
                        parse_str($_POST['post_data'],$post_data);
                    else
                        $post_data = $_POST;
                    if ($package['destination']['country'] == 'IR') { //Iran country
                        $this->destination_state = $package['destination']['state']; //example: TE
                        //$package['destination']['postcode'] //1234567890
                        $city                    = $package['destination']['city'];
                        $state                   = $package['destination']['state'];
                    }
                    //echo $city;
                    //echo $total_weight;
                    # پرداخت به صورت آنلاین
                    $buyOnline  = $options['online_enable'] == 'yes';
                    # پرداخت درب منزل
                    $buyCOD     = $options['cod_enable'] == 'yes';

                    $buy_types = array();
                    if ($buyOnline)
                        $buy_types[] = postresan_helper::BUY_ONLINE;
                    if ($buyCOD)
                        $buy_types[] = postresan_helper::BUY_COD;

                    //print_r($buy_types);
                    $deliveryPishtaz    = $options['pishtaz_enable'] == 'yes';
                    $deliverySefareshi  = $options['sefareshi_enable'] == 'yes';

                    $delivery_types = array();
                    if ($deliveryPishtaz)
                        $delivery_types[] = postresan_helper::DELIVERY_PISHTAZ;
                    if ($deliverySefareshi)
                        $delivery_types[] = postresan_helper::DELIVERY_SEFARESHI;

                    //print_r($delivery_types);
                    $total_weight = wc_get_weight($total_weight,'g');
                    $postresan_helper = new postresan_helper($options['user'],$options['password']);
                    $total_price += $total_packing;
                    try {
                        # نوع پرداخت رامشخص میکند
                        $chosen_payment_method = WC()->session->get('chosen_payment_method');

                        global $wpdb;
                        //echo $city;
                        //$rescity        = $wpdb->get_var("SELECT code FROM wp_citys where city='" . $city . "'");
                        # فراخوانی متد برای محاسبه هزینه پست
                        if ($buyOnline) {
                            if ($deliveryPishtaz) {
                                $resultOnlinePishtaz = $postresan_helper ->getPrices($city, $total_price, $total_weight, 2, 2);
                            }
                            if ($deliverySefareshi) {
                                $resultOnlineSefareshi = $postresan_helper ->getPrices($city, $total_price, $total_weight, 2, 1);
                            }
                        }
                        if ($buyCOD) {
                            if ($deliveryPishtaz) {
                                $resultCodPishtaz = $postresan_helper ->getPrices($city, $total_price, $total_weight, 1, 2);
                            }
                            if ($deliverySefareshi) {
                                $resultCodSefareshi = $postresan_helper ->getPrices($city, $total_price, $total_weight, 1, 1);
                                //echo $total_price;

                            }
                        }

                    } catch (PostresanWebserviceException $e) {
                        wc_add_notice($e->getMessage(),'error');
                        return false;
                    }
                    $total_packing = $total_packing;


                    foreach ($delivery_types as $delivery) {
                        if ($delivery == postresan_helper::DELIVERY_SEFARESHI) {
                            $deliveryLabel = 'سفارشی '.$buyTypeLabel;
                            $id = $this->id.'_sefareshi_'.$buyType;
                            if($chosen_payment_method == "cod"){
                                $Cost = $resultCodSefareshi['PostPrice']+$resultCodSefareshi['TaxPrice']+$resultCodSefareshi['ServicePrice']+$resultCodSefareshi['ProductsPrice'];
                            }
                            else{
                                $Cost = $resultOnlineSefareshi['PostPrice']+$resultOnlineSefareshi['TaxPrice']+$resultOnlineSefareshi['ServicePrice']+$resultOnlineSefareshi['ProductsPrice'];
                            }

                        } else {
                            $deliveryLabel = 'پیشتاز '.$buyTypeLabel;
                            $id = $this->id.'_pishtaz_'.$buyType;
                            if($chosen_payment_method == "cod"){
                                $Cost = $resultCodPishtaz['PostPrice']+$resultCodPishtaz['TaxPrice']+$resultCodPishtaz['ServicePrice']+$resultCodPishtaz['ProductsPrice'];
                            }
                            else{
                                $Cost = $resultOnlinePishtaz['PostPrice']+$resultOnlinePishtaz['TaxPrice']+$resultOnlinePishtaz['ServicePrice']+$resultOnlinePishtaz['ProductsPrice'];
                            }

                        }

                        if($Cost == 0){
                            if ($delivery == postresan_helper::DELIVERY_SEFARESHI) {
                                $Cost = $options['fixed_sefareshi'];
                            }
                            else{
                                $Cost = $options['fixed_pishtaz'];
                            }
                        }
                        //$Cost = $resultCodPishtaz['PostPrice'];

                        /**Convert To Shop Unit **/
                        $symbol = strtoupper(get_woocommerce_currency());

                        $rate = array(
                            'id'    => $id,
                            'label' => $deliveryLabel,
                            'cost'  => convertToShopUnitCurrency($Cost)
                        );
                        $this->add_rate($rate);
                    }
                    return true;
                }
            }
        }
    }

    function install_postresan_plugin()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();


        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    }
}



/**
 * register shipping method to woocommerce
 *
 * @param array $methods
 *
 * @return array
 */
function add_postresan_shipping_method($methods)
{
    $methods[] = 'WC_Postresan_Shipping_Method';
    return $methods;
}



register_activation_hook(__FILE__,'install_postresan_plugin');
register_deactivation_hook(__FILE__,'');
//Disable SSL Verification
$postresan_options = get_option('woocommerce_postresan_shipping_settings');
add_filter('woocommerce_shipping_methods','add_postresan_shipping_method');
add_action('woocommerce_shipping_init','postresan_shipping_method_init');
add_filter('woocommerce_calculated_total','calculated_total_price');
add_action( 'woocommerce_thankyou', 'postresan_register_order' );
add_action( 'woocommerce_review_order_before_payment', 'refresh_payment_methods' );
add_action('plugins_loaded','plugins_load');
add_filter( 'woocommerce_states', 'custom_postresan_woocommerce_states' ,99);
add_action('woocommerce_after_checkout_form','add_load_state_js');
add_filter('woocommerce_checkout_fields','field_city_province');

// add state and cities based on frotel ID
function custom_postresan_woocommerce_states( $states ) {

    $fstates = json_decode(file_get_contents('https://service.postresan.com/js/city.json'),true);
    $tmp_states = array();
    foreach($fstates as $state){
        $tmp_states[$state['id']] =  $state['name'];
    }
    $states['IR'] = $tmp_states;
    return $states;
}
function add_load_state_js()
{
    //wp_enqueue_script('add_postresan_js',plugins_url('/Postresan-Shipping/js/lib.js'));
    echo '
     <script type="text/javascript" src="https://service.postresan.com/js/city.js"></script>
     <script type="text/javascript">
         jQuery(function(){
             jQuery("select#billing_state").change(function(){
                     ldMenu(jQuery(this).val(),"billing_city");
             })
         });
        var wc_ajax_url_postresan = "'.WooCommerce::instance()->ajax_url().'";
     </script>';
}

function field_city_province($fields)
{
    $critical_css = file_get_contents( WP_PLUGIN_DIR . '/woocommerce/assets/css/select2.css' );
    // $css = WP_PLUGIN_DIR . '/woocommerce';
    // print_r( $css);
    preg_match_all( '/(?ims)([a-z0-9\s\.\:#_\-@,]+)\{([^\}]*)\}/', $critical_css, $arr);
    $selector = trim($arr[0][1]);
    $rules = explode(';', trim($arr[2][1]));
    $rules_arr = array();
    foreach ($rules as $strRule){
        if (!empty($strRule)){
            $rule = explode(":", $strRule);
            $rules_arr[trim($rule[0])] = trim($rule[1]);
        }
    }

    $stateheight  =$rules_arr['height'];
    global $city_style;
    # فیلد انتخاب شهر ساخته میشود
    $fields['billing']['billing_city'] = array(
        'type'      => 'select',
        'label'     => __('City', 'woocommerce'),
        'required'  => true,
        'class'     => array('address-field','form-row-wide'),
        'custom_attributes' => array('style' =>'height: '.$stateheight .';border-radius:5px;'),
        'options'   => array(
            '' => 'شهر را انتخاب کنید'
        )
    );
    $fields['billing']['billing_state']['clear'] = true;
    if($city_style == 'yes') {
        $fields['shipping']['shipping_state']['class'] = $fields['billing']['billing_state']['class'] = array('form-row-last');
        $fields['shipping']['shipping_city']['class'] = $fields['billing']['billing_city']['class'] = array('form-row-first');
    }
    // reorder fields
    $order = array(
        'first_name',
        'last_name',
        'company',
        'email',
        'phone',
        'country',
        'city',
        'state',
        'address_1',
        'address_2',
        'postcode',
    );
    $tmp = array();
    foreach($order as $item){
        if (isset($fields['shipping']['shipping_'.$item]))
            $tmp['shipping']['shipping_'.$item] = $fields['shipping']['shipping_'.$item];
        if (isset($fields['billing']['billing_'.$item]))
            $tmp['billing']['billing_'.$item] = $fields['billing']['billing_'.$item];
    }
    $fields['billing'] = $tmp['billing'];
    $fields['shipping'] = $tmp['shipping'];
    unset($tmp);
    $fields['shipping']['shipping_postcode']['class'] = $fields['billing']['billing_postcode']['class'] = array('form-row-wide');
    //$fields['shipping']['shipping_city']['style'] = $fields['billing']['billing_city']['style'] = array('height: 60px;');
    $fields['order']['order_comments']['class'] = array('form-row-wide','notes');
    return $fields;
}


function plugins_load(){
    remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
    add_action( 'woocommerce_after_order_notes', 'woocommerce_checkout_payment', 30 );
}




function refresh_payment_methods(){

    ?>
    <script type="text/javascript">

        jQuery(function(){
            jQuery( 'form.checkout' ).on( 'change', 'input[name^="payment_method"]', function() {

                if(jQuery('#billing_address_1').val() !="") {
                    var address = jQuery('#billing_address_1').val();
                    jQuery.trim(address) == address ? jQuery('#billing_address_1').val(address + ' ') : jQuery('#billing_address_1').val(jQuery.trim(address));
                }
                jQuery('body').trigger('update_checkout');

            });
        });
    </script>
    <?php
}

function calculated_total_price($total_price)
{
    /**
     * @var WooCommerce $woocommerce
     */
    global $woocommerce;

    return $total_price;
}
function postresan_register_order($order_id ){

    $coupons_amount1 = 0;
    $order           = wc_get_order($order_id);
    foreach ($order->get_used_coupons() as $coupon_name) {
        $coupon_post_obj = get_page_by_title($coupon_name, OBJECT, 'shop_coupon');
        $coupon_id       = $coupon_post_obj->ID;
        $coupons_obj     = new WC_Coupon($coupon_id);
        if ($coupons_obj->get_discount_type() == 'cash_back_percentage') {
            $coupons_amount1 = $coupons_obj->get_amount();
        }
        if ($coupons_obj->is_type('cash_back_fixed')) {
            // Get the coupon object amount
            $coupons_amount2 = $coupons_obj->get_amount();
        }
    }
    global $wpdb;
    $weight_unit               = get_option('woocommerce_weight_unit');
    $order_data                = $order->get_data();
    $order                     = new WC_Order($order_id);
    $items                     = $order->get_items();
    $customer_message          = $order->get_customer_note();
    // 2) Get the Order meta data
    $order_meta                = get_post_meta($order_id);
    $items                     = $order->get_items();
    $order_shipping_first_name = $order_data['shipping']['first_name'];
    $order_shipping_last_name  = $order_data['shipping']['last_name'];
    $order_shipping_company    = $order_data['shipping']['company'];
    $order_shipping_address_1  = $order_data['shipping']['address_1'];
    $order_shipping_address_2  = $order_data['shipping']['address_2'];
    $order_shipping_city       = $order_data['shipping']['city'];
    $order_shipping_state      = $order_data['shipping']['state'];
    $order_shipping_postcode   = $order_data['shipping']['postcode'];
    $order_shipping_email      = $order_data['billing']['email'];
    $order_shipping_country    = $order_data['shipping']['country'];
    $order_shipping_ip         = $order_meta['_customer_ip_address'][0];
    $order_shipping_phonenum   = $order_meta['_billing_phone'][0];
    $ordersdetail              = array();
    foreach ($items as $item) {
        $product        = wc_get_product($item['product_id']);
        $product_weight = $product->get_weight();
        if ($weight_unit == "kg") {
            $product_weight = $product_weight * 1000;
        }

        $product_name     = $product->get_name();
        $product_price    = $product->get_regular_price();
        $curren = get_option('woocommerce_currency');
        if ($curren == 'IRT') {
            //$this->extra_cost = $this->extra_cost * 10;
            $product_price = $product_price * 10;
        } elseif ($curren == 'IRHT') {
            //$this->extra_cost = $this->extra_cost * 10000;
            $product_price = $product_price * 10000;
        }
        $product_id       = $product->get_id();
        $product_quantity = $item['quantity'];
        $ordersdetail[]   = array(
            "ProductId" => $product_id,
            "Amount" => $product_quantity,
            "Title" => $product_name,
            "Price" => $product_price,
            "Weight" => $product_weight
        );

    }
    $buy_type_insert = $order->get_payment_method();
    if($buy_type_insert == "cod")
        $buy_type_insert = 1;
    else{
        $buy_type_insert = 2;
    }
    global $woocommerce;
    $chosen_methods = $woocommerce->session->get('chosen_shipping_methods');
    $chosen_shipping = $chosen_methods[0];
    $chosen_shipping = str_ireplace('postresan_shipping_','',$chosen_shipping);
    $chosen_shipping = explode('_',$chosen_shipping);
    //print_r($chosen_shipping);
    switch ($chosen_shipping[0]) {
        case 'sefareshi':
            $deliveryType = postresan_helper::DELIVERY_SEFARESHI;
            break;
        case 'pishtaz':
            $deliveryType = postresan_helper::DELIVERY_PISHTAZ;
            break;
    }
    //echo $deliveryType;
    global $username;
    global $password;
    $postresan_helper = new postresan_helper($username,$password);
    $result = $postresan_helper->registerOrder(
        $descity,
        $order_shipping_first_name,
        $order_shipping_last_name,
        $ordersdetail,
        $order_shipping_phonenum,
        $order_shipping_phonenum,
        $order_shipping_email,
        $order_shipping_address_1,
        $order_shipping_postcode,
        $buy_type_insert,
        $deliveryType,
        $customer_message,
        $order_shipping_ip,
        0
    );
    //var_dump($result);
    //echo $options['password'];
}
/**
 * تبدیل مبلغ به ریال
 *
 * @param float $price
 *
 * @return float
 */
function convertToRial($price)
{
    $symbol = strtoupper(get_woocommerce_currency());
    switch ($symbol) {
        case 'IRR':         # ریال
        default:
            return $price;
            break;
        case 'IRHR':        # هزار ریال
            return $price*1000;
            break;
        case 'IRT':        # تومان
            return $price*10;
            break;
        case 'IRHT':        # هزار تومان
            return $price*10000;
            break;
    }
}
/**
 * تبدیل مبلغ از ریال به واحد پولی فروشگاه
 *
 * @param float $price
 *
 * @return float
 */
function convertToShopUnitCurrency($price)
{
    $symbol = strtoupper(get_woocommerce_currency());
    //echo $price;
    switch ($symbol) {
        case 'IRR':         # ریال
        default:
            return $price;
            break;
        case 'IRHR':        # هزار ریال
            return $price/1000;
            break;
        case 'IRT':        # تومان
            return $price/10;
            break;
        case 'IRHT':        # هزار تومان
            return $price/10000;
            break;
    }
}
