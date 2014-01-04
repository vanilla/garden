<?php

namespace Garden;

class Response {
    /// Properties ///

    /**
     * An array of cookie sets. This array is in the form:
     *
     * ```
     * array (
     *     'name' => array(args for setcookie)
     * )
     * ```
     *
     * @var array An array of cookies sets.
     */
    protected $cookies = array();

    /**
     * @var array The data array that will be passed to the view.
     */
    protected $data = array();

    /**
     * @var string The default cookie domain.
     */
    public $defaultCookieDomain;

    /**
     * @var string The default cookie path.
     */
    public $defaultCookiePath;

    /**
     * @var array An array of http headers.
     */
    protected $headers = array();

    /**
     * @var int HTTP status code
     */
    protected $status = 200;

    /**
     * @var array HTTP response codes and messages.
     */
    protected static $messages = array(
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Successful 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported'
    );

    /// Methods ///

    public function data($name, $default) {
        return val($name, $this->data, $default);
    }

    public function header($name, $value = null, $replace = true) {
    }

    public function setCookie($name, $value = null, $expires = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        // Set the defaults.
        if ($path === null)
            $path = $this->defaultCookiePath;
        if ($domain === null)
            $domain = $this->defaultCookieDomain;

        $this->cookies[$name] = array($value, $expires, $path, $domain, $secure, $httponly);
    }

    public function setData($name, $value) {
        $this->data[$name] = $value;
    }

    /**
     * Gets/sets the http status code.
     * @param int $value The new value if setting the http status code.
     * @return int The current http status code.
     * @throws \InvalidArgumentException The new status is not a valid http status number.
     */
    public function status($value = null) {
        if ($value !== null) {
            if (!isset(self::$messages[$value])) {
                throw new \InvalidArgumentException("Response->status(): Invalid http status: $value.", 500);
            }
            $this->status = (int)$value;
        }
        return $this->status;
    }
}