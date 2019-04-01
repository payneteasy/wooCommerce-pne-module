<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\Exception\PaynetException;
use PaynetEasy\PaynetEasyApi\PaymentData\BillingAddress;
use PaynetEasy\PaynetEasyApi\PaymentData\CreditCard;
use PaynetEasy\PaynetEasyApi\PaymentData\Customer;
use PaynetEasy\PaynetEasyApi\PaymentData\Payment;
use PaynetEasy\PaynetEasyApi\PaymentData\QueryConfig;
use PaynetEasy\PaynetEasyApi\Strategies\IntegrationInterface;
use PaynetEasy\PaynetEasyApi\Strategies\LoggerInterface;
use PaynetEasy\PaynetEasyApi\Strategies\PaymentStrategy;
use PaynetEasy\PaynetEasyApi\Strategies\Transaction;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;
use PaynetEasy\PaynetEasyApi\Transport\Response;
use PaynetEasy\PaynetEasyApi\Util\RegionFinder;
use PaynetEasy\PaynetEasyApi\Util\Validator;

/**
 * Class WCIntegration
 * @package PaynetEasy\WoocommerceGateway
 */
class WCIntegration                 implements IntegrationInterface
{
    const DATABASE_TABLE            = 'payneteasy_transactions';
    const TRANSACTION_ID            = 'payneteasy_transaction_id';
    
    protected $table                = '';
    
    protected $plugin_id;
    /**
     * @var array
     */
    protected $settings;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var PaymentStrategy
     */
    protected $payment_strategy;
    
    public function __construct($plugin_id, array $settings, LoggerInterface $logger)
    {
        global $wpdb;
        $this->table                = $wpdb->prefix.self::DATABASE_TABLE;
        
        $this->plugin_id            = $plugin_id;
        $this->settings             = $settings;
        $this->payment_strategy     = new PaymentStrategy($this);
        $this->logger               = $logger;
    }
    
    /**
     * @param array $data
     *
     * @return array|bool
     *
     * @throws \PaynetEasy\PaynetEasyApi\Exception\ValidationException
     */
    public function validateData(array $data)
    {
        // Checking data only for sale (credit card inline form)
        if($this->defineIntegrationMethod() !== Transaction::METHOD_INLINE)
        {
            return true;
        }
    
        // Check fields
        $required                   =
        [
            //'billing_phone'       => __('Phone', 'paynet-easy-gateway'),
            'card_printed_name'     => __('Printed name', 'paynet-easy-gateway'),
            'credit_card_number'    => __('Card number', 'paynet-easy-gateway'),
            'expire_month'          => __('Expire month', 'paynet-easy-gateway'),
            'expire_year'           => __('Expire year', 'paynet-easy-gateway'),
            'cvv2'                  => __('Cvv', 'paynet-easy-gateway')
        ];
        
        $rules                      =
        [
            'credit_card_number'    => Validator::CREDIT_CARD_NUMBER,
            'expire_month'          => Validator::MONTH,
            'expire_year'           => Validator::YEAR,
            'cvv2'                  => Validator::CVV2,
        ];
        
        $errors                     = [];
    
        foreach ($required as $name => $title)
        {
            if(empty($data[$name]) || empty(trim($data[$name])))
            {
                /* Translators: %s is field name */
                $errors[]           = sprintf( __( 'Required "%s" field', 'paynet-easy-gateway'), $title);
            }
        }
    
        foreach($rules as $name => $rule)
        {
            if(empty($data[$name]))
            {
                continue;
            }
    
            if(!Validator::validateByRule($data[$name], $rule, false))
            {
                /* Translators: %s is field name */
                $errors[]           = sprintf( __( 'The "%s" field is not valid', 'paynet-easy-gateway'), $required[$name]);
            }
        }
        
        return $errors;
    }
    
    /**
     * @param   string  $order_id
     *
     * @return Transaction
     */
    public function startPayment($order_id)
    {
        $this->debug($order_id.': Start processing payment');
        
        $this->payment_strategy->assignOrderId($order_id);
        $this->payment_strategy->execute();
        
        $transaction                = $this->payment_strategy->getTransaction();
        // save transaction id into session
        WC()->session->set(self::TRANSACTION_ID, $transaction->getTransactionId());
        
        return $transaction;
    }
    
