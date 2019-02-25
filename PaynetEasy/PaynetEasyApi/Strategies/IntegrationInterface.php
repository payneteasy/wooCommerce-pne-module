<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

interface IntegrationInterface      extends LoggerInterface
{
    /**
     * @param null $order_id
     *
     * @return Transaction
     */
    public function newTransaction($order_id = null);
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
    public function findTransactionByOrderId($orderId);
    
    public function onError(Transaction $transaction);
    public function onApprove(Transaction $transaction);
    public function onProcess(Transaction $transaction);
    public function onException(\Exception $exception);
    
}