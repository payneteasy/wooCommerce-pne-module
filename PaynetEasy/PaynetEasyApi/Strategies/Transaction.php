<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

use PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction;
use PaynetEasy\PaynetEasyApi\Transport\Response;

/**
 * Class Transaction
 * @package PaynetEasy\PaynetEasyApi\Strategies
 */
class Transaction          extends PaymentTransaction
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
     * @var bool
     */
    protected $isStop = false;
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
     * @var IntegrationInterface
     */
    protected $integrator;

    /**
     * additional transaction data that the server sent in the callback.
     * when the transaction was successful (or not successful) completed.
     *
     * @var array
     */
    protected $callbackData              = [];
    
    /**
     * Transaction constructor.
     *
     * @param   IntegrationInterface      $integrator
     */
    public function __construct(IntegrationInterface $integrator)
    {
        parent::__construct();
    
        $this->integrator           = $integrator;
        
        $this->state                = self::STATE_NEW;
        $this->response             = null;
    }
    
    public function getTransactionId()
    {
        return $this->transactionId;
    }
    
    public function setTransactionId($transactionId)
    {
        $this->transactionId        = $transactionId;
        
        return $this;
    }
    
    /**
     * Returns TRUE if REVERSAL or CHARGEBACK
     *
     * @return bool
     */
    public function isReversal()
    {
        return in_array($this->getTransactionType(), [self::REVERSAL, self::CHARGEBACK]);
    }

    public function stop()
    {
        $this->isStop               = true;
        return $this;
    }

    public function isStop()
    {
        return $this->isStop;
    }

    /**
     * @return string
     */
    public function getOrderId()
    {
        return $this->orderId;
    }
    
    public function setOrderId($orderId)
    {
        $this->orderId              = $orderId;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getOperation()
    {
        return $this->operation;
    }
    
    public function setOperation($operation)
    {
        $this->operation            = $operation;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getTransactionType()
    {
        return $this->transactionType;
    }
    
    public function setTransactionType($transactionType)
    {
        $this->transactionType      = $transactionType;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getIntegrationMethod()
    {
        return $this->integrationMethod;
    }
    
    public function setIntegrationMethod($integrationMethod)
    {
        $this->integrationMethod    = $integrationMethod;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }
    
    /**
     * @param string $state
     *
     * @return $this
     */
    public function setState($state)
    {
        $this->state                = $state;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }
    
    public function setHtml($html)
    {
        $this->html                 = $html;
        
        return $this;
    }
    
    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     * @param   Response $response
     *
     * @return  $this
     */
    public function setResponse(Response $response)
    {
        $this->response             = $response;
        
        return $this;
    }
    
    /**
     * @return Transaction
     */
    public function getParentTransaction()
    {
        return $this->parentTransaction;
    }
    
    /**
     * Returns TRUE is inline method integration
     *
     * @return bool
     */
    public function isInlineIntegration()
    {
        return $this->integrationMethod === self::METHOD_INLINE;
    }
    
    public function definePaymentMethod()
    {
        if($this->isInlineIntegration())
        {
            return $this->getTransactionType();
        }
        
        return $this->getTransactionType().'-'.$this->getIntegrationMethod();
    }
    
    public function setErrors($errors)
    {
        if(is_array($errors))
        {
            $this->errors           = $errors;
        }
        else
        {
            $this->errors           = [$errors];
        }
        
        return $this;
    }
    
    /**
     * @return bool
     */
    public function isRedirect()
    {
        return $this->response !== null && $this->response->getNeededAction() === Response::NEEDED_REDIRECT;
    }
    
    /**
     * @return string|null
     */
    public function getRedirectUrl()
    {
        if($this->isRedirect())
        {
            return $this->response->getRedirectUrl();
        }
        
        return null;
    }

    /**
     * @return array
     */
    public function getCallbackData(): array
    {
        if(!is_array($this->callbackData))
        {
            return [];
        }

        return $this->callbackData;
    }

    /**
     * @param array $callbackData
     * @return Transaction
     */
    public function setCallbackData(array $callbackData): Transaction
    {
        $this->callbackData         = $callbackData;

        return $this;
    }
}