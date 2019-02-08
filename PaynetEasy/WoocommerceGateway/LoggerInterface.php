<?php
namespace PaynetEasy\WoocommerceGateway;

/**
 * Interface LoggerInterface
 *
 * @package PaynetEasy\WoocommerceGateway
 */
interface LoggerInterface
{
    public function log($message, $level);
    public function debug($message);
    public function error($message);
}