<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 * @since 1.0
 */

namespace Garden;

use JsonSerializable;

/**
 * A class that contains the information in an http response.
 */
class Response implements JsonSerializable {
    /// Properties ///

    /**
     * An array of cookie sets. This array is in the form:
     *
     * ```
     * array (
     *     'name' => [args for setcookie()]
     * )
     * ```
     *
     * @var array An array of cookies sets.
     */
    protected $cookies = [];

    /**
     * An array of global cookie sets.
     *
     * This array is for code the queue up cookie changes before the response has been created.
     *
     * @var array An array of cookies.
     */
    protected static $globalCookies;

    /**
     * @var Response The current response.
     */
    protected static $current;

    /**
     * @var array
     */
    protected $data = [];

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
     * @var array An array of global http headers.
     */
    protected static $globalHeaders;

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

    /**
     * Gets or sets the response that is currently being processed.
     *
     * @param Response|null $response Set a new response or pass null to get the current response.
     * @return Response Returns the current response.
     */
    public static function current(Response $response = null) {
        if ($response !== null) {
            self::$current = $response;
        } elseif (self::$current === null) {
            self::$current = new Response();
        }

        return self::$current;
    }

    /**
     * Create a Response from a variety of data.
     *
     * @param mixed $result The result to create the response from.
     * @return Response Returns a {@link Response} object.
     */
    public static function create($result) {
        if ($result instanceof Response) {
            return $result;
        }

        $response = new Response();

        if ($result instanceof Exception\ClientException) {
            /* @var Exception\ClientException $cex */
            $cex = $result;
            $response->status($cex->getCode());
            $response->headers($cex->getHeaders());
            $response->data([
                'exception' => $cex->getMessage(),
                'code' => $cex->getCode()
            ]);
        } elseif ($result instanceof \Exception) {
            /* @var \Exception $ex */
            $ex = $result;
            $response->status($ex->getCode());
            $response->data([
                'exception' => $ex->getMessage(),
                'code' => $ex->getCode()
            ]);
        } elseif (is_array($result)) {
            if (count($result) === 3 && isset($result[0], $result[1], $result[2])) {
                // This is a rack style response in the form [code, headers, body].
                $response->status($result[0]);
                $response->headers($result[1]);
                $response->data($result[2]);
            } elseif (isset($result['response'], $result['body'])) {
                // This is a dispatched response.
                $response = static::create($result['response']);
//                if ($result['body']) {
//                    $response->data('body'] = $result['body'];
//                }
            } else {
                $response->data($result);
            }
        } else {
            $response->status(422);
            $response->data([
                'exception' => "Unknown result type for response.",
                'code' => $response->status()
            ]);
        }
        return $response;
    }

    /**
     * Gets or sets the content type.
     *
     * @param string|null $value The new content type or null to get the current content type.
     * @return Response|string Returns the current content type or `$this` for fluent calls.
     */
    public function contentType($value = null) {
        if ($value === null) {
            return $this->headers('Content-Type');
        }

        return $this->headers('Content-Type', $value);
    }

    /**
     * Set the content type from an accept header.
     *
     * @param string $accept The value of the accept header.
     * @return Response $this Returns `$this` for fluent calls.
     */
    public function contentTypeFromAccept($accept) {
        $accept = strtolower($accept);
        if (strpos($accept, ',') === false) {
            list($contentType) = explode(';', $accept);
        } elseif (strpos($accept, 'text/html') !== false) {
            $contentType = 'text/html';
        } elseif (strpos($accept, 'application/rss+xml' !== false)) {
            $contentType = 'application/rss+xml';
        } elseif (strpos($accept, 'text/plain')) {
            $contentType = 'text/plain';
        } else {
            $contentType = 'text/html';
        }
        $this->contentType($contentType);
        return $this;
    }

    /**
     * Translate an http code to its corresponding status message.
     *
     * @param int $statusCode The http status code.
     * @param bool $header Whether or not the result should be in a form that can be passed to {@link header}.
     * @return string Returns the status message corresponding to {@link $code}.
     */
    public static function statusMessage($statusCode, $header = false) {
        $message = val($statusCode, self::$messages, 'Unknown');

        if ($header) {
            return "HTTP/1.1 $statusCode $message";
        } else {
            return $message;
        }
    }

