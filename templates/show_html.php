<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$payneteasy_transaction             = get_query_var('payneteasy_transaction');

if($payneteasy_transaction instanceof \PaynetEasy\PaynetEasyApi\Strategies\Transaction)
{
    echo $payneteasy_transaction->getHtml();
}