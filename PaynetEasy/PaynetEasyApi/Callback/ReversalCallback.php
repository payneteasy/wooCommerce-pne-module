<?php

namespace PaynetEasy\PaynetEasyApi\Callback;

use PaynetEasy\PaynetEasyApi\PaymentData\PaymentTransaction;
use PaynetEasy\PaynetEasyApi\Transport\CallbackResponse;

class ReversalCallback              extends AbstractCallback
{
    /**
     * {@inheritdoc}
     */
    public function processCallback(PaymentTransaction $paymentTransaction, CallbackResponse $callbackResponse)
    {
        $paymentTransaction->setProcessorType(PaymentTransaction::PROCESSOR_CALLBACK);
        $paymentTransaction->setProcessorName($callbackResponse->getType());
        
        parent::processCallback($paymentTransaction, $callbackResponse);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function validateCallback(PaymentTransaction $paymentTransaction, CallbackResponse $callbackResponse)
    {
        $this->validateQueryConfig($paymentTransaction);
        $this->validateSignature($paymentTransaction, $callbackResponse);
        
    }
}