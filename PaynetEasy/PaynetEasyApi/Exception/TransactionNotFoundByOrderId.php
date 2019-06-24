<?php
namespace PaynetEasy\PaynetEasyApi\Exception;

/**
 * Class PaynetIdUndefined
 * @package PaynetEasy\PaynetEasyApi\Exception
 */
class TransactionNotFoundByOrderId     extends ResponseException
{
    private $orderId;
    
    public function __construct($orderId)
    {
        $this->orderId              = $orderId;
        
        parent::__construct('The transaction is not found by order Id %s');
    }
    
    public function getOrderId()
    {
        return $this->orderId;
    }
}