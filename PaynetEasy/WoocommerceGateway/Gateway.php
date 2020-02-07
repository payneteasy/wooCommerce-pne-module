<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\Exception\ValidationException;
use PaynetEasy\PaynetEasyApi\Strategies\LoggerInterface;
use PaynetEasy\PaynetEasyApi\Strategies\Transaction;

class Gateway                       extends     \WC_Payment_Gateway
                                    implements  LoggerInterface
{
    /**
     * Log severity level (default ERROR)
     * @var int
     */
    protected $log_level_severity   = 500;
    /**
     * @var \WC_Logger
     */
    protected $logger;
    
    /**
     * @var WCIntegration
     */
    protected $wc_integration;

    public function __construct()
    {
        // payment gateway plugin ID
        $this->id                   = 'payneteasy';
        // Title
        $this->title                = __('Visa Mastercard', 'paynet-easy-gateway');
        // URL of the icon that will be displayed on checkout page near your gateway name
        $this->icon                 = $this->image_url('visa-mastercard.jpg');
        // in case you need a custom credit card form
        $this->has_fields           = true;
        // Title of method
        $this->method_title         = __('PaynetEasy Gateway', 'paynet-easy-gateway');
        // will be displayed on the options page
        $this->method_description   = __('PaynetEasy Gateway for Visa and Mastercard', 'paynet-easy-gateway');

        /*
        $this->supports             = [
            'products',
            'subscriptions',
            'refunds',
            'subscription_cancellation',
            'subscription_reactivation',
            'subscription_suspension',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'subscription_payment_method_change_customer',
            'subscription_payment_method_change_admin',
            'multiple_subscriptions',
        ];
        */
        $this->supports             = ['products', 'refunds'];

        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        // Setup title
        if(!empty($this->settings['title']))
        {
            $this->title            = $this->settings['title'];
        }

        // Setup title
        if(!empty($this->settings['description']))
        {
            $this->description      = $this->settings['description'];
        }
        
        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options'] );

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', [$this, 'payment_scripts']);

        // Register a webhook here
        // http://domain.com/wc-api/{webhook name}/
        // example:
        // http://woo.pne.io/wc-api/paynet-easy-gateway_callback/
        // https://woo.pne.io/?wc-api=payneteasy_callback
        add_action('woocommerce_api_'.$this->id.'_callback', [$this, 'on_callback']);
        add_action('woocommerce_api_'.$this->id.'_redirect', [$this, 'on_redirect']);
        // Checkout filters
        add_filter( 'woocommerce_checkout_fields' , [$this, 'on_checkout_fields']);
        // email filters
        add_action( 'woocommerce_email_order_details', [$this, 'on_email_payment_details'], 10, 4);
        
        $this->wc_integration       = new WCIntegration($this->id, $this->settings, $this);
    
        // has fields mode is on only for Credit Card
        $this->has_fields           = $this->wc_integration->defineIntegrationMethod() === Transaction::METHOD_INLINE;
    }

    public function image_url($file)
    {
        /* @var $PaynetEasyWoocommerceGateway WordPressPlugin */
        global $PaynetEasyWoocommerceGateway;

        return $PaynetEasyWoocommerceGateway->plugin_url().'/public/images/'.$file;
    }

    public function payment_scripts()
    {
        return;
    }

    public function init_settings()
    {
        parent::init_settings();

        if(!empty($this->settings['log_level_severity']))
        {
            $this->log_level_severity = $this->settings['log_level_severity'];
        }
    }

    public function on_checkout_fields($fields)
    {
        // make billing_phone is required
        if(!empty($fields['billing']) && !empty($fields['billing']['billing_phone']))
        {
            $fields['billing']['billing_phone']['required'] = true;
        }

        return $fields;
    }

    public function validate_fields()
    {
        try
        {
            $errors                 = $this->wc_integration->validateData($_REQUEST);
            
            if(empty($errors))
            {
                return true;
            }
    
            foreach ($errors as $error)
            {
                wc_add_notice($error, 'error');
            }
            
            return false;
        }
        catch (\Exception $exception)
        {
            wc_add_notice(__('Validation error', 'paynet-easy-gateway'), 'error');
            
            return false;
        }
    }

    /**
     * Start of process order
     *
     * @param   int                 $order_id
     *
     * @return  array
     *
     * @throws  \Exception
     */
    public function process_payment($order_id)
    {
        $transaction                = $this->wc_integration->startPayment($order_id);

        // Redirect to the thank you page
        return
        [
            'result'                => 'success',
            'redirect'              => $this->define_redirect_for_transaction($transaction)
        ];
    }

    /**
     * @return Transaction
     *
     * @throws \Exception
     */
    public function handle_progress()
    {
        return $this->wc_integration->processPayment();
    }

    public function define_redirect_for_transaction(Transaction $transaction)
    {
        $transaction_id             = $transaction->getTransactionId();
        
        // If processing
        if($transaction->isProcessing() && $transaction->isStop() === false)
        {
            // or redirect or order page
            $redirect               = $transaction->getRedirectUrl() ?? $this->wc_integration->getProcessPageUrl($transaction_id);
        }
        else
        {
            // default redirect to order
            $redirect               = $this->get_return_url(wc_get_order($transaction->getOrderId()));
        }

        return $redirect;
    }

    /**
     * Handler for callback from PaynetEsy
     *
     * @return int
     *
     * @throws \Exception
     */
    public function on_callback()
    {
        return $this->wc_integration->processCallback();
    }

    /**
     * Handler for redirect (customer return to site) from PaynetEasy
     *
     * @return  int
     *
     * @throws  \Exception
     */
    public function on_redirect()
    {
        $transaction            = $this->wc_integration->processRedirect();
        
        $redirect               = $this->define_redirect_for_transaction($transaction);

        if($redirect !== null)
        {
            wp_redirect($redirect);
        }

        return 1;
    }

    public function can_refund_order($order)
    {
        return true;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $transaction                = $this->wc_integration->startRefund($order_id, $amount, $reason);
        
        return true;
    }

    /**
     * @return string
     */
    public function get_merchant_country()
    {
        $merchant_country           = get_option('woocommerce_default_country');

        if(empty($merchant_country))
        {
            return '';
        }

        // country + state
        $merchant_country           = explode(':', $merchant_country);

        // Country and state separated:
        $merchant_country           = $merchant_country[0];

        if(!empty($merchant_country) & !empty(WC()->countries->countries[$merchant_country]))
        {
            return WC()->countries->countries[$merchant_country];
        }

        return '';
    }

    /**
     * Out extra data for email
     *
     * Hooked into `woocommerce_email_order_details` action hook.
     *
     * @param WC_Order $order         Order data.
     * @param bool     $sent_to_admin Send to admin (default: false).
     * @param bool     $plain_text    Plain text email (default: false).
     * @throws \Exception
     */
    public function on_email_payment_details($order, $sent_to_admin = false, $plain_text = false)
    {
        if ($plain_text || ! is_a( $order, 'WC_Order' )) {
            return;
        }

        /**
         * @var $order \WC_Order
         */
        // Find transaction for current order:
        $transaction                = $this->wc_integration->findTransactionByOrderId(
            $order->get_id(), ['transaction_type' => 'sale', 'state' => Transaction::STATE_PROCESSING]
        );

        // If transaction not exists nothing to do
        if(empty($transaction)) {
            return;
        }

        /**
         * @var $transaction Transaction
         */
        $data                       = $transaction->getCallbackData();

        if(empty($data)) {
            return;
        }

        $status                     = !empty($data['status']) ? ucfirst($data['status']) : '';
        $approval_code              = !empty($data['approval-code']) ? $data['approval-code'] : '';

        $authorization_code         = [];

        if(!empty($status))
        {
            $authorization_code[]   = $status;
        }

        if(!empty($approval_code))
        {
            $authorization_code[]   = $approval_code;
        }

        $WC_Email                   = new \WC_Email();

        $merchant_name              = get_option('woocommerce_store_address');
        $merchant_country           = $this->get_merchant_country();
        $merchant_online_address    = get_site_url();
        $rrn                        = !empty($data['processor-rrn']) ? $data['processor-rrn'] : '';
        $authorization_code         = implode(', ', $authorization_code);
        $transaction_type           = !empty($data['card-type']) ? $data['card-type'] : '';
        $card_final_4_digits        = !empty($data['last-four-digits']) ? $data['last-four-digits'] : '';
        $return_policy              = home_url('/return-policy/');
        $refund_policy              = home_url('/refund-policy/');
        $customer_service_contact   = $WC_Email->get_from_address();

        ?>
        <h3>Payment details</h3>
        <ul>
            <li><b>Merchant Name</b>: <?=$merchant_name?></li>
            <li><b>Merchant Country</b>: <?=$merchant_country?></li>
            <li><b>Merchant online address</b>: <a href="<?=$merchant_online_address?>"><?=$merchant_online_address?></a></li>
            <li><b>Retrieval Reference Number</b>: <?=$rrn?></li>
            <li><b>Authorization code</b>: <?=$authorization_code?></li>
            <li><b>Transaction type</b>: <?=$transaction_type?></li>
            <li><b>Card number, the final 4 digits</b>: <?=$card_final_4_digits?></li>
            <li><b>Return policy</b>: <a href="<?=$return_policy?>" target="_blank"><?=$return_policy?></a></li>
            <li><b>Refund policy</b>: <a href="<?=$refund_policy?>" target="_blank"><?=$refund_policy?></a></li>
            <li><b>Customer service contact</b>: <?=$customer_service_contact?></li>
        </ul>
        <?php

    }

    /**
     * Logging method
     *
     * @param       string          $message
     * @param       string          $level
     * @return      $this
     */
    public function log($message, $level = \WC_Log_Levels::NOTICE)
    {
        // Filter by error level
        if(\WC_Log_Levels::get_level_severity($level) < $this->log_level_severity)
        {
            return $this;
        }

        if (empty($this->logger))
        {
            $this->logger       = new \WC_Logger();
        }

        $this->logger->add($this->id, $message, $level);

        return $this;
    }

    public function debug($message)
    {
        return $this->log($message, \WC_Log_Levels::DEBUG);
    }

    public function error($message)
    {
        return $this->log($message, \WC_Log_Levels::ERROR);
    }
    
    public function notice($message)
    {
        return $this->log($message, \WC_Log_Levels::NOTICE);
    }
    
    /**
     * Initialize Gateway Settings form fields
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields          = [

            'enabled'               => [
                'title'             => __('Enable/Disable', 'paynet-easy-gateway'),
                'label'             => __('Enable', 'paynet-easy-gateway'),
                'type'              => 'checkbox',
                'description'       => '',
                'default'           => 'no',
            ],
            'title'                 => [
                'title'             => __('Title', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('This controls the title which the user sees during checkout.', 'paynet-easy-gateway'),
                'default'           => __('Credit Card', 'paynet-easy-gateway'),
            ],
            'description'           => [
                'title'             => __('Description', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __( 'This controls the description which the user sees during checkout.', 'paynet-easy-gateway'),
                'default'           => 'Pay securely using your credit card.',
            ],
            'test_mode'             => [
                'title'             => __( 'Sandbox test mode', 'paynet-easy-gateway' ),
                'label'             => __( 'Enable sandbox test mode', 'paynet-easy-gateway' ),
                'type'              => 'checkbox',
                'description'       => __( 'Place the payment gateway in development mode.', 'paynet-easy-gateway' ),
                'default'           => 'no',
            ],
            'log_level_severity'    => [
                'title'             => __('Log level', 'paynet-easy-gateway' ),
                'type'              => 'select',
                'class'             => 'select',
                'default'           => 500,
                'options'           => [
                    800             => 'EMERGENCY',
                    700             => 'ALERT',
                    600             => 'CRITICAL',
                    500             => 'ERROR',
                    400             => 'WARNING',
                    300             => 'NOTICE',
                    200             => 'INFO',
                    100             => 'DEBUG',
                ],
            ],
            'integration_method'    => [
                'title'             => __('Integration Method', 'paynet-easy-gateway'),
                'type'              => 'select',
                'class'             => 'select',
                'default'           => 'sale',
                'description'       => __( 'Accepting payment details and all this stuff is completely implemented on the PaynetEasy gateway side', 'paynet-easy-gateway'),
                'options'           => [
                    'inline'        => __('Inline form', 'paynet-easy-gateway'),
                    'form'          => __('Remote form', 'paynet-easy-gateway')
                ]
            ],
            'end_point'             => [
                'title'             => __('End point', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('The End point ID is an entry point for incoming Merchant’s transactions for single currency integration', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'end_point_group'       => [
                'title'             => __('End point group', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('The End point group ID is an entry point for incoming Merchant’s transactions for multi currency integration', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'login'                 => [
                'title'             => __('Login', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Merchant login name', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'merchant_control'      => [
                'title'             => __('Control Key', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Merchant control string for sign', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'gateway_url'           => [
                'title'             => __('Gateway url', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Url for gateway', 'paynet-easy-gateway'),
                'default'           => 'https://payneteasy.com/paynet/api/v2/'
            ],
            // test data
            'sandbox_end_point'     => [
                'title'             => __('Sandbox End point', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('The End point ID is an entry point for incoming Merchant’s transactions for single currency integration', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'sandbox_end_point_group' => [
                'title'             => __('Sandbox End point group', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('The End point group ID is an entry point for incoming Merchant’s transactions for multi currency integration', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'sandbox_login'         => [
                'title'             => __('Sandbox Login', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Merchant login name', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'sandbox_merchant_control' => [
                'title'             => __('Sandbox Control Key', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Merchant control string for sign', 'paynet-easy-gateway'),
                'default'           => '',
                'css'               => 'width: 400px',
            ],
            'gateway_url_sandbox'   => [
                'title'             => __('Gateway url sandbox', 'paynet-easy-gateway'),
                'type'              => 'text',
                'description'       => __('Url for gateway sandbox', 'paynet-easy-gateway'),
                'default'           => 'https://payneteasy.com/paynet/api/v2/'
            ]
        ];
    }

    /**
     * Generate payment fields
     */
    public function payment_fields()
    {
        // you can instructions for test mode, I mean test card numbers etc.
        if ($this->wc_integration->isSandboxMode())
        {
            // let's display some description before the payment form
            if (empty($this->description))
            {
                $this->description = '';
            }

            $this->description .= ' <br/> <strong style="color: red">SANDBOX MODE ENABLED!</strong> ';
            $this->description .= ' (In test mode, you can use the card numbers listed in ';
            $this->description .= '<a href="http://doc.payneteasy.com/" target="_blank">documentation</a>)';

            $this->description  = trim($this->description);
        }

        // add to description form instruction
        if($this->wc_integration->defineIntegrationMethod() === Transaction::METHOD_FORM)
        {
            if(!is_string($this->description))
            {
                $this->description = '';
            }

            $this->description .= '<br/>'.__('You will be redirected to a secure site for entering credit card information.', 'paynet-easy-gateway');
        }

        // let's display some description before the payment form
        if (!empty($this->description))
        {
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
        }

        /**
         * If sale mode is off we not show credit card form
         */
        if(empty($this->has_fields))
        {
            return;
        }

        // I will echo() the form, but you can close PHP tags and print it directly in HTML
        echo '<fieldset id="wc-' . esc_attr($this->id) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

        // Add this action hook if you want your custom gateway to support it
        do_action('woocommerce_credit_card_form_start', $this->id);

        $card_number                = __('Card Number', 'paynet-easy-gateway');
        $printed_name               = __('Printed name', 'paynet-easy-gateway');
        $expiry_month               = __('Expiry month', 'paynet-easy-gateway');
        $expiry_year                = __('Expiry year', 'paynet-easy-gateway');
        $cvv2                       = __('Card Code (CVC)', 'paynet-easy-gateway');

        $card_number_value          = '';
        $printed_name_value         = '';
        $expiry_month_value         = '';
        $expiry_year_value          = '';
        $cvv2_value                 = '';

        if($this->wc_integration->isSandboxMode())
        {
            // test data
            $card_number_value      = '4444555566661111';
            $printed_name_value     = 'Test Name';
            $expiry_month_value     = rand(1, 12);
            $current_year           = (int)date('y');
            $expiry_year_value      = rand($current_year + 1, $current_year + 10);
            $cvv2_value             = '123';
        }

        echo <<<EOD
<div class="form-row form-row-wide">
  <label>$card_number <span class="required">*</span></label>
  <input name="credit_card_number" value="$card_number_value" type="text" autocomplete="off">
</div>
<div class="form-row form-row-wide">
  <label>$printed_name <span class="required">*</span></label>
  <input name="card_printed_name" value="$printed_name_value" type="text" autocomplete="off" placeholder="$printed_name">
</div>		
<div class="form-row form-row-first">
    <label>$expiry_month <span class="required">*</span></label>
    <input name="expire_month" value="$expiry_month_value" type="text" autocomplete="off" placeholder="MM">
</div>
<div class="form-row form-row-last">
    <label>$expiry_year <span class="required">*</span></label>
    <input name="expire_year" value="$expiry_year_value" type="text" autocomplete="off" placeholder="YY">
</div>
<div class="form-row form-row-first" style="text-align: right">
  <img src="{$this->image_url('cvv-caption_new.png')}" style="height: 80px">
</div>
<div class="form-row form-row-last">
    <label>$cvv2 <span class="required">*</span></label>
    <input name="cvv2" value="$cvv2_value" type="password" autocomplete="off" placeholder="CVV">
</div>
<div class="clear"></div>
EOD;

        do_action('woocommerce_credit_card_form_end', $this->id);

        echo '<div class="clear"></div></fieldset>';
    }
}