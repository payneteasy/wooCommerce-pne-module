<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\PaymentData\BillingAddress;
use PaynetEasy\PaynetEasyApi\PaymentData\CreditCard;
use PaynetEasy\PaynetEasyApi\PaymentData\Customer;
use PaynetEasy\PaynetEasyApi\PaymentData\Payment;
use PaynetEasy\PaynetEasyApi\Transport\Response;
use PaynetEasy\PaynetEasyApi\Util\RegionFinder;

/**
 * Class PaymentTransaction
 * @package PaynetEasy\WoocommerceGateway
 */
class PaymentTransaction            extends \PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction
{
    const DATABASE_TABLE            = 'payneteasy_transactions';
    /**
     * New transaction
     */
    const NEW_TRANSACTION           = null;
    /**
     * State of handle
     */
    const STATE_NEW                 = 'new';
    const STATE_PROCESSING          = 'processing';
    const STATE_DONE                = 'done';
    
    /**
     * Table for transactions
     * @var string
     */
    protected $table;

    /**
     * @var string
     */
    protected $order_id;
    /**
     * @var int
     */
    protected $transaction_id;
    /**
     * sale,reversal,capture,preauth
     *
     * @var string
     */
    protected $transaction_type;
    /**
     * inline, form
     *
     * @var string
     */
    protected $integration_method;
    /**
     * @var string
     */
    protected $state;
    /**
     * Html for 3D redirect
     * @var string
     */
    protected $html;
    /**
     * Response from server
     * @var Response
     */
    protected $response;

    /**
     * @var \WC_Order
     */
    protected $order;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Find by $paynet_order_id
     *
     * @param       string      $paynet_order_id
     * @return      string|null
     */
    public static function find_by_paynet_order_id($paynet_order_id)
    {
        global $wpdb;

        $paynet_order_id            = esc_sql($paynet_order_id);
        $table                      = $wpdb->prefix.self::DATABASE_TABLE;

        $query                      = "SELECT * FROM {$table} WHERE paynet_order_id = '{$paynet_order_id}'";

        $result                     = $wpdb->get_row($query, ARRAY_A);

        if(empty($result))
        {
            return null;
        }

        return $result['transaction_id'];
    }

    /**
     * PaymentTransaction constructor.
     *
     * @param       string          $transaction_id
     * @param       string          $order_id
     * @param       string          $integration_method
     * @param       string          $transaction_type
     *
     * @throws      \Exception
     */
    public function __construct
    (
        $transaction_id             = null,
        $order_id                   = null,
        $integration_method         = null,
        $transaction_type           = self::SALE
    )
    {
        global $wpdb;

        // define of table name
        $this->table                = $wpdb->prefix.self::DATABASE_TABLE;

        parent::__construct();

        $this->state                = self::STATE_NEW;
        $this->transaction_id       = $transaction_id;
        $this->order_id             = $order_id;
        $this->integration_method   = $integration_method;
        $this->transaction_type     = $transaction_type;
        $this->response             = null;

        // try to find exists transaction by order id
        if($this->transaction_id === null && $order_id !== null)
        {
            $this->transaction_id   = $this->find_processing_transaction($order_id);
        }

        if($this->transaction_id !== null)
        {
            $this->load_data();
        }

        if($this->order_id !== null)
        {
            $this->order            = wc_get_order($this->order_id);
        }

        $this->define_payment_data();
    }

    public function assign_logger(LoggerInterface $logger)
    {
        $this->logger               = $logger;
        return $this;
    }

    public function transaction_id()
    {
        return $this->transaction_id;
    }

    /**
     * Returns payment method for this transaction
     *
     * @return string|null
     */
    public function get_integration_method()
    {
        return $this->integration_method;
    }

    /**
     * Is inline payment method (used credit card form)
     *
     * @return bool
     */
    public function is_inline_integration()
    {
        return $this->integration_method === self::METHOD_INLINE;
    }

    public function define_payment_method()
    {
        if($this->is_inline_integration())
        {
            return $this->transaction_type;
        }

        return $this->transaction_type.'-'.$this->integration_method;
    }

    public function define_operation()
    {
        return 'sale';
    }

    public function get_html_for_show()
    {
        if(empty($this->html))
        {
            return null;
        }

        return $this->html;
    }

    /**
     * @return bool
     */
    public function is_redirect()
    {
        return $this->response !== null && $this->response->getNeededAction() === Response::NEEDED_REDIRECT;
    }