    /**
     * Gets or sets a cookie.
     *
     * @param string $name The name of the cookie.
     * @param bool $value The value of the cookie. This value is stored on the clients computer; do not store sensitive information.
     * @param int $expires The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch.
     * @param string $path The path on the server in which the cookie will be available on.
     * If set to '/', the cookie will be available within the entire {@link $domain}.
     * @param string $domain The domain that the cookie is available to.
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client.
     * @param bool $httponly When TRUE the cookie will be made accessible only through the HTTP protocol.
     * @return $this|mixed Returns the cookie settings at {@link $name} or `$this` when setting a cookie for fluent calls.
     */
    public function cookies($name, $value = false, $expires = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        if ($value === false) {
            return val($name, $this->cookies);
        }

        $this->cookies[$name] = [$value, $expires, $path, $domain, $secure, $httponly];
        return $this;
    }

    /**
     * Gets or sets a global cookie.
     *
     * Global cookies are used when you want to set a cookie, but a {@link Response} has not been created yet.
     *
     * @param string $name The name of the cookie.
     * @param bool $value The value of the cookie. This value is stored on the clients computer; do not store sensitive information.
     * @param int $expires The time the cookie expires. This is a Unix timestamp so is in number of seconds since the epoch.
     * @param string $path The path on the server in which the cookie will be available on.
     * If set to '/', the cookie will be available within the entire {@link $domain}.
     * @param string $domain The domain that the cookie is available to.
     * @param bool $secure Indicates that the cookie should only be transmitted over a secure HTTPS connection from the client.
     * @param bool $httponly When TRUE the cookie will be made accessible only through the HTTP protocol.
     * @return mixed|null Returns the cookie settings at {@link $name} or `null` when setting a cookie.
     */
    public static function globalCookies($name = null, $value = false, $expires = 0, $path = null, $domain = null, $secure = false, $httponly = false) {
        if (self::$globalCookies === null) {
            self::$globalCookies = [];
        }

        if ($name === null) {
            return self::$globalCookies;
        }

        if ($value === false) {
            return val($name, self::$globalCookies);
        }

        self::$globalCookies[$name] = [$value, $expires, $path, $domain, $secure, $httponly];
        return null;
    }

    /**
     * Get or set the data for the response.
     *
     * @param array|null $value Pass a new data value or `null` to get the current data array.
     * @return Response|array $this Returns either the data or `$this` when setting the data.
     */
    public function data($value = null) {
        if ($value !== null) {
            $this->data = $value;
            return $this;
        } else {
            return $this->data();
        }
    }

    /**
     * Gets or sets headers.
     *
     * @param string|array $name The name of the header or an array of headers.
     * @param string|null $value A new value for the header or null to get the current header.
     * @param bool $replace Whether or not to replace the current header or append.
     * @return Response|string Returns the value of the header or `$this` for fluent calls.
     */
    public function headers($name, $value = null, $replace = true) {
        $headers = static::splitHeaders($name, $value);

        if (is_string($headers)) {
            return val($headers, $this->headers);
        }

        foreach ($headers as $name => $value) {
            if ($replace || !isset($this->headers[$name])) {
                $this->headers[$name] = $value;
            } else {
                $this->headers[$name] = array_merge((array)$this->headers, [$value]);
            }
        }
        return $this;
    }

