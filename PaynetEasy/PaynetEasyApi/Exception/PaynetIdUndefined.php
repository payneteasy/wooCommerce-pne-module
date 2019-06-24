<?php
namespace PaynetEasy\PaynetEasyApi\Exception;

/**
 * Class PaynetIdUndefined
 * @package PaynetEasy\PaynetEasyApi\Exception
 */
class PaynetIdUndefined             extends ResponseException
{
    private $paynetId;
    
    public function __construct($paynetId)
    {
        parent::__construct('The paynet Id %s is undefined in the callback');
    }
    
    public function getPaynetId()
    {
        return $this->paynetId;
    }
}