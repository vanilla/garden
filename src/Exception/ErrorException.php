<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Exception;

/**
 * An exception for php errors that also includes the error context and a backtrace.
 */
class ErrorException extends \ErrorException {
    protected $context;

    protected $backtrace;

    /**
     * Initialize an instance of the {@link ErrorException} class.
     *
     * @param string $message The error message.
     * @param int $number The error number.
     * @param int $filename The file where the error occurred.
     * @param string $line The line number in the file.
     * @param int $context The currently defined variables when the error occured.
     * @param array $backtrace A debug backtrace from when the error occurred.
     */
    public function __construct($message, $number, $filename, $line, $context, $backtrace = []) {
        parent::__construct($message, $number, 0, $filename, $line);
        $this->context = $context;
        $this->backtrace = $backtrace;
    }

    /**
     * Get the debug backtrace from the error.
     *
     * @return array Returns the backtrace.
     */
    public function getBacktrace() {
        return $this->backtrace;
    }

    /**
     * Get the error context.
     *
     * @return int
     */
    public function getContext() {
        return $this->context;
    }
}