    /**
     * @return Transaction
     * @throws \Exception
     */
    public function processPayment()
    {
        // restore transaction id
        $transaction_id             = WC()->session->get(self::TRANSACTION_ID);
        
        if(!empty($transaction_id))
        {
            $this->payment_strategy->assignTransaction($this->findTransactionById($transaction_id));
        }
        
        $this->payment_strategy->execute();
        return $this->payment_strategy->getTransaction();
    }
    
    public function processCallback()
    {
        $this->notice('Detect callback link using');
        
        try
        {
            $this->payment_strategy->execute();
    
            $response                   = $this->payment_strategy->getResponse();
            $status                     = $this->translateStatus($response->getStatus());
            $order_id                   = $this->payment_strategy->getTransaction()->getOrderId();
            $paynet_order_id            = $this->payment_strategy->getTransaction()->getResponse()->getPaymentPaynetId();
    
            // notice
            wc_get_order($order_id)->add_order_note
            (
                __('CALLBACK has been received with status', 'paynet-easy-gateway').
                ': '.$status.
                ' (paynet id = '.$paynet_order_id.')'
            );
        }
        catch (\Exception $exception)
        {
            $transaction                = $this->payment_strategy->getTransaction();
            
            if($transaction === null || empty($transaction->getOrderId()))
            {
                $this->error($exception->getMessage());
                return -500;
            }
    
            $order                      = wc_get_order($transaction->getOrderId());
            
            if(empty($order))
            {
                return -501;
            }
            
            $order->add_order_note
            (
                __('CALLBACK error: ', 'paynet-easy-gateway').$exception->getMessage()
            );
            
            return -2;
        }
        
        return 0;
    }
    
    /**
     * @return Transaction
     * @throws \Exception
     */
    public function processRedirect()
    {
        $this->notice('Detect redirect link using');
    
        try
        {
            $this->payment_strategy->execute();
        
            $response                   = $this->payment_strategy->getResponse();
            $status                     = $this->translateStatus($response->getStatus());
            $order_id                   = $this->payment_strategy->getTransaction()->getOrderId();
            $paynet_order_id            = $this->payment_strategy->getTransaction()->getResponse()->getPaymentPaynetId();
        
            // notice
            wc_get_order($order_id)->add_order_note
            (
                __('REDIRECT has been received with status', 'paynet-easy-gateway').
                ': '.$status.
                ' (paynet id = '.$paynet_order_id.')'
            );
        }
        catch (\Exception $exception)
        {
            $transaction                = $this->payment_strategy->getTransaction();
        
            if($transaction === null || empty($transaction->getOrderId()))
            {
                $this->error($exception->getMessage());
                throw $exception;
            }
        
            $order                      = wc_get_order($transaction->getOrderId());
        
            if(empty($order))
            {
                throw $exception;
            }
        
            $order->add_order_note
            (
                __('CALLBACK error: ', 'paynet-easy-gateway').$exception->getMessage()
            );
        
            throw $exception;
        }
    
        return $this->payment_strategy->getTransaction();
    }
    
    /**
     * Start refund process
     *
     * @param   int       $order_id
     * @param   float     $amount
     * @param   string    $reason
     *
     * @return Transaction
     *
     * @throws \PaynetEasy\PaynetEasyApi\Exception\PaynetException
     * @throws \Exception
     */
    public function startRefund($order_id, $amount = null, $reason = '')
    {
        // 1. Find sale transaction with status approved
        $sale_transaction           = $this->findTransactionByOrderId($order_id, ['status' => Transaction::STATUS_APPROVED]);
        
        if($sale_transaction === null)
        {
            throw new PaynetException('I can not find a approved payment transaction to make a refund');
        }
        
        $this->payment_strategy->assignOrderId($order_id);
    
        // Create reversal transaction and define data
        $transaction                = $this->payment_strategy->createReversalTransaction();
        
        $order                      = wc_get_order($order_id);
        
        $data                       =
        [
            'client_id'             => $transaction->getOrderId(),
            // Paynet order id
            'orderid'               => $sale_transaction->getPayment()->getPaynetId(),
            'comment'               => $reason,
            'amount'                => $amount ?? $this->defineOrderTotal($order),
            'currency'              => strtoupper($order->get_currency())
        ];
        
        $transaction->setPayment(new Payment($data));
        
        // Execute query
        $this->payment_strategy->execute();
        return $this->payment_strategy->getTransaction();
    }
    
