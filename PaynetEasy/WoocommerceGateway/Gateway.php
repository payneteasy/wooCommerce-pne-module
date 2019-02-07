<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\PaymentData\QueryConfig;
use PaynetEasy\PaynetEasyApi\PaymentProcessor;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;
use PaynetEasy\PaynetEasyApi\Transport\Response;

class Gateway                       extends \WC_Payment_Gateway
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
        $this->supports             = ['products'];

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
        add_action('woocommerce_api_'.$this->id.'_callback', [$this, 'on_callback']);
        add_action('woocommerce_api_'.$this->id.'_redirect', [$this, 'on_redirect']);
        // Checkout filters
        add_filter( 'woocommerce_checkout_fields' , [$this, 'on_checkout_fields']);
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

    public function on_callback()
    {
        $this->log('Detect callback using');

        // try to find by $paynet_order_id
        if(empty($_REQUEST['orderid']))
        {
            $this->log('Detect callback using with empty "orderid"');
            return -1;
        }

        $paynet_order_id            = $_REQUEST['orderid'];
        $transaction_id             = PaymentTransaction::find_by_paynet_order_id($paynet_order_id);

        if(empty($transaction_id))
        {
            $this->error('Detect callback using with wrong "orderid" = '.$paynet_order_id);
        }

        $this->log('Detect callback using with CORRECT "orderid" = '.$paynet_order_id);

        // 1. Init transaction
        $transaction                = new PaymentTransaction($transaction_id);
        $transaction->setQueryConfig($this->get_query_config());

        // notice
        $transaction->get_order()->add_order_note
        (
            __('CALLBACK has been received', 'paynet-easy-gateway').' (paynet id = '.$paynet_order_id.')'
        );

        // 2. Execute query
        $payment_processor          = $this->create_payment_processor();
        $payment_processor->processPaynetEasyCallback
        (
            new CallbackResponse($_REQUEST),
            $transaction
        );

        // 3. Handle results
        $transaction->handle_transaction()->save_transaction();

        return 1;
    }

    /**
     *
     *
     * @return  int
     *
     * @throws  \Exception
     */
    public function on_redirect()
    {
        // try to find by $paynet_order_id
        if(empty($_REQUEST['orderid']))
        {
            $this->log('Detect redirect_url using with empty "orderid"');
            return -1;
        }

        $paynet_order_id            = $_REQUEST['orderid'];
        $transaction_id             = PaymentTransaction::find_by_paynet_order_id($paynet_order_id);

        if(empty($transaction_id))
        {
            $this->error('Detect redirect_url using with wrong "orderid" = '.$paynet_order_id);
        }

        $this->log('Detect redirect_url using with CORRECT "orderid" = '.$paynet_order_id);

        // 1. Init transaction
        $transaction                = new PaymentTransaction($transaction_id);
        $transaction->setQueryConfig($this->get_query_config());

        // notice
        $transaction->get_order()->add_order_note
        (
            __('REDIRECT has been received', 'paynet-easy-gateway').' (paynet id = '.$paynet_order_id.')'
        );

        // 2. Execute query
        $payment_processor          = $this->create_payment_processor();
        $payment_processor->processCustomerReturn
        (
            new CallbackResponse($_REQUEST),
            $transaction
        );

        // 3. Handle results
        $transaction->handle_transaction()->save_transaction();

        $redirect               = $this->define_redirect_for_transaction($transaction);

        if($redirect !== null)
        {
            wp_redirect($redirect);
        }

        return 1;
    }

    public function validate_fields()
    {
        // Check fields
        $required                   =
        [
            //'billing_phone'         => __('Phone', 'paynet-easy-gateway'),
            'card_printed_name'     => __('Printed name', 'paynet-easy-gateway'),
            'credit_card_number'    => __('Card number', 'paynet-easy-gateway'),
            'expire_month'          => __('Expire month', 'paynet-easy-gateway'),
            'expire_year'           => __('Expire year', 'paynet-easy-gateway'),
            'cvv2'                  => __('Cvv', 'paynet-easy-gateway')
        ];

        $no_errors                  = true;

        foreach ($required as $name => $title)
        {
            if(empty($_REQUEST[$name]) || empty(trim($_REQUEST[$name])))
            {
                $no_errors          = true;
                wc_add_notice(sprintf( __( 'Required "%s" field', 'paynet-easy-gateway'), $title), 'error');
            }
        }

        return $no_errors;
    }

    /**
     * @param int $order_id
     * @return array
     * @throws \Exception
     */
    public function process_payment($order_id)
    {
        $this->log($order_id.': Start processing payment');

        // Init Payment Processor
        $payment_processor          = $this->create_payment_processor();
        $transaction                = new PaymentTransaction
        (PaymentTransaction::NEW_TRANSACTION,
            $order_id,
            $this->define_payment_method($order_id)
        );

        $transaction->setQueryConfig($this->get_query_config());

        // not create two transaction in twice
        if($transaction->is_processing())
        {
            $payment_processor->executeQuery('status', $transaction);
        }
        else
        {
            $payment_processor->executeQuery($transaction->get_payment_method(), $transaction);
        }

        $transaction->handle_transaction()->save_transaction();

        // Redirect to the thank you page
        return
        [
            'result'                => 'success',
            'redirect'              => $this->define_redirect_for_transaction($transaction)
        ];
    }

    /**
     * @param $transaction_id
     *
     * @return PaymentTransaction
     *
     * @throws \Exception
     */
    public function handle_progress($transaction_id)
    {
        // 1. Init transaction
        $transaction                = new PaymentTransaction($transaction_id);
        $transaction->setQueryConfig($this->get_query_config());

        // 2. Execute query
        $payment_processor          = $this->create_payment_processor();
        $payment_processor->executeQuery('status', $transaction);

        // 3. Handle results
        $transaction->handle_transaction()->save_transaction();

        return $transaction;
    }

    public function define_redirect_for_transaction(PaymentTransaction $transaction)
    {
        $transaction_id             = $transaction->transaction_id();

        // default redirect to order
        $redirect                   = $this->get_return_url($transaction->get_order());

        // If processing
        if($transaction->isProcessing())
        {
            // or redirect or order page
            $redirect               = $transaction->get_redirect_url() ?? $this->get_process_page_url($transaction_id);
        }

        return $redirect;
    }

    public function process_refund($order_id, $amount = null, $reason = '')
    {
        return parent::process_refund($order_id, $amount, $reason); // TODO: Change the autogenerated stub
    }

    public function can_refund_order($order)
    {
        return parent::can_refund_order($order); // TODO: Change the autogenerated stub
    }

    public function saved_payment_methods()
    {
        parent::saved_payment_methods(); // TODO: Change the autogenerated stub
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
            'debug_mode'            => [
                'title'             => __( 'Debug Mode', 'paynet-easy-gateway' ),
                'type'              => 'select',
                'desc_tip'          => __( 'Show Detailed Error Messages and API requests / responses on the checkout page.', 'paynet-easy-gateway' ),
                'default'           => 'off',
                'options'           => [
                    'off'           => __( 'Off', 'paynet-easy-gateway' ),
                    'on'            => __( 'On', 'paynet-easy-gateway' ),
                ],
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
            'payment_method'        => [
                'title'             => __('Payment Method', 'paynet-easy-gateway'),
                'type'              => 'select',
                'class'             => 'select',
                'default'           => 'sale',
                'description'       => __( 'Payment method description', 'paynet-easy-gateway'),
                'options'           => [
                    'sale'          => '3DSecure',
                    'sale_form'     => 'Form'
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

    public function payment_fields()
    {
        // you can instructions for test mode, I mean test card numbers etc.
        if ($this->is_sandbox_mode())
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

        // let's display some description before the payment form
        if (!empty($this->description))
        {
            // display the description with <p> tags etc.
            echo wpautop(wp_kses_post($this->description));
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

        if($this->is_sandbox_mode())
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

    /**
     * Обработчик для сохранения транзакции
     * Saving transaction handler
     *
     * @param PaymentTransaction $transaction
     * @param Response $response
     */
    public function on_save_transaction(PaymentTransaction $transaction, Response $response = null)
    {
        if($response !== null)
        {
            $transaction->set_response($response);
        }

        $transaction->save_transaction();
    }

    /**
     * @return \PaynetEasy\PaynetEasyApi\PaymentProcessor
     */
    protected function create_payment_processor()
    {
        $handlers                   =
        [
            PaymentProcessor::HANDLER_SAVE_CHANGES => [$this, 'on_save_transaction']
        ];

        $payment_processor          = new PaymentProcessor($handlers);

        return $payment_processor;
    }

    /**
     * @param string $order_id
     * @return string
     */
    protected function define_payment_method($order_id = null)
    {
        if(!empty($this->settings['payment_method']))
        {
            return $this->settings['payment_method'];
        }

        // default payment method
        return 'sale';
    }

    /**
     * @return QueryConfig
     */
    protected function get_query_config()
    {
        /**
         * Точка входа для аккаунта мерчанта, выдается при подключении
         */
        $end_point                  = $this->get_end_point();
        $end_point_group            = $this->get_end_point_group();

        $config                     =
        [
            /**
             * Логин мерчанта, выдается при подключении
             */
            'login'                 => $this->get_paynet_login(),
            /**
             * Ключ мерчанта для подписывания запросов, выдается при подключении
             */
            'signing_key'           => $this->get_merchant_control(),
            /**
             * URL на который пользователь будет перенаправлен после окончания запроса
             */
            'redirect_url'          => $this->get_redirect_url(),
            /**
             * URL на который пользователь будет перенаправлен после окончания запроса
             */
            'callback_url'          => $this->get_callback_url(),
            /**
             * Режим работы библиотеки: sandbox, production
             */
            'gateway_mode'          => $this->get_gateway_mode(),
            /**
             * Ссылка на шлюз PaynetEasy для режима работы sandbox
             */
            'gateway_url_sandbox'   => $this->settings['gateway_url_sandbox'] ?? null,
            /**
             * Ссылка на шлюз PaynetEasy для режима работы production
             */
            'gateway_url_production' => $this->settings['gateway_url'] ?? ''
        ];

        if(!empty($end_point_group))
        {
            $config['end_point_group']  = $end_point_group;
        }
        else
        {
            $config['end_point']        = $end_point;
        }

        return new QueryConfig($config);
    }

    /**
     * @return string
     */
    protected function get_end_point()
    {
        $result                     = $this->is_sandbox_mode() ?
                                    $this->settings['sandbox_end_point'] : $this->settings['end_point'];

        return $result ?? $this->settings['end_point'];
    }

    /**
     * @return string
     */
    protected function get_end_point_group()
    {
        $result                     = $this->is_sandbox_mode() ?
                                    $this->settings['sandbox_end_point_group'] : $this->settings['end_point_group'];

        return $result ?? $this->settings['end_point_group'];
    }

    /**
     * @return string
     */
    protected function get_paynet_login()
    {
        $result                     = $this->is_sandbox_mode() ?
                                    $this->settings['sandbox_login'] : $this->settings['login'];

        return $result ?? $this->settings['login'];
    }

    /**
     * @return string
     */
    protected function get_merchant_control()
    {
        $result                     = $this->is_sandbox_mode() ?
                                      $this->settings['sandbox_merchant_control'] : $this->settings['merchant_control'];

        return $result ?? $this->settings['merchant_control'];
    }

    protected function get_redirect_url()
    {
        return add_query_arg(['wc-api' => $this->id.'_redirect'], home_url('/'));
    }

    protected function get_callback_url()
    {
        return add_query_arg(['wc-api' => $this->id.'_callback'], home_url('/'));
    }

    /**
     * @param   string      $transaction_id
     * @param   array       $ex_params
     * @return  string
     */
    protected function get_process_page_url($transaction_id, array $ex_params = [])
    {
        $ex_params[PAYNET_EASY_PAGE]    = 1;
        $ex_params['transaction_id']    = $transaction_id;

        return add_query_arg($ex_params, home_url('/'));
    }

    protected function is_sandbox_mode()
    {
        return !empty($this->settings['test_mode']);
    }

    protected function get_gateway_mode()
    {
        return $this->is_sandbox_mode() ? QueryConfig::GATEWAY_MODE_SANDBOX : QueryConfig::GATEWAY_MODE_PRODUCTION;
    }

    /**
     * Logging method
     *
     * @param       string          $message
     * @param       string          $level
     * @return      $this
     */
    protected function log($message, $level = \WC_Log_Levels::NOTICE)
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

    protected function debug($message)
    {
        return $this->log($message, \WC_Log_Levels::DEBUG);
    }

    protected function error($message)
    {
        return $this->log($message, \WC_Log_Levels::ERROR);
    }
}