    /**
     * @return string|null
     */
    public function get_redirect_url()
    {
        if($this->is_redirect())
        {
            return $this->response->getRedirectUrl();
        }

        return null;
    }

    /**
     * @return string
     */
    public function get_state(): string
    {
        return $this->state;
    }

    /**
     * @return bool
     */
    public function is_processing(): bool
    {
        return $this->state === self::STATE_PROCESSING;
    }

    /**
     * @return \WC_Order
     */
    public function get_order()
    {
        return $this->order;
    }

    /**
     * @return string
     */
    public function get_order_id()
    {
        return $this->order_id;
    }

    public function get_response()
    {
        return $this->response;
    }

    public function set_response(Response $response)
    {
        $this->response             = $response;

        return $this;
    }

    public function save_transaction()
    {
        $this->save_data();

        return $this;
    }

    /**
     * General handler for transaction
     *
     * @return $this
     */
    public function handle_transaction()
    {
        if($this->isDeclined() || $this->isError())
        {
            $this->handle_error();
        }
        elseif($this->isApproved())
        {
            $this->handle_approve();
        }
        elseif($this->isProcessing())
        {
            $this->handle_process();
        }

        return $this;
    }

    public function getNeededAction()
    {
        $response                   = $this->get_response();

        if(empty($response))
        {
            return null;
        }

        return $response->getNeededAction();
    }

