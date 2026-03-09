<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Sentinel exception thrown by the wp_die() Brain Monkey stub so that
 * tests can assert where execution stopped without calling die/exit.
 */
class WpDieException extends \RuntimeException {}
