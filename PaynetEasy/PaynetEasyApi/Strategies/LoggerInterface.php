<?php
namespace PaynetEasy\PaynetEasyApi\Strategies;

/**
 * Interface LoggerInterface
 *
 * @package PaynetEasy\PaynetEasyApi\Strategies
 */
interface LoggerInterface
{
    public function notice($message);
    public function debug($message);
    public function error($message);
}