    protected function handle_process()
    {
        $this->state                = 'processing';

        $action                     = $this->getNeededAction();

        if(empty($action) || $action === Response::NEEDED_STATUS_UPDATE)
        {
            /* Translators: It's status for order notes */
            $this->order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': wait');
        }
        elseif($action === Response::NEEDED_SHOW_HTML)
        {
            $this->order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': show html');
        }
        elseif($action === Response::NEEDED_REDIRECT)
        {
            $this->order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': redirect');
        }
    }

    protected function handle_approve()
    {
        $this->state                = 'done';

        $paynet_id                  = $this->getPayment()->getPaynetId();

        $this->order->add_order_note(__('Payment approved', 'paynet-easy-gateway')." (paynet id = $paynet_id)");
        $this->order->payment_complete($this->transaction_id);
    }

    protected function handle_error()
    {
        $this->state                = 'done';

        $errors                     = [];

        foreach ($this->errors as $error)
        {
            if($error instanceof \Exception)
            {
                $errors[]           = $error->getMessage();
            }
            else if(is_string($error))
            {
                $errors[]           = $error;
            }
        }

        $errors                     = implode("\n", $errors);

        $this->order->add_order_note(__('Payment declined', 'paynet-easy-gateway'). " errors:\n$errors");
        $this->order->update_status('failed', __('Payment declined', 'paynet-easy-gateway'));
    }

    /**
     * @throws \Exception
     */
    protected function define_payment_data()
    {
        if($this->transaction_id !== null)
        {
            return;
        }

        $customer_data              =
        [
            'first_name'            => $this->order->get_billing_first_name() ?? $this->order->get_shipping_first_name(),
            'last_name'             => $this->order->get_billing_last_name() ?? $this->order->get_shipping_last_name(),
            'email'                 => $this->order->get_billing_email() ?? $this->order->get_user()->user_email,
            'ip_address'            => $this->define_ip_address(),
            'birthday'              => $this->define_birthday(),
            // additional data
            'customer_accept_language' => $_REQUEST['customer_accept_language'] ?? '',
            'customer_user_agent'      => $this->order->get_customer_user_agent(),
            'customer_localtime'       => $_REQUEST['customer_localtime'] ?? '',
            'customer_screen_size'     => $_REQUEST['customer_screen_size'] ?? ''
        ];

        //$this->order->get_customer_user_agent();

        $country                    = $this->order->get_billing_country() ?? $this->order->get_shipping_country();
        $state                      = $this->order->get_billing_state() ?? $this->order->get_shipping_state();

        $address                    = $this->order->get_billing_address_1();

        if(!empty($this->order->get_billing_address_2()))
        {
            $address                .= ' '.$this->order->get_billing_address_2();
        }

        $billing_address            =
        [
            'country'               => $country,
            'city'                  => $this->order->get_billing_city(),
            'state'                 => RegionFinder::hasStates($country) ? $state : '',
            'first_line'            => $address,
            'zip_code'              => $this->order->get_billing_postcode() ?? $this->order->get_shipping_postcode()
        ];

        if(!empty($this->order->get_billing_phone()))
        {
            $billing_address['phone'] = $this->order->get_billing_phone();
        }

        $data                       =
        [
            'client_id'             => $this->order_id,
            'description'           => $this->generate_order_description(),
            //'merchant_data'         => $this->generate_merchant_data(),
            'amount'                => $this->define_order_total(),
            'currency'              => $this->define_order_currency(),
            'customer'              =>  new Customer($customer_data),
            'billing_address'       =>  new BillingAddress($billing_address)
        ];

        if($this->is_inline_integration())
        {
            $data['credit_card']    = new CreditCard($this->define_credit_card());
        }

        $this->setPayment(new Payment($data));
    }


    /**
     * @return string
     */
    protected function define_order_total()
    {
        $total                      = $this->order->get_total();

        $total                      = number_format($total, 2, '.', '');

        return $total;
    }

    /**
     * @return string
     */
    protected function define_order_currency()
    {
        return strtoupper($this->order->get_currency());
    }

    /**
     * @return string
     */
    protected function define_ip_address()
    {
        return $this->order->get_customer_ip_address();
    }

    /**
     * @return string
     */
    protected function define_birthday()
    {
        return $this->order->get_user()->user_birthday ?? '';
    }

    /**
     * Define credit card data
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function define_credit_card()
    {
        $properties                 =
        [
            'card_printed_name',
            'credit_card_number',
            'expire_month',
            'expire_year',
            'cvv2'
        ];

        $data                       = [];

        foreach ($properties as $property)
        {
            if(empty($_REQUEST[$property]))
            {
                throw new \Exception('Error: '.$property.' is undefined');
            }

            $data[$property]        = trim($_REQUEST[$property]);
        }

        if(strlen($data['expire_year']) === 4)
        {
            $data['expire_year']    = substr($data['expire_year'], 2, 2);
        }

        return $data;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function generate_order_description()
    {
        $order_items                = $this->order->get_items(['line_item', 'fee', 'shipping']);

        if(is_wp_error($order_items))
        {
            throw new \Exception('error');
        }

        $result                     = [];

        foreach($order_items as $item)
        {
            if($item instanceof \WC_Order_Item_Product)
            {
                $id                 = $item->get_variation_id();

                if(empty($id))
                {
                    $id             = $item->get_product_id();
                }

                // template for info
                // #item_id item_name (quantity): price
                $result[]               = "#$id {$item->get_name()} ({$item->get_quantity()}): {$item->get_total()}";
            }
            else if ($item instanceof \WC_Order_Item_Fee)
            {
                // template for info
                // type: name = amount
                $result[]               = "{$item->get_type()}: {$item->get_name()}, amount: {$item->get_amount()}, total: {$item->get_total()}, tax: {$item->get_total_tax()}";
            }
            else if ($item instanceof \WC_Order_Item_Coupon)
            {
                // template for info
                // type: name = amount
                $result[]               = "{$item->get_type()}: {$item->get_code()}, discount: {$item->get_discount()}, tax: {$item->get_discount_tax()}";
            }
        }

        $result                     = implode("\n", $result);

        return $result;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function generate_merchant_data()
    {
        $order_items                = $this->order->get_items(['line_item', 'fee', 'shipping']);

        if(is_wp_error($order_items))
        {
            throw new \Exception('error');
        }

        $result                     = [];

        foreach($order_items as $item)
        {
            if($item instanceof \WC_Order_Item_Product)
            {
                $id                 = $item->get_variation_id();

                if(empty($id))
                {
                    $id             = $item->get_product_id();
                }

                $result[]               =
                [
                    'type'          => $item->get_type(),
                    'id'            => $id,
                    'name'          => $item->get_name(),
                    'quantity'      => $item->get_quantity()
                ];
            }
            else if ($item instanceof \WC_Order_Item_Fee)
            {
                $result[]               =
                [
                    'type'              => $item->get_type(),
                    'name'              => $item->get_name(),
                    'amount'            => $item->get_amount(),
                    'total'             => $item->get_total(),
                    'tax'               => $item->get_total_tax()
                ];
            }
            else if ($item instanceof \WC_Order_Item_Coupon)
            {
                $result[]               =
                [
                    'type'              => $item->get_type(),
                    'name'              => $item->get_name(),
                    'code'              => $item->get_code(),
                    'amount'            => $item->get_discount(),
                    'tax'               => $item->get_discount_tax()
                ];
            }
            else
            {
                $result[]               = "{$item->get_name()}: {$item->get_name()}";
                $result[]               =
                [
                    'type'              => $item->get_type(),
                    'name'              => $item->get_name()
                ];
            }
        }

        $result                     = json_encode($result, JSON_PRETTY_PRINT);

        return $result;
    }

    protected function load_data()
    {
        global $wpdb;

        if(empty($this->transaction_id))
        {
            // This is a new transaction - nothing to do
            return;
        }

        $query                      = "SELECT * FROM {$this->table} WHERE transaction_id = {$this->transaction_id}";
        $result                     = $wpdb->get_row($query, ARRAY_A);

        if(empty($result))
        {
            throw new \Exception("Transaction id {$this->transaction_id} is not found");
        }

        $this->order_id             = $result['order_id'];
        $this->status               = $result['status'];
        $this->state                = $result['state'];
        $this->html                 = $result['html'];
        $this->integration_method   = $result['integration_method'];
        $this->transaction_type     = $result['transaction_type'];

        // restore payment data
        if(!empty($result['paynet_order_id']))
        {
            $this->setPayment
            (
                new Payment(['client_id' => $this->order_id, 'paynet_id' => $result['paynet_order_id']])
            );
        }

        // serialize errors
        $errors                     = $result['errors'];

        if(!empty($errors))
        {
            $errors                 = unserialize($errors);

            if(is_array($errors))
            {
                $this->errors       = $errors;
            }
        }
    }

    protected function save_data()
    {
        global $wpdb;

        $data                       =
        [
            'transaction_id'        => $this->transaction_id ?? 0,
            'order_id'              => $this->order_id,
            'mode'                  => $this->queryConfig->getGatewayMode(),
            'operation'             => $this->define_operation(),
            'integration_method'    => $this->integration_method,
            'transaction_type'      => $this->transaction_type,
            'payment_method'        => $this->define_payment_method(),
            'state'                 => $this->state,
            'status'                => $this->status,
            'html'                  => '',
            'errors'                => ''
        ];

        $payment                    = $this->getPayment();

        if($payment instanceof Payment)
        {
            $data['paynet_order_id'] = $payment->getPaynetId();
        }

        if(is_array($this->errors) && count($this->errors) > 0)
        {
            $data['errors']         = serialize($this->errors);
        }

        // save html if exists
        if($this->response instanceof Response && $this->response->getNeededAction() === Response::NEEDED_SHOW_HTML)
        {
            $data['html']           = $this->response->getHtml();
        }

        if($this->transaction_id !== null)
        {
            $data['date_update']    = date('Y-m-d H:i:s');

            if(false === $wpdb->update($this->table, $data, ['transaction_id' => $this->transaction_id]))
            {
                throw new \Exception('Error while transaction create');
            }
        }
        else
        {
            $data                   = array_merge($data,
            [
                'login'                 => $this->queryConfig->getLogin(),
                'end_point'             => $this->queryConfig->getEndPoint(),
                'end_point_group'       => $this->queryConfig->getEndPointGroup(),
                'gateway_url'           => $this->queryConfig->getGatewayUrl()
            ]);

            $data['date_create']    = date('Y-m-d H:i:s');

            if(!$wpdb->insert($this->table, $data))
            {
                throw new \Exception('Error while transaction create');
            }

            $this->transaction_id   = $wpdb->insert_id;
        }
    }

    /**
     * Try to find the transaction with order id
     *
     * @param       string          $order_id
     * @return      string|null
     */
    protected function find_processing_transaction(string $order_id)
    {
        global $wpdb;

        $query                      = "SELECT * FROM {$this->table} 
                                    WHERE order_id = {$order_id} AND `state` IN ('processing')";

        $result                     = $wpdb->get_row($query, ARRAY_A);

        if(empty($result))
        {
            return null;
        }

        return $result['transaction_id'];
    }

    /**
     *
     *
     * @param       string      $message
     * @param       string      $level
     * @return  $this
     */
    protected function log($message, $level = \WC_Log_Levels::NOTICE)
    {
        if($this->logger instanceof LoggerInterface)
        {
            $this->logger->log($message, $level);
        }

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