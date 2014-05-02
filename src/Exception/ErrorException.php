<?php

namespace Garden\Exception;

/**
 * An exception for php errors that also includes
 */
class ErrorException extends \ErrorException {
    protected $context;

    public function __construct($message, $errno, $filename, $line, $context) {
       parent::__construct($message, $errno, 0, $filename, $line);
       $this->context = $context;
    }

    public function getContext() {
       return $this->context;
    }
}