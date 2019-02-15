<?php
namespace PaynetEasy\PaynetEasyApi\Exception;

/**
 * Class PaynetIdUndefined
 * @package PaynetEasy\PaynetEasyApi\Exception
 */
class TransactionNotFoundByPaynetId     extends ResponseException
{
    private $paynetId;
    
    public function __construct($paynetId)
    {
        $this->paynetId             = $paynetId;
        
        parent::__construct('The transaction is not found by paynet Id %s');
    }
    
    public function getPaynetId()
    {
        return $this->paynetId;
    }
}