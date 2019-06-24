<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

interface IntegrationInterface      extends LoggerInterface
{
    /**
     * @param int $order_id
     * @param bool $is_define_data
     *
     * @return Transaction
     */
    public function newTransaction($order_id = null, $is_define_data = true);
    public function saveTransaction(Transaction $transaction);
    public function definePaymentData(Transaction $transaction);
    
    /**
     * Returns transaction id or null
     *
     * @param $paynetId
     * @param array $filters
     *
     * @return Transaction
     */
    public function findTransactionByPaynetId($paynetId, array $filters = []);
    public function findTransactionById($transactionId);
    public function findTransactionByOrderId($orderId, array $filters = []);
    
    public function onError(Transaction $transaction);
    public function onApprove(Transaction $transaction);
    public function onProcess(Transaction $transaction);
    public function onException(\Exception $exception);
    
    public function onReversal(Transaction $transaction);
}