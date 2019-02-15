<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

use PaynetEasy\PaynetEasyApi\Exception\PaynetIdUndefined;
use PaynetEasy\PaynetEasyApi\Exception\ResponseException;
use PaynetEasy\PaynetEasyApi\Exception\TransactionHasWrongState;
use PaynetEasy\PaynetEasyApi\Exception\TransactionNotFoundByOrderId;
use PaynetEasy\PaynetEasyApi\Exception\TransactionNotFoundByPaynetId;
use PaynetEasy\PaynetEasyApi\PaymentProcessor;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;
use PaynetEasy\PaynetEasyApi\Transport\Response;

/**
 * Class PaymentStrategy
 * @package PaynetEasy\PaynetEasyApi\Strategies
 */
class PaymentStrategy
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
    /**
     * @var Response
     */
    protected $response;
    
    /**
     * @var IntegrationInterface
     */
    protected $integration;
    
    public function __construct(IntegrationInterface $integration = null)
    {
        if($integration !== null)
        {
            $this->assignIntegration($integration);
        }
    }
    
    public function assignIntegration(IntegrationInterface $integration)
    {
        $this->integration          = $integration;
        
        return $this;
    }
    
    public function assignOrderId($orderId)
    {
        $this->orderId              = $orderId;
        
        return $this;
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
     * @throws TransactionNotFoundByOrderId
     * @throws TransactionNotFoundByPaynetId
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
            
            $this->transaction      = $this->integration->findTransactionByPaynetId($paynetId);
            
            if($this->transaction === null)
            {
                throw new TransactionNotFoundByPaynetId($paynetId);
            }
            
            return;
        }
        
        // restore transaction by order
        if($this->orderId !== null)
        {
            $this->transaction      = $this->integration->findTransactionByOrderId($this->orderId);
    
            if($this->transaction === null)
            {
                throw new TransactionNotFoundByOrderId($this->orderId);
            }
        }
        
        $this->transaction          = $this->integration->newTransaction();
    }
    
    /**
     * @throws TransactionHasWrongState
     */
    protected function processing()
    {
        $paymentProcessor           = $this->createPaymentProcessor();
    
        // check transaction inside callback
        if($this->callback instanceof CallbackResponse && $this->transaction->getState() === Transaction::STATE_NEW)
        {
            throw new TransactionHasWrongState
            (
                Transaction::STATE_NEW,
                'A transaction cannot have a NEW state while the callback from the server is handled'
            );
        }
    
        if($this->action === self::ACTION_CALLBACK)
        {
            $this->integration->debug($this->orderId.": Detect callback for transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->processPaynetEasyCallback($this->callback, $this->transaction);
        }
        elseif ($this->action === self::ACTION_REDIRECT)
        {
            $this->integration->debug($this->orderId.": Detect redirect for transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->processCustomerReturn($this->callback, $this->transaction);
        }
        elseif ($this->transaction->isProcessing())
        {
            $this->integration->debug($this->orderId.": Update status for transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->executeQuery('status', $this->transaction);
        }
        else
        {
            $this->integration->debug($this->orderId.": Start process transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->executeQuery($this->transaction->define_payment_method(), $this->transaction);
        }
    }
    
    protected function handleTransaction()
    {
        if($this->transaction->isDeclined() || $this->transaction->isError())
        {
            $this->handleError();
        }
        elseif($this->transaction->isApproved())
        {
            $this->handleApprove();
        }
        elseif($this->transaction->isProcessing())
        {
            $this->handleProcess();
        }
    }
    
    protected function handleError()
    {
    
    }
    
    protected function handleApprove()
    {
    
    }
    
    protected function outResponse()
    {
    
    }
    
    protected function handleException($exception)
    {
    
    }
    
    protected function createPaymentProcessor()
    {
        $handlers                   =
        [
            PaymentProcessor::HANDLER_SAVE_CHANGES => [$this, 'onSaveTransaction']
        ];
        
        $payment_processor          = new PaymentProcessor($handlers);
        
        return $payment_processor;
    }
}