    /**
     * @param int $order_id
     * @param bool $is_define_data
     *
     * @return Transaction
     *
     * @throws \Exception
     */
    public function newTransaction($order_id = null, $is_define_data = true)
    {
        $transaction                = new Transaction($this);
        $transaction->setTransactionType(Transaction::SALE);
        $transaction->setOperation(Transaction::SALE);
        $transaction->setIntegrationMethod($this->defineIntegrationMethod($order_id));
        $transaction->setOrderId($order_id);
    
        $transaction->setQueryConfig($this->getQueryConfig());
        
        if($is_define_data)
        {
        
        }
        $this->definePaymentData($transaction);
        
        return $transaction;
    }
    
    /**
     * @param   Transaction     $transaction
     * @param   array           $result
     *
     * @return  Transaction
     *
     * @throws \Exception
     */
    protected function initTransaction(Transaction $transaction, array $result)
    {
        $transaction->setQueryConfig($this->getQueryConfig());
        
        $transaction->setTransactionId($result['transaction_id'] ?? null);
        $transaction->setOrderId($result['order_id'] ?? null);
        $transaction->setStatus($result['status']);
        $transaction->setState($result['state']);
        $transaction->setIntegrationMethod($result['integration_method']);
        $transaction->setTransactionType($result['transaction_type']);
        $transaction->setHtml($result['html'] ?? '');
        
        // restore payment data
        if(!empty($result['paynet_order_id']))
        {
            $transaction->setPayment
            (
                new Payment(['client_id' => $result['order_id'], 'paynet_id' => $result['paynet_order_id']])
            );
        }
    
        // serialize errors
        $errors                     = $result['errors'];
    
        if(!empty($errors))
        {
            $errors                 = unserialize($errors);
        
            if(is_array($errors))
            {
                $transaction->setErrors($errors);
            }
        }
        
        return $transaction;
    }
    
    /**
     * @param Transaction $transaction
     *
     * @throws \Exception
     */
    public function saveTransaction(Transaction $transaction)
    {
        global $wpdb;
    
        $data                       =
        [
            'transaction_id'        => $transaction->getTransactionId() ?? 0,
            'order_id'              => $transaction->getOrderId(),
            'mode'                  => $transaction->getQueryConfig()->getGatewayMode(),
            'operation'             => $transaction->getOperation(),
            'integration_method'    => $transaction->getIntegrationMethod(),
            'transaction_type'      => $transaction->getTransactionType(),
            'payment_method'        => $transaction->definePaymentMethod(),
            'state'                 => $transaction->getState(),
            'status'                => $transaction->getStatus(),
            'html'                  => '',
            'errors'                => ''
        ];
    
        $payment                    = $transaction->getPayment();
    
        if($payment instanceof Payment)
        {
            $data['paynet_order_id'] = $payment->getPaynetId() ?? 0;
        }
    
        $errors                     = $transaction->getErrors();
        
        if(is_array($errors) && count($errors) > 0)
        {
            $data['errors']         = serialize($errors);
        }
    
        $response                   = $transaction->getResponse();
        
        // save html if exists
        if($response instanceof Response && $response->getNeededAction() === Response::NEEDED_SHOW_HTML)
        {
            $data['html']           = $response->getHtml();
        }
    
        if($transaction->getTransactionId() !== null)
        {
            $data['date_update']    = date('Y-m-d H:i:s');
        
            if(false === $wpdb->update($this->table, $data, ['transaction_id' => $transaction->getTransactionId()]))
            {
                throw new \Exception('Error while transaction create');
            }
        }
        else
        {
            $queryConfig            = $transaction->getQueryConfig();
            
            $data                   = array_merge($data,
            [
                'login'             => $queryConfig->getLogin(),
                'end_point'         => $queryConfig->getEndPoint(),
                'end_point_group'   => $queryConfig->getEndPointGroup(),
                'gateway_url'       => $queryConfig->getGatewayUrl()
            ]);
        
            $data['date_create']    = date('Y-m-d H:i:s');
        
            if(!$wpdb->insert($this->table, $data))
            {
                throw new \Exception('Error while transaction create');
            }
        
            $transaction->setTransactionId($wpdb->insert_id);
        }
    }
    