    /**
     * Gets or sets global headers.
     *
     * The global headers exist to allow code to queue up headers before the response has been constructed.
     *
     * @param string|array|null $name The name of the header or an array of headers.
     * @param string|null $value A new value for the header or null to get the current header.
     * @param bool $replace Whether or not to replace the current header or append.
     * @return string|array Returns one of the following:
     * - string|array: Returns the current value of the header at {@link $name}.
     * - array: Returns the entire global headers array when {@link $name} is not passed.
     * - null: Returns `null` when setting a global header.
     */
    public static function globalHeaders($name = null, $value = null, $replace = true) {
        if (self::$globalHeaders === null) {
            self::$globalHeaders = [
                'P3P' => 'CP="CAO PSA OUR"'
            ];
        }

        if ($name === null) {
            return self::$globalHeaders;
        }

        $headers = static::splitHeaders($name, $value);

        if (is_string($headers)) {
            return val($headers, self::$globalHeaders);
        }

        foreach ($headers as $name => $value) {
            if ($replace || !isset(self::$globalHeaders[$name])) {
                self::$globalHeaders[$name] = $value;
            } else {
                self::$globalHeaders[$name] = array_merge((array)self::$globalHeaders, [$value]);
            }
        }
        return null;
    }

    /**
     * Split and normalize headers into a form appropriate for {@link $headers} or {@link $globalHeaders}.
     *
     * @param string|array $name The name of the header or an array of headers.
     * @param string|null $value The header value if {@link $name} is a string.
     * @return array|string Returns one of the following:
     * - array: An array of headers.
     * - string: The header name if just a name was passed.
     * @throws \InvalidArgumentException Throws an exception if {@link $name} is not a valid string or array.
     */
    private static function splitHeaders($name, $value = null) {
        if (is_string($name)) {
            if (strpos($name, ':') !== false) {
                // The name is in the form Header: value.
                list($name, $value) = explode(':', $name, 2);
                return [static::normalizeHeader(trim($name)) => trim($value)];
            } elseif ($value !== null) {
                return [static::normalizeHeader($name) => $value];
            } else {
                return static::normalizeHeader($name);
            }
        } elseif (is_array($name)) {
            $result = [];
            foreach ($name as $key => $value) {
                if (is_numeric($key)) {
                    // $value should be a header in the form Header: value.
                    list($key, $value) = explode(':', $value, 2);
                }
                $result[static::normalizeHeader(trim($key))] = trim($value);
            }
            return $result;
        }
        throw new \InvalidArgumentException("Argument #1 to splitHeaders() was not valid.", 422);
    }

    /**
     * Normalize a header key to the proper casing.
     *
     * Example:
     *
     * ```
     * echo static::normalizeHeader('CONTENT_TYPE');
     *
     * // Content-Type
     * ```
     *
     * @param string $name The name of the header.
     * @return string Returns the normalized header name.
     */
    protected static function normalizeHeader($name) {
        $name = str_replace(['-', '_'], ' ', strtolower($name));
        $name = str_replace(' ', '-', ucwords($name));
        return $name;
    }

    /**
     * Gets/sets the http status code.
     *
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

    /**
     * Flush the response to the client.
     */
    public function flush() {
        $this->flushHeaders();

        echo json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Flush the headers to the browser.
     *
     * @param bool $global Whether or not to merge the global headers with this response.
     */
    public function flushHeaders($global = true) {
        if ($global) {
            $cookies = array_replace(static::globalCookies(), $this->cookies);
        } else {
            $cookies = $this->cookies;
        }

        // Set the cookies first.
        foreach ($cookies as $name => $value) {
            // Set the defaults.
            if ($value[2] === null) {
                $value[2] = $this->defaultCookiePath;
            }
            if ($value[3] === null) {
                $value[3] = $this->defaultCookieDomain;
            }

            setcookie($name, $value[0], $value[1], $value[2], $value[3], $value[4], $value[5]);
        }

        // Set the response code.
        header(static::statusMessage($this->status), true, $this->status);

        if ($global) {
            $headers = array_replace(static::globalHeaders(), $this->headers);
        } else {
            $headers = $this->headers;
        }

        // Flush the rest of the headers.
        foreach ($headers as $name => $value) {
            if (!$value) {
                continue;
            }

            if ($name === 'Content-Type') {
                $value = is_array($value) ? array_shift($value) : $value;

                header("$name: $value; charset=utf8", true);
            } elseif (is_array($value)) {
                foreach ($value as $line) {
                    header("$name: $line", false);
                }
            } else {
                header("$name: $value", false);
            }
        }
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        return $this->data;
    }
}