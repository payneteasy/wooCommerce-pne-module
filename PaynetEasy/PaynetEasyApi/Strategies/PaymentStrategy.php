<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

use PaynetEasy\PaynetEasyApi\Exception\PaynetIdUndefined;
use PaynetEasy\PaynetEasyApi\Exception\ResponseException;
use PaynetEasy\PaynetEasyApi\Exception\TransactionNotFound;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;
use PaynetEasy\PaynetEasyApi\Transport\Response;

/**
 * Class PaymentStrategy
 * @package PaynetEasy\PaynetEasyApi\Strategies
 */
abstract class PaymentStrategy
{
    const ACTION_REDIRECT           = 'redirect';
    const ACTION_CALLBACK           = 'callback';
    const PAYNET_PARAMETER          = 'action';
    
    /**
     * @var string
     */
    protected $action;
    /**
     * @var Transaction
     */
    protected $transaction;
    /**
     * @var CallbackResponse
     */
    protected $callback;
    /**
     * @var string
     */
    protected $orderId;
    
    public function __construct()
    {
    
    }
    
    public function getPaymentUrlParameter($action)
    {
        return [self::PAYNET_PARAMETER => $action];
    }
    
    public function getPaymentParameter()
    {
        if(empty($_REQUEST[self::PAYNET_PARAMETER]))
        {
            return null;
        }
        
        return $_REQUEST[self::PAYNET_PARAMETER];
    }
    
    public function execute()
    {
        try
        {
            $this->detectPaynetCallback();
            $this->defineCurrentTransaction();
            $this->processing();
            $this->handleTransaction();
        }
        catch (\Exception $exception)
        {
            $this->handleException($exception);
            return;
        }
        
        $this->outResponse();
    }
    
    protected function detectPaynetCallback()
    {
        $this->action               = $this->getPaymentParameter();
        
        switch($this->action)
        {
            case self::ACTION_REDIRECT:
            {
                $this->callback     = new CallbackResponse($_REQUEST);
                break;
            }
            case self::ACTION_CALLBACK:
            {
                $this->callback     = new CallbackResponse($_REQUEST);
                break;
            }
        }
    }
    
    /**
     * @throws PaynetIdUndefined
     * @throws TransactionNotFound
     */
    protected function defineCurrentTransaction()
    {
        if($this->callback instanceof CallbackResponse)
        {
            $paynetId               = $this->callback->getPaymentPaynetId();
            
            if(empty($paynetId))
            {
                throw new PaynetIdUndefined($paynetId);
            }
            
            $transactionId          = $this->findTransactionByPaynetId($paynetId);
            
            if(empty($transactionId))
            {
                throw new TransactionNotFound($paynetId);
            }
            
            $this->initTransaction($transactionId);
            
            return;
        }
        
        $this->initTransaction();
    }
    
    protected function processing()
    {
    
    }
    
    protected function handleTransaction()
    {
    
    }
    
    protected function outResponse()
    {
    
    }
    
    protected function handleException($exception)
    {
    
    }
    
    abstract protected function initTransaction($transactionId = null);
    abstract protected function findTransactionByPaynetId($paynetId);
}