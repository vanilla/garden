<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Exception;

/**
 * Represents a 404 not found error.
 */
class NotFoundException extends ClientException {
    /**
     * @param string $message
     * @param int $code
     * @param array $headers
     */
    public function __construct($message, $code = 404, array $headers = []) {
        parent::__construct($message, 404, $headers);
    }
}
