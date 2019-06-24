<?php
namespace PaynetEasy\PaynetEasyApi\Exception;

/**
 * Class PaynetIdUndefined
 * @package PaynetEasy\PaynetEasyApi\Exception
 */
class TransactionHasWrongState     extends ResponseException
{
    private $state;
    private $explain;
    
    /**
     * TransactionHasWrongState constructor.
     *
     * @param string        $state
     * @param string        $explain
     */
    public function __construct($state, $explain = '')
    {
        $this->state            = $state;
        $this->explain          = $explain;
        
        parent::__construct('The transaction has wrong state %s (%s)');
    }
    
    public function getState()
    {
        return $this->state;
    }
    
    public function getExplain()
    {
        return $this->explain;
    }
}