    /**
     * @param Transaction $transaction
     *
     * @throws \Exception
     */
    public function definePaymentData(Transaction $transaction)
    {
        if($transaction->getTransactionId() !== null)
        {
            return;
        }
    
        $order                      = wc_get_order($transaction->getOrderId());
        
        // Payment data for REVERSAL
        if($transaction->getTransactionType() === Transaction::REVERSAL)
        {
            return;
        }
        
        $customer_data              =
        [
            'first_name'            => $order->get_billing_first_name() ?? $order->get_shipping_first_name(),
            'last_name'             => $order->get_billing_last_name() ?? $order->get_shipping_last_name(),
            'email'                 => $order->get_billing_email() ?? $order->get_user()->user_email,
            'ip_address'            => $order->get_customer_ip_address(),
            'birthday'              => $order->get_user()->user_birthday ?? '',
            // additional data
            'customer_accept_language' => $_REQUEST['customer_accept_language'] ?? '',
            'customer_user_agent'      => $order->get_customer_user_agent(),
            'customer_localtime'       => $_REQUEST['customer_localtime'] ?? '',
            'customer_screen_size'     => $_REQUEST['customer_screen_size'] ?? ''
        ];
    
        //$order->get_customer_user_agent();
    
        $country                    = $order->get_billing_country() ?? $order->get_shipping_country();
        $state                      = $order->get_billing_state() ?? $order->get_shipping_state();
    
        $address                    = $order->get_billing_address_1();
    
        if(!empty($order->get_billing_address_2()))
        {
            $address                .= ' '.$order->get_billing_address_2();
        }
    
        $billing_address            =
        [
            'country'               => $country,
            'city'                  => $order->get_billing_city(),
            'state'                 => RegionFinder::hasStates($country) ? $state : '',
            'first_line'            => $address,
            'zip_code'              => $order->get_billing_postcode() ?? $order->get_shipping_postcode()
        ];
    
        if(!empty($order->get_billing_phone()))
        {
            $billing_address['phone'] = $order->get_billing_phone();
        }
    
        $data                       =
        [
            'client_id'             => $transaction->getOrderId(),
            'description'           => $this->generateOrderDescription($order),
            'amount'                => $this->defineOrderTotal($order),
            'currency'              => strtoupper($order->get_currency()),
            'customer'              =>  new Customer($customer_data),
            'billing_address'       =>  new BillingAddress($billing_address)
        ];
    
        if($transaction->isInlineIntegration())
        {
            $data['credit_card']    = new CreditCard($this->defineCreditCard());
        }
    
        $transaction->setPayment(new Payment($data));
    }
    
    /**
     * Find transaction by paynet id
     *
     * @param string $paynetId
     * @param array $filters
     *
     * @return Transaction|null
     * @throws \Exception
     */
    public function findTransactionByPaynetId($paynetId, array $filters = [])
    {
        global $wpdb;
    
        $paynetId                   = esc_sql($paynetId);
    
        $query                      = "SELECT * FROM {$this->table} WHERE paynet_order_id = '{$paynetId}'";
    
        $result                     = $wpdb->get_row($query, ARRAY_A);
    
        if(empty($result))
        {
            return null;
        }
        
        return $this->initTransaction(new Transaction($this), $result);
    }
    
    /**
     * @param string $transactionId
     *
     * @return Transaction
     * @throws \Exception
     */
    public function findTransactionById($transactionId)
    {
        global $wpdb;
    
        if(empty($transactionId))
        {
            throw new \Exception("Transaction id {$transactionId} is not found");
        }
    
        $transactionId              = (float) $transactionId;
    
        $query                      = "SELECT * FROM {$this->table} WHERE transaction_id = {$transactionId}";
        $result                     = $wpdb->get_row($query, ARRAY_A);
    
        if(empty($result))
        {
            throw new \Exception("Transaction id {$transactionId} is not found");
        }
        
        return $this->initTransaction(new Transaction($this), $result);
    }
    
    /**
     * Find transaction by order id
     *
     * @param string $orderId
     * @param array $filters
     *
     * @return Transaction|null
     * @throws \Exception
     */
    public function findTransactionByOrderId($orderId, array $filters = [])
    {
        global $wpdb;
        
        $orderId                    = esc_sql($orderId);
    
        if(empty($filters))
        {
            $filters                = ['state' => [Transaction::STATE_NEW, Transaction::STATE_PROCESSING]];
        }
        
        $query                      = "SELECT * FROM {$this->table} WHERE order_id = '{$orderId}' AND "
                                    .$this->filtersToSql($filters);
        
        $result                     = $wpdb->get_row($query, ARRAY_A);
        
        if(empty($result))
        {
            return null;
        }
        
        return $this->initTransaction(new Transaction($this), $result);
    }
    
    public function onError(Transaction $transaction)
    {
        $order_id                   = $transaction->getOrderId();
        
        if(empty($order_id))
        {
            return;
        }
        
        $order                      = wc_get_order($order_id);
        
        if(empty($order))
        {
            return;
        }
        
        $transaction_id             = $transaction->getTransactionId();
        
        $paynet_id                  = '';
        
        if($transaction->getResponse() !== null)
        {
            $paynet_id              = $transaction->getResponse()->getPaymentPaynetId();
        }
    
        $errors                     = [];
    
        foreach ($transaction->getErrors() as $error)
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
    
        $order->add_order_note
        (
            __('Payment declined', 'paynet-easy-gateway')
            . " (transaction_id = $transaction_id, paynet_id = $paynet_id) errors:\n$errors"
        );
        
        $order->update_status('failed', __('Payment declined', 'paynet-easy-gateway'));
    }
    
    public function onApprove(Transaction $transaction)
    {
        $order_id                   = $transaction->getOrderId();
    
        if(empty($order_id))
        {
            return;
        }
    
        $order                      = wc_get_order($order_id);
    
        if(empty($order))
        {
            return;
        }
    
        $transaction_id             = $transaction->getTransactionId();
        $paynet_id                  = $transaction->getResponse()->getPaymentPaynetId();
    
        $order->add_order_note(__('Payment approved', 'paynet-easy-gateway')." (paynet id = $paynet_id)");
        $order->payment_complete($transaction_id);
    }
    
    public function onProcess(Transaction $transaction)
    {
        $order_id                   = $transaction->getOrderId();
    
        if(empty($order_id))
        {
            return;
        }
    
        $order                      = wc_get_order($order_id);
    
        if(empty($order) || $transaction->getResponse() === null)
        {
            return;
        }
        
        $action                     = $transaction->getResponse()->getNeededAction();
    
        if(empty($action) || $action === Response::NEEDED_STATUS_UPDATE)
        {
            /* Translators: It's status for order notes */
            $order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': wait');
        }
        elseif($action === Response::NEEDED_SHOW_HTML)
        {
            $order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': show html');
        }
        elseif($action === Response::NEEDED_REDIRECT)
        {
            $order->update_status('on-hold', __('Payment processing', 'paynet-easy-gateway').': redirect');
        }
    }
    
    public function onException(\Exception $exception)
    {
        wc_add_notice(__('Error during payment processing', 'paynet-easy-gateway'), 'error');
        
        // Try get order
        $transaction                = $this->payment_strategy->getTransaction();
        
        if(empty($transaction))
        {
            return;
        }

        $this->onError($transaction);
    }
    
    public function createRefundForCallback(CallbackResponse $response)
    {
        /* @todo Crete refund for callback */
    }
    
    /**
     * @param Transaction $transaction
     *
     * @throws \Exception
     */
    public function onReversal(Transaction $transaction)
    {
        $order_id                   = $transaction->getOrderId();
        
        $order                      = wc_get_order($order_id);
        
        if(empty($order))
        {
            $this->error("Transaction Reversal detected with wrong order id $order_id (transaction id = {$transaction->getTransactionId()})");
            $transaction->setErrors(new \Exception('Transaction Reversal detected with wrong order id'));
            $transaction->setState(Transaction::STATE_DONE);
            $transaction->setStatus(Transaction::STATUS_ERROR);
            $this->saveTransaction($transaction);
        }
    
        if($transaction->getTransactionType() === Transaction::CHARGEBACK)
        {
            $order->update_status('refunded', __('Payment chargeback', 'paynet-easy-gateway'));
        }
        else
        {
            $order->update_status('refunded', __('Payment reversal', 'paynet-easy-gateway'));
        }

        /*
        
        $refund_options             =
        [
            //  The amount to be refunded
            'amount'                => '',
            // Reason of the refund
            'reason'                => '',
            // ID of the order we want to refund
            'order_id'              => '',
            //  ID of the refund we want to use again and retry
            // The array of line items will contain various information.
            // Each array item will have the item’s ID as the array key.
            //
            // Each item that has been assigned to the array by the key, will have:
            //
            // * qty – Quantity
            // * refund_total – Total amount to be refunded for that item
            // * refund_tax – Tax to be refunded
            //
            'line_items'            => [],
            // Boolean. If true, the refund process will also try to refund the payment through the payment gateway
            'refund_payment'        => false,
            // If true, it will restock the items back by the quantity of each line item that we have refunded
            'restock_items'         => false
        ];
        
        wc_create_refund($refund_options);
        */
    }
    
    public function notice($message)
    {
        return $this->logger->notice($message);
    }
    
    public function debug($message)
    {
        return $this->logger->debug($message);
    }
    
    public function error($message)
    {
        return $this->logger->error($message);
    }
    
    /**
     * Define credit card data
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function defineCreditCard()
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
     * @param \WC_Order $order
     *
     * @return string
     */
    protected function defineOrderTotal(\WC_Order $order)
    {
        $total                      = $order->get_total();
        
        $total                      = number_format($total, 2, '.', '');
        
        return $total;
    }
    
    /**
     * @param \WC_Order $order
     *
     * @return string
     * @throws \Exception
     */
    protected function generateOrderDescription(\WC_Order $order)
    {
        $order_items                = $order->get_items(['line_item', 'fee', 'shipping']);
        
        if(is_wp_error($order_items))
        {
            throw new \Exception('generateOrderDescription error');
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
     * @param \WC_Order $order
     *
     * @return string
     * @throws \Exception
     */
    protected function generateMerchantData(\WC_Order $order)
    {
        $order_items                = $order->get_items(['line_item', 'fee', 'shipping']);
        
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
    
    /**
     * Define a payment method: sale or form
     *
     * @param       string      $order_id
     * @return      string
     */
    public function defineIntegrationMethod($order_id = null)
    {
        if(!empty($this->settings['integration_method']))
        {
            return $this->settings['integration_method'];
        }
        
        // default payment method
        return 'sale';
    }
    
    /**
     * @return QueryConfig
     */
    public function getQueryConfig()
    {
        /**
         * Точка входа для аккаунта мерчанта, выдается при подключении
         */
        $end_point                  = $this->getEndPoint();
        $end_point_group            = $this->getEndPointGroup();
        
        $config                     =
            [
                /**
                 * Логин мерчанта, выдается при подключении
                 */
                'login'                 => $this->getPaynetLogin(),
                /**
                 * Ключ мерчанта для подписывания запросов, выдается при подключении
                 */
                'signing_key'           => $this->getMerchantControl(),
                /**
                 * URL на который пользователь будет перенаправлен после окончания запроса
                 */
                'redirect_url'          => $this->getRedirectUrl(),
                /**
                 * URL на который пользователь будет перенаправлен после окончания запроса
                 */
                'callback_url'          => $this->getCallbackUrl(),
                /**
                 * Режим работы библиотеки: sandbox, production
                 */
                'gateway_mode'          => $this->getGatewayMode(),
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
    public function getEndPoint()
    {
        $result                     = $this->isSandboxMode() ?
            $this->settings['sandbox_end_point'] : $this->settings['end_point'];
        
        return $result ?? $this->settings['end_point'];
    }
    
    /**
     * @return string
     */
    public function getEndPointGroup()
    {
        $result                     = $this->isSandboxMode() ?
            $this->settings['sandbox_end_point_group'] : $this->settings['end_point_group'];
        
        return $result ?? $this->settings['end_point_group'];
    }
    
    /**
     * @return string
     */
    public function getPaynetLogin()
    {
        $result                     = $this->isSandboxMode() ?
                                    $this->settings['sandbox_login'] : $this->settings['login'];
        
        return $result ?? $this->settings['login'];
    }
    
    /**
     * @return string
     */
    public function getMerchantControl()
    {
        $result                     = $this->isSandboxMode() ?
                                    $this->settings['sandbox_merchant_control'] : $this->settings['merchant_control'];
        
        return $result ?? $this->settings['merchant_control'];
    }
    
    public function getRedirectUrl()
    {
        $params                     = ['wc-api' => $this->plugin_id.'_redirect', 't' => time()];
        
        $params                     = array_merge
        (
            $params,
            $this->payment_strategy->getPaymentUrlParameter(PaymentStrategy::ACTION_REDIRECT)
        );
        
        return add_query_arg($params, home_url('/'));
    }
    
    public function getCallbackUrl()
    {
        $params                     = ['wc-api' => $this->plugin_id.'_callback', 't' => time()];
    
        $params                     = array_merge
        (
            $params,
            $this->payment_strategy->getPaymentUrlParameter(PaymentStrategy::ACTION_CALLBACK)
        );
        
        return add_query_arg($params, home_url('/'));
    }
    
    /**
     * @param   string      $transaction_id
     * @param   array       $ex_params
     * @return  string
     */
    public function getProcessPageUrl($transaction_id, array $ex_params = [])
    {
        $ex_params[PAYNET_EASY_PAGE]    = 1;
        // time
        $ex_params['t']                 = time();
        
        $ex_params                      = array_merge
        (
            $ex_params,
            $this->payment_strategy->getPaymentUrlParameter(PaymentStrategy::ACTION_STATUS)
        );
        
        return add_query_arg($ex_params, home_url('/'));
    }
    
    public function isSandboxMode()
    {
        return !empty($this->settings['test_mode']) && $this->settings['test_mode'] === 'yes';
    }
    
    public function getGatewayMode()
    {
        return $this->isSandboxMode() ? QueryConfig::GATEWAY_MODE_SANDBOX : QueryConfig::GATEWAY_MODE_PRODUCTION;
    }
    
    /**
     * @param string $status
     *
     * @return string
     */
    public function translateStatus($status)
    {
        switch ($status)
        {
            case 'approved':        return __('approved', 'paynet-easy-gateway');
            case 'declined':        return __('declined', 'paynet-easy-gateway');
            case 'error':           return __('error', 'paynet-easy-gateway');
            case 'filtered':        return __('filtered', 'paynet-easy-gateway');
            case 'processing':      return __('processing', 'paynet-easy-gateway');
            case 'unknown':         return __('unknown', 'paynet-easy-gateway');
            
            default: return $status;
        }
    }
    
    /**
     * @param array $filters
     *
     * @return string
     */
    protected function filtersToSql(array $filters)
    {
        if(empty($filters))
        {
            return '';
        }
        
        $sql                        = [];
        
        foreach ($filters as $field => $filter)
        {
            if(is_array($filter))
            {
                $values             = [];
                
                foreach ($filter as $item)
                {
                    $values[]       = "'".esc_sql($item)."'";
                }
                
                if(count($values) > 0)
                {
                    $sql[]          = "`$field` IN (".implode(',', $values).")";
                }
            }
            else
            {
                $filter             = esc_sql($filter);
                $sql[]              = "`$field` = '$filter'";
            }
        }
        
        if(empty($sql))
        {
            return '';
        }
        
        return implode(' AND ', $sql);
    }
    
}