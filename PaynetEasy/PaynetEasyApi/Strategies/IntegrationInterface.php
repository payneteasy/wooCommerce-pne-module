<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

interface IntegrationInterface      extends LoggerInterface
{
    /**
     * @return Transaction
     */
    public function newTransaction();
    
    /**
     * Returns transaction id or null
     *
     * @param $paynetId
     *
     * @return Transaction
     */
    public function findTransactionByPaynetId($paynetId);
    public function findTransactionByOrderId($orderId);
    public function findTransactionById($transactionId);
}