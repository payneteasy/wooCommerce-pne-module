<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

use PaynetEasy\PaynetEasyApi\Exception\PaynetException;
use PaynetEasy\PaynetEasyApi\Exception\PaynetIdUndefined;
use PaynetEasy\PaynetEasyApi\Exception\TransactionHasWrongState;
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
    /**
     * TRUE if the transaction already had its result before this request
     *
     * @var bool
     */
    protected $isFinished = false;

    /**
     * Update current status
     */
    const ACTION_STATUS             = 'status';
    /**
     * Redirect from Paynet
     */
    const ACTION_REDIRECT           = 'redirect';
    /**
     * Callback from Paynet
     */
    const ACTION_CALLBACK           = 'callback';
    /**
     * Action parameter
     */
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
    
    public function __construct(?IntegrationInterface $integration = null)
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
    
    public function assignTransaction(Transaction $transaction)
    {
        $this->transaction          = $transaction;
        
        return $this;
    }
    
    public function assignOrderId($orderId)
    {
        $this->orderId              = $orderId;
        
        return $this;
    }
    
    public function getTransaction()
    {
        return $this->transaction;
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
    
    /**
     * TRUE if the transaction already had its result before this request
     *
     * @return bool
     */
    public function isFinished()
    {
        return $this->isFinished;
    }

    public function getResponse()
    {
        if($this->callback instanceof CallbackResponse)
        {
            return $this->callback;
        }
        
        return $this->response;
    }

    /**
     * @throws \Exception
     */
    public function execute()
    {
        try
        {
            $this->detectPaynetCallback();
            $this->defineCurrentTransaction();
            $this->processing();

            if($this->isFinished)
            {
                // The transaction already has its result: nothing changes
                $this->integration->debug($this->orderId.": Transaction {$this->transaction->getTransactionId()} is already done, order not changed");
            }
            elseif($this->transaction->isReversal())
            {
                $this->handleReversalTransaction();
            }
            else
            {
                $this->handleTransaction();
            }
        }
        catch (\Exception $exception)
        {
            $this->handleException($exception);
            // remove components
            $this->response         = null;
            $this->transaction      = null;

            throw $exception;
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
     * @throws TransactionNotFoundByPaynetId
     */
    protected function defineCurrentTransaction()
    {
        // For reversal
        if($this->callback instanceof CallbackResponse && $this->callback->isReversal())
        {
            $this->integration->debug("Detect reversal callback");
            
            // If current transaction has same type with callback
            // nothing to do
            if($this->transaction !== null && $this->transaction->getTransactionType() === $this->callback->getType())
            {
                return;
            }
    
            $paynetId               = $this->callback->getPaymentPaynetId();
            $orderId                = $this->callback->getPaymentClientId();
    
            $this->integration->debug("Detect reversal callback with paynet_id = $paynetId and order_id = $orderId");
    
            $this->transaction      = $this->integration->findTransactionByPaynetId($paynetId);
            
            if($this->transaction === null)
            {
                // create new transaction for reversal
                $this->transaction  = $this->integration->newTransaction($orderId);
                $this->transaction->setTransactionType($this->callback->getType());
                $this->transaction->setOperation($this->callback->getType());
                $this->transaction->setResponse($this->callback);
                
                // Transaction done
                $this->transaction->setState(Transaction::STATE_DONE);
            }
            
            return;
        }
        
        // When transaction already exists return
        if($this->transaction !== null)
        {
            return;
        }
        
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
    
            if($this->transaction !== null)
            {
                return;
            }
        }
        
        $this->transaction          = $this->integration->newTransaction($this->orderId);
    }
    
    /**
     * @return Transaction
     *
     * @throws PaynetException
     */
    public function createReversalTransaction()
    {
        if($this->orderId === null)
        {
            throw new PaynetException('The orderId must be defined for Reversal');
        }
        
        $this->transaction          = $this->integration->newTransaction($this->orderId, false);
        $this->transaction->setTransactionType(Transaction::REVERSAL);
        $this->transaction->setOperation(Transaction::REVERSAL);
        
        return $this->transaction;
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
    
        // A finished sale transaction keeps its result: a repeated or late callback
        // or customer return must not run the order handlers again.
        // The callback type is not signed: a reversal or chargeback callback never
        // runs the sale handlers of a sale transaction.
        if(($this->action === self::ACTION_CALLBACK || $this->action === self::ACTION_REDIRECT)
           && !$this->transaction->isReversal()
           && ($this->transaction->getState() === Transaction::STATE_DONE
               || ($this->callback instanceof CallbackResponse && $this->callback->isReversal())))
        {
            $this->response     = $this->transaction->getResponse();
            $this->isFinished   = true;
            return;
        }

        if($this->action === self::ACTION_CALLBACK)
        {
            $this->integration->debug($this->orderId.": Detect callback for transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->processPaynetEasyCallback($this->callback, $this->transaction);
        }
        elseif ($this->action === self::ACTION_REDIRECT)
        {
            $this->integration->debug($this->orderId.": Detect redirect for transaction {$this->transaction->getTransactionId()}");

            // The redirect data is not verified here: keep the stored result
            if(!$this->transaction->isProcessing())
            {
                $this->response     = $this->transaction->getResponse();
                $this->isFinished   = true;
                return;
            }

            $this->response     = $paymentProcessor->processCustomerReturn($this->callback, $this->transaction);
            
            if($this->response !== null) {
                // mark transaction is done
                $this->transaction->stop();
            }
        }
        elseif ($this->transaction->isProcessing())
        {
            $this->integration->debug($this->orderId.": Update status for transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->executeQuery('status', $this->transaction);
        }
        elseif ($this->transaction->isReversal())
        {
            // start reversal
            $this->integration->debug($this->orderId.": Start reversal transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->executeQuery('return', $this->transaction);
        }
        else
        {
            $this->integration->debug($this->orderId.": Start process transaction {$this->transaction->getTransactionId()}");
            $this->response     = $paymentProcessor->executeQuery($this->transaction->definePaymentMethod(), $this->transaction);
        }
        
        if($this->response !== null)
        {
            $this->transaction->setResponse($this->response);
        }
    }
    
    protected function handleReversalTransaction()
    {
        if($this->transaction->isDeclined() || $this->transaction->isError())
        {
            $this->transaction->setState(Transaction::STATE_DONE);
            $this->integration->debug($this->orderId.": Reversal has error {$this->transaction->getTransactionId()}");
        }
        elseif($this->transaction->isApproved())
        {
            $this->transaction->setState(Transaction::STATE_DONE);
            $this->integration->debug($this->orderId.": Reversal has approved {$this->transaction->getTransactionId()}");
            $this->integration->onReversal($this->transaction);
        }
        elseif($this->transaction->isProcessing())
        {
            $this->transaction->setState(Transaction::STATE_PROCESSING);
            $this->integration->debug($this->orderId.": Reversal processing {$this->transaction->getTransactionId()}");
        }
    
        // save modifications
        $this->integration->saveTransaction($this->transaction);
    }
    
    protected function handleTransaction()
    {
        // early save transaction
        $this->integration->saveTransaction($this->transaction);

        if($this->transaction->isDeclined() || $this->transaction->isError())
        {
            $this->handleError();
        }
        elseif($this->transaction->isApproved())
        {
            // The customer return is verified by its signature: complete the order
            // here too, the server callback may come later or not at all.
            $this->handleApprove();
        }
        elseif($this->transaction->isProcessing())
        {
            $this->handleProcess();
        }
    
        // save final modifications
        $this->integration->saveTransaction($this->transaction);
    }
    
    protected function handleError()
    {
        // Done
        $this->transaction->setState(Transaction::STATE_DONE);
        $this->integration->debug($this->orderId.": Transaction has error {$this->transaction->getTransactionId()}");
        $this->integration->onError($this->transaction);
    }
    
    protected function handleApprove()
    {
        $this->transaction->setState(Transaction::STATE_DONE);
        $this->integration->debug($this->orderId.": Transaction approved {$this->transaction->getTransactionId()}");
        $this->integration->onApprove($this->transaction);
    }
    
    protected function handleProcess()
    {
        if($this->response !== null && $this->response->isShowHtmlNeeded())
        {
            // save html to transaction
            $this->transaction->setHtml($this->response->getHtml());
        }
        
        $this->transaction->setState(Transaction::STATE_PROCESSING);
        $this->integration->debug($this->orderId.": Transaction continue processing {$this->transaction->getTransactionId()}");
        $this->integration->onProcess($this->transaction);
    }
    
    protected function outResponse()
    {
    
    }
    
    protected function handleException(\Exception $exception)
    {
        $this->integration->error('Exception: '.$exception->getMessage()."\n".$exception->getTraceAsString());

        // A failed callback or redirect (bad data, unknown transaction, gateway
        // error) says nothing about the payment: leave the transaction and order as they are.
        if(in_array($this->action, [self::ACTION_CALLBACK, self::ACTION_REDIRECT], true))
        {
            return;
        }
        
        if($this->transaction !== null)
        {
            $this->transaction->setState(Transaction::STATE_DONE);
            $this->transaction->setErrors($exception);
            $this->integration->saveTransaction($this->transaction);
        }
        
        $this->integration->onException($exception);
    }
    
    protected function createPaymentProcessor()
    {
        $payment_processor          = new PaymentProcessor();
        
        return $payment_processor;
    }
}