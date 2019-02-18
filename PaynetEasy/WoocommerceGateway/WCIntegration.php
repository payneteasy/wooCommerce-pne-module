<?php
namespace PaynetEasy\WoocommerceGateway;

use PaynetEasy\PaynetEasyApi\PaymentData\BillingAddress;
use PaynetEasy\PaynetEasyApi\PaymentData\CreditCard;
use PaynetEasy\PaynetEasyApi\PaymentData\Customer;
use PaynetEasy\PaynetEasyApi\PaymentData\Payment;
use PaynetEasy\PaynetEasyApi\Strategies\IntegrationInterface;
use PaynetEasy\PaynetEasyApi\Strategies\Transaction;
use PaynetEasy\PaynetEasyApi\Transport\Response;
use PaynetEasy\PaynetEasyApi\Util\RegionFinder;

/**
 * Class WCIntegration
 * @package PaynetEasy\WoocommerceGateway
 */
class WCIntegration                 implements IntegrationInterface
{
    const DATABASE_TABLE            = 'payneteasy_transactions';
    
    protected $table                = '';
    
    public function __construct()
    {
        global $wpdb;
        $this->table                = $wpdb->prefix.self::DATABASE_TABLE;
    }
    
    /**
     * @return Transaction
     *
     * @throws \Exception
     */
    public function newTransaction()
    {
        $transaction                = new Transaction($this);
        $transaction->setTransactionType(Transaction::SALE);
        $transaction->setIntegrationMethod(Transaction::METHOD_INLINE);
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
            $data['paynet_order_id'] = $payment->getPaynetId();
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
     *
     * @return Transaction|null
     * @throws \Exception
     */
    public function findTransactionByPaynetId($paynetId)
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
    
    public function notice($message)
    {
        return $GLOBALS['PaynetEasyWoocommerceGateway']->log($message, \WC_Log_Levels::NOTICE);
    }
    
    public function debug($message)
    {
        return $GLOBALS['PaynetEasyWoocommerceGateway']->log($message, \WC_Log_Levels::DEBUG);
    }
    
    public function error($message)
    {
        return $GLOBALS['PaynetEasyWoocommerceGateway']->log($message, \WC_Log_Levels::ERROR);
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
}