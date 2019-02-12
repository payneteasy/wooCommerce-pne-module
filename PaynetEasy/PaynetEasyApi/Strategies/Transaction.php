<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

use PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction;
use PaynetEasy\PaynetEasyApi\Transport\Response;

/**
 * Class Transaction
 * @package PaynetEasy\PaynetEasyApi\Strategies
 */
abstract class Transaction          extends PaymentTransaction
{
    /**
     * State of handle
     */
    const STATE_NEW                 = 'new';
    const STATE_PROCESSING          = 'processing';
    const STATE_DONE                = 'done';
    
    /**
     * @var string
     */
    protected $orderId;
    /**
     * @var int
     */
    protected $transactionId;
    /**
     * @var string
     */
    protected $operation;
    /**
     * sale,reversal,capture,preauth
     *
     * @var string
     */
    protected $transactionType;
    /**
     * inline, form
     *
     * @var string
     */
    protected $integrationMethod;
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
     * Parent transaction, transaction linked with this.
     * Example: refund and sale or chargeback and sale.
     *
     * @var Transaction
     */
    protected $parentTransaction;
    
    /**
     * @var LoggerInterface
     */
    protected $logger;
    
    abstract protected function findTransactionByOrderId($orderId);
    abstract protected function load();
    abstract protected function save();
    abstract protected function definePaymentData();
    
    /**
     * PaymentTransaction constructor.
     *
     * @param       string $transactionId
     * @param       string $orderId
     * @param       string $integrationMethod
     * @param       string $transactionType
     *
     * @throws      \Exception
     */
    public function __construct
    (
        $transactionId             = null,
        $orderId                   = null,
        $integrationMethod         = null,
        $transactionType           = self::SALE
    )
    {
        parent::__construct();
        
        $this->state                = self::STATE_NEW;
        $this->transactionId        = $transactionId;
        $this->orderId              = $orderId;
        $this->integrationMethod    = $integrationMethod;
        $this->transactionType      = $transactionType;
        $this->response             = null;
        
        // try to find exists transaction by order id
        if($this->transactionId === null && $orderId !== null)
        {
            $this->transactionId    = $this->findTransactionByOrderId($orderId);
        }
        
        if($this->transactionId !== null)
        {
            $this->load();
        }
        
        $this->definePaymentData();
    }
    
}