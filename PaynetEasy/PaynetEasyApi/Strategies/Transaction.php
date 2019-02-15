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
     * @param       string $integrationMethod
     * @param       string $transactionType
     *
     * @throws      \Exception
     */
    public function __construct
    (
        $transactionId             = null,
        $integrationMethod         = null,
        $transactionType           = self::SALE
    )
    {
        parent::__construct();
        
        $this->state                = self::STATE_NEW;
        $this->transactionId        = $transactionId;
        $this->integrationMethod    = $integrationMethod;
        $this->transactionType      = $transactionType;
        $this->response             = null;
        
        if($this->transactionId !== null)
        {
            $this->load();
        }
        
        $this->definePaymentData();
    }
    
    public function getTransactionId()
    {
        return $this->transactionId;
    }
    
    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }
    
    /**
     * @return string
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }
    
    /**
     * @return string
     */
    public function getIntegrationMethod()
    {
        return $this->integrationMethod;
    }
    
    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }
    
    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }
    
    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * @return Transaction
     */
    public function getParentTransaction()
    {
        return $this->parentTransaction;
    }
}