<?php

namespace Garden\Exception;

/**
 * Represents a 400 series exception.
 */
class ClientException extends \Exception {
    protected $headers;

    public function __construct($message = '', $code = 400, array $headers = []) {
        parent::__construct($message, $code);
        $this->headers = $headers;
    }
}
