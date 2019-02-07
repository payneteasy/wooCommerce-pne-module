<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

$payneteasy_transaction             = get_query_var('payneteasy_transaction');

if($payneteasy_transaction instanceof \PaynetEasy\WoocommerceGateway\PaymentTransaction)
{
    echo $payneteasy_transaction->get_html_for_show();
}