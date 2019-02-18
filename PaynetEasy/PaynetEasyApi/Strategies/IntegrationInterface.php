<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

interface IntegrationInterface      extends LoggerInterface
{
    /**
     * @return Transaction
     */
    public function newTransaction();
    public function saveTransaction(Transaction $transaction);
    public function definePaymentData(Transaction $transaction);
    
    /**
     * Returns transaction id or null
     *
     * @param $paynetId
     *
     * @return Transaction
     */
    public function findTransactionByPaynetId($paynetId);
    public function findTransactionById($transactionId);
}