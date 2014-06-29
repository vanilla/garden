<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;

use JsonSerializable;

/**
 * A class that contains the information in an http request.
 */
class Request implements JsonSerializable {

    /// Constants ///
    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';
    const METHOD_OPTIONS = 'OPTIONS';

    /// Properties ///

    /**
     *
     * @var array The data in this request.
     */
    protected $env;

    /**
     * @var Request The currently dispatched request.
     */
    protected static $current;

    /**
     * @var array The default environment for constructed requests.
     */
    protected static $defaultEnv;

    /**
     * @var array The global environment for the request.
     */
    protected static $globalEnv;

    /**
     * Special-case HTTP headers that are otherwise unidentifiable as HTTP headers.
     * Typically, HTTP headers in the $_SERVER array will be prefixed with
     * `HTTP_` or `X_`. These are not so we list them here for later reference.
     *
     * @var array
     */
    protected static $specialHeaders = array(
        'CONTENT_TYPE',
        'CONTENT_LENGTH',
        'PHP_AUTH_USER',
        'PHP_AUTH_PW',
        'PHP_AUTH_DIGEST',
        'AUTH_TYPE'
    );

    /// Methods ///

    /**
     * Initialize a new instance of the {@link Request} class.
     *
     * @param string $url The url of the request or blank to use the current environment.
     * @param string $method The request method.
     * @param mixed $data The request data. This is the query string for GET requests or the body for other requests.
     */
    public function __construct($url = '', $method = '', $data = null) {
        if ($url) {
            $this->env = (array)static::defaultEnvironment();
            // Instantiate the request from the url.
            $this->setUrl($url);
            if ($method) {
                $this->setMethod($method);
            }
            if (is_array($data)) {
                $this->setData($data);
            }
        } else {
            // Instantiate the request from the global environment.
            $this->env = static::globalEnvironment();
            if ($method) {
                $this->setMethod($method);
            }
            if (is_array($data)) {
                $this->setData($data);
            }
        }

        static::overrideEnvironment($this->env);
    }

    /**
     * Convert a request to a string.
     *
     * @return string Returns the url of the request.
     */
    public function __toString() {
        return $this->getUrl();
    }

    /**
     * Gets or sets the current request.
     *
     * @param Request $request Pass a request object to set the current request.
     * @return Request Returns the current request if {@link Request} is null or the previous request otherwise.
     */
    public static function current(Request $request = null) {
        if ($request !== null) {
            $bak = self::$current;
            self::$current = $request;
            return $bak;
        }
        return self::$current;
    }

    /**
     * Gets or updates the default environment.
     *
     * @param string|array|null $key Specifies a specific key in the environment array.
     * If you pass an array for this parameter then you can set the default environment.
     * @param bool $merge Whether or not to merge the new value.
     * @return array|mixed Returns the value at {@link $key} or the entire environment array.
     * @throws \InvalidArgumentException Throws an exception when {@link $key} is not valid.
     */
    public static function defaultEnvironment($key = null, $merge = false) {
        if (self::$defaultEnv === null) {
            self::$defaultEnv = array(
                'REQUEST_METHOD' => 'GET',
                'SCRIPT_NAME' => '',
                'PATH_INFO' => '/',
                'EXT' => '',
                'QUERY' => [],
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
                'HTTP_ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
                'HTTP_USER_AGENT' => 'Garden/0.1 (Howdy stranger)',
                'REMOTE_ADDR' => '127.0.0.1',
                'URL_SCHEME' => 'http',
                'INPUT' => [],
            );
        }

        if ($key === null) {
            return self::$defaultEnv;
        } elseif (is_array($key)) {
            if ($merge) {
                self::$defaultEnv = array_merge(self::$defaultEnv, $key);
            } else {
                self::$defaultEnv = $key;
            }
            return self::$defaultEnv;
        } elseif (is_string($key)) {
            return val($key, self::$defaultEnv);
        } else {
            throw new \InvalidArgumentException("Argument #1 for Request::globalEnvironment() is invalid.", 422);
        }
    }

    /**
     * Parse request information from the current environment.
     *
     * The environment contains keys based on the Rack protocol (see http://rack.rubyforge.org/doc/SPEC.html).
     *
     * @param mixed $key The environment variable to look at.
     * - null: Return the entire environment.
     * - true: Force a re-parse of the environment and return the entire environment.
     * - string: One of the environment variables.
     * @return array|string Returns the global environment or the value at {@link $key}.
     */
    public static function globalEnvironment($key = null) {
        // Check to parse the environment.
        if ($key === true || !isset(self::$globalEnv)) {
            $env = static::defaultEnvironment();

            // REQUEST_METHOD.
            $env['REQUEST_METHOD'] = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? val('REQUEST_METHOD', $_SERVER) : 'CONSOLE');

            // SCRIPT_NAME: This is the root directory of the application.
            $script_name = $_SERVER['SCRIPT_NAME'];
            if ($script_name && substr($script_name, -strlen('index.php')) == 0) {
                $script_name = substr($script_name, 0, -strlen('index.php'));
            } else {
                $script_name = '';
            }
            $env['SCRIPT_NAME'] = rtrim($script_name, '/');

            // PATH_INFO.
            $path = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';

            // Strip the extension from the path.
            if (substr($path, -1) !== '/' && ($pos = strrpos($path, '.')) !== false) {
                $ext = substr($path, $pos);
                $path = substr($path, 0, $pos);
                $env['EXT'] = $ext;
            }

            $env['PATH_INFO'] = '/' . ltrim($path, '/');

            // QUERY.
            $get = $_GET;
            $env['QUERY'] = $get;

            // SERVER_NAME.
            $env['SERVER_NAME'] = array_select(
                ['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME'],
                $_SERVER
            );

            // HTTP_* headers.
            $env = array_replace($env, static::extractHeaders($_SERVER));

            // URL_SCHEME.
            $url_scheme = 'http';
            // Web server-originated SSL.
            if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
                $url_scheme = 'https';
            }
            // Load balancer-originated (and terminated) SSL.
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') {
                $url_scheme = 'https';
            }
            // Varnish modifies the scheme.
            $org_protocol = val('HTTP_X_ORIGINALLY_FORWARDED_PROTO', $_SERVER, null);
            if (!is_null($org_protocol)) {
                $url_scheme = $org_protocol;
            }
            $env['URL_SCHEME'] = $url_scheme;

            // SERVER_PORT.
            if (isset($_SERVER['SERVER_PORT'])) {
                $server_port = (int) $_SERVER['SERVER_PORT'];
            } elseif ($url_scheme === 'https') {
                $server_port = 443;
            } else {
                $server_port = 80;
            }
            $env['SERVER_PORT'] = $server_port;

            // INPUT: The entire input.
            // Input stream (readable one time only; not available for multipart/form-data requests)
            switch (val('CONTENT_TYPE', $env)) {
                case 'application/json':
                    $input_raw = @file_get_contents('php://input');
                    $input = @json_decode($input_raw, true);
                    break;
            }
            if (isset($input)) {
                $env['INPUT'] = $input;
            } elseif (isset($_POST)) {
                $env['INPUT'] = $_POST;
            }

            if (isset($input_raw)) {
                $env['INPUT_RAW'] = $input_raw;
            }

            // IP Address.
            // Load balancers set a different ip address.
            $ip = array_select(
                ['HTTP_X_ORIGINALLY_FORWARDED_FOR', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'],
                $_SERVER,
                '127.0.0.1'
            );
            if (strpos($ip, ',') !== false) {
                $ip = substr($ip, 0, strpos($ip, ','));
            }

            // Make sure we have a valid ip.
            if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m)) {
                $ip = $m[1];
            } elseif ($ip === '::1') {
                $ip = '127.0.0.1';
            } else {
                $ip = '0.0.0.0'; // unknown ip
            }

            $env['REMOTE_ADDR'] = $ip;

            self::$globalEnv = $env;
        }

        if ($key) {
            return val($key, self::$globalEnv);
        }
        return self::$globalEnv;
    }

    /**
     * Check for specific environment overrides.
     *
     * @param array &$env The environment to override.
     */
    protected static function overrideEnvironment(&$env) {
        $get =& $env['QUERY'];

        // Check to override the method.
        if (isset($get['x-method'])) {
            $method = strtoupper($get['x-method']);

            $getMethods = array(self::METHOD_GET, self::METHOD_HEAD, self::METHOD_OPTIONS);

            // Don't allow get style methods to be overridden to post style methods.
            if (!in_array($env['REQUEST_METHOD'], $getMethods) || in_array($method, $getMethods)) {
                static::replaceEnv($env, 'REQUEST_METHOD', $method);
            } else {
                $env['X_METHOD_BLOCKED'] = true;
            }
            unset($get['x-method']);
        }

        // Check to override the accepts header.
        switch (strtolower($env['EXT'])) {
            case '.json':
                static::replaceEnv($env, 'HTTP_ACCEPT', 'application/json');
                break;
            case '.rss':
                static::replaceEnv($env, 'HTTP_ACCEPT', 'application/rss+xml');
                break;
        }
    }

    /**
     * Get a value from the environment or the entire environment.
     *
     * @param string|null $key The key to get or null to get the entire environment.
     * @param mixed $default The default value if {@link $key} is not found.
     * @return mixed|array Returns the value at {@link $key}, {$link $default} or the entire environment array.
     * @see Request::setEnv()
     */
    public function getEnv($key = null, $default = null) {
        if ($key === null) {
            return $this->env;
        }
        return val(strtoupper($key), $this->env, $default);
    }

    /**
     * Set a value from the environment or the entire environment.
     *
     * @param string|array $key The key to set or an array to set the entire environment.
     * @param mixed $value The value to set.
     * @return Request Returns $this for fluent calls.
     * @throws \InvalidArgumentException Throws an exception when {@link $key} is invalid.
     * @see Request::getEnv()
     */
    public function setEnv($key, $value = null) {
        if (is_string($key)) {
            $this->env[strtoupper($key)] = $value;
        } elseif (is_array($key)) {
            $this->env = $key;
        } else {
            throw new \InvalidArgumentException("Argument 1 must be either a string or array.", 422);
        }
        return $this;
    }

    /**
     * Replace an environment variable with another one and back up the old one in a *_RAW key.
     *
     * @param array &$env The environment array.
     * @param string $key The environment key.
     * @param mixed $value The new environment value.
     * @return mixed Returns the old value or null if there was no old value.
     */
    public static function replaceEnv(&$env, $key, $value) {
        $key = strtoupper($key);

        $result = null;
        if (isset($env[$key])) {
            $result = $env[$key];
            $env[$key.'_RAW'] = $result;
        }
        $env[$key] = $value;
        return $result;
    }

    /**
     * Restore an environment variable that was replaced with {@link Request::replaceEnv()}.
     *
     * @param array &$env The environment array.
     * @param string $key The environment key.
     * @return mixed Returns the current environment value.
     */
    public static function restoreEnv(&$env, $key) {
        $key = strtoupper($key);

        if (isset($env[$key.'_RAW'])) {
            $env[$key] = $env[$key.'_RAW'];
            unset($env[$key.'_RAW']);
            return $env[$key];
        } elseif (isset($env[$key])) {
            return $env[$key];
        }
        return null;
    }

    /**
     * Extract the headers from an array such as $_SERVER or the request's own $env.
     *
     * @param array $arr The array to extract.
     * @return array The extracted headers.
     */
    public static function extractHeaders($arr) {
        $result = array();

        foreach ($arr as $key => $value) {
            $key = strtoupper($key);
            if (strpos($key, 'X_') === 0 || strpos($key, 'HTTP_') === 0 || in_array($key, static::$specialHeaders)) {
                if ($key === 'HTTP_CONTENT_TYPE' || $key === 'HTTP_CONTENT_LENGTH') {
                    continue;
                }
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Get the hostname of the request.
     *
     * @return string Returns the host.
     */
    public function getHost() {
        return (string)$this->env['SERVER_NAME'];
    }

    /**
     * Set the hostname of the request.
     *
     * @param string $host The hostname.
     * @return Request Returns $this for fluent calls.
     */
    public function setHost($host) {
        $this->env['SERVER_NAME'] = $host;
        return $this;
    }

    /**
     * Get the host and port, but only if the port is not the standard port for the request scheme.
     *
     * @return string Returns the host and port or just the host if this is the standard port.
     * @see Request::getHost()
     * @see Request::getPort()
     */
    public function getHostAndPort() {
        $host = $this->getHost();
        $port = $this->getPort();

        // Only append the port if it is non-standard.
        if (($port == 80 && $this->getScheme() === 'http') || ($port == 443 && $this->getScheme() === 'https')) {
            $port = '';
        } else {
            $port = ':'.$port;
        }
        return $host.$port;
    }

    /**
     * Get the ip address of the request.
     *
     * @return string Returns the current ip address.
     */
    public function getIP() {
        return (string)$this->env['REMOTE_ADDR'];
    }

    /**
     * Set the ip address of the request.
     *
     * @param string $ip The new ip address.
     * @return Request Returns $this for fluent calls.
     */
    public function setIP($ip) {
        $this->env['REMOTE_ADDR'] = $ip;
        return $this;
    }

    /**
     * Gets whether or not this is a DELETE request.
     *
     * @return bool Returns true if this is a DELETE request, false otherwise.
     */
    public function isDelete() {
        return $this->getMethod() === self::METHOD_DELETE;
    }

    /**
     * Gets whether or not this is a GET request.
     *
     * @return bool Returns true if this is a GET request, false otherwise.
     */
    public function isGet() {
        return $this->getMethod() === self::METHOD_GET;
    }

    /**
     * Gets whether or not this is a HEAD request.
     *
     * @return bool Returns true if this is a HEAD request, false otherwise.
     */
    public function isHead() {
        return $this->getMethod() === self::METHOD_HEAD;
    }

    /**
     * Gets whether or not this is an OPTIONS request.
     *
     * @return bool Returns true if this is an OPTIONS request, false otherwise.
     */
    public function isOptions() {
        return $this->getMethod() === self::METHOD_OPTIONS;
    }

    /**
     * Gets whether or not this is a PATCH request.
     *
     * @return bool Returns true if this is a PATCH request, false otherwise.
     */
    public function isPatch() {
        return $this->getMethod() === self::METHOD_PATCH;
    }

    /**
     * Gets whether or not this is a POST request.
     *
     * @return bool Returns true if this is a POST request, false otherwise.
     */
    public function isPost() {
        return $this->getMethod() === self::METHOD_POST;
    }

    /**
     * Gets whether or not this is a PUT request.
     *
     * @return bool Returns true if this is a PUT request, false otherwise.
     */
    public function isPut() {
        return $this->getMethod() === self::METHOD_PUT;
    }

    /**
     * Get the http method of the request.
     *
     * @return string Returns the http method of the request.
     */
    public function getMethod() {
        return (string)$this->env['REQUEST_METHOD'];
    }

    /**
     * Set the http method of the request.
     *
     * @param string $method The new request method.
     * @return Request Returns $this for fluent calls.
     */
    public function setMethod($method) {
        $this->env['REQUEST_METHOD'] = strtoupper($method);
        return $this;
    }

    /**
     * Gets the request path.
     *
     * Note that this returns the path without the file extension.
     * If you want the full path use {@link Request::getFullPath()}.
     *
     * @return string Returns the request path.
     */
    public function getPath() {
        return (string)$this->env['PATH_INFO'];
    }

    /**
     * Sets the request path.
     *
     * @param string $path The path to set.
     * @return Request Returns $this for fluent calls.
     */
    public function setPath($path) {
        $this->env['PATH_INFO'] = (string)$path;
        return $this;
    }

    /**
     * Get the file extension of the request.
     *
     * @return string Returns the file extension.
     */
    public function getExt() {
        return (string)$this->env['EXT'];
    }

    /**
     * Sets the file extension of the request.
     *
     * @param string $ext The file extension to set.
     * @return Request Returns $this for fluent calls.
     */
    public function setExt($ext) {
        if ($ext) {
            $this->env['EXT'] = '.'.ltrim($ext, '.');
        } else {
            $this->env['EXT'] = '';
        }
        return $this;
    }

    /**
     * Get the path and file extenstion.
     *
     * @return string Returns the path and file extension.
     * @see Request::setPathExt()
     */
    public function getPathExt() {
        return $this->env['PATH_INFO'].$this->env['EXT'];
    }

    /**
     * Set the path with file extension.
     *
     * @param string $path The path to set.
     * @return Request Returns $this for fluent calls.
     * @see Request::getPathExt()
     */
    public function setPathExt($path) {
        // Strip the extension from the path.
        if (substr($path, -1) !== '/' && ($pos = strrpos($path, '.')) !== false) {
            $ext = substr($path, $pos);
            $path = substr($path, 0, $pos);
            $this->env['EXT'] = $ext;
        } else {
            $this->env['EXT'] = '';
        }
        $this->env['PATH_INFO'] = $path;
        return $this;
    }

    /**
     * Get the full path of the request.
     *
     * The full path consists of the root + path + extension.
     *
     * @return string Returns the full path.
     */
    public function getFullPath() {
        return $this->getRoot().$this->getPathExt();
    }

    /**
     * Set the full path of the request.
     *
     * The full path consists of the root + path + extension.
     * This method examins the current root to see if the root can still be used or should be removed.
     * Special care must be taken when calling this method to make sure you don't remove the root unintentionally.
     *
     * @param string $fullPath The full path to set.
     * @return Request Returns $this for fluent calls.
     */
    public function setFullPath($fullPath) {
        // Try stripping the root out of the path first.
        $root = (string)$this->getRoot();

        if ($root && strpos($fullPath, $root) === 0) {
            $fullPath = substr($fullPath, strlen($root));

            if (!$fullPath || substr($fullPath, 0, 1) === '/') {
                $this->setRoot($root);
                $this->setPathExt($fullPath);
            } else {
                // The root was part of the path, but wasn't a directory.
                $this->setRoot('');
                $this->setPathExt($root.$fullPath);
            }
        } else {
            $this->setRoot('');
            $this->setPathExt($fullPath);
        }

        return $this;
    }

    /**
     * Gets the port.
     *
     * @return int Returns the port.
     */
    public function getPort() {
        return (int)$this->env['SERVER_PORT'];
    }

    /**
     * Sets the port.
     *
     * Setting the port to 80 or 443 will also set the scheme to http or https respectively.
     *
     * @param int $port The port to set.
     * @return Request Returns $this for fluent calls.
     */
    public function setPort($port) {
        $this->env['SERVER_PORT'] = $port;

        // Override the scheme for standard ports.
        if ($port === 80) {
            $this->setScheme('http');
        } elseif ($port === 443) {
            $this->setScheme('https');
        }

        return $this;
    }

    /**
     * Get an item from the query string array.
     *
     * @param string|null $key Either a string key or null to get the entire array.
     * @param mixed|null $default The default to return if {@link $key} is not found.
     * @return string|array|null Returns the query string value or the query string itself.
     * @see Request::setQuery()
     */
    public function getQuery($key = null, $default = null) {
        if ($key === null) {
            return $this->env['QUERY'];
        }
        return isset($this->env['QUERY'][$key]) ? $this->env['QUERY'][$key] : $default;
    }

    /**
     * Set an item from the query string array.
     *
     * @param string|array $key Either a string key or an array to set the entire query string.
     * @param mixed|null $value The value to set.
     * @return Request Returns $this for fluent call.
     * @throws \InvalidArgumentException Throws an exception when {@link $key is invalid}.
     * @see Request::getQuery()
     */
    public function setQuery($key, $value = null) {
        if (is_string($key)) {
            $this->env['QUERY'][$key] = $value;
        } elseif (is_array($key)) {
            $this->env['QUERY'] = $key;
        } else {
            throw new \InvalidArgumentException("Argument 1 must be a string or array.", 422);
        }
        return $this;
    }

    /**
     * Get an item from the input array.
     *
     * @param string|null $key Either a string key or null to get the entire array.
     * @param mixed|null $default The default to return if {@link $key} is not found.
     * @return string|array|null Returns the query string value or the input array itself.
     */
    public function getInput($key = null, $default = null) {
        if ($key === null) {
            return $this->env['INPUT'];
        }
        return isset($this->env['INPUT'][$key]) ? $this->env['INPUT'][$key] : $default;
    }

    /**
     * Set an item from the input array.
     *
     * @param string|array $key Either a string key or an array to set the entire input.
     * @param mixed|null $value The value to set.
     * @return Request Returns $this for fluent call.
     * @throws \InvalidArgumentException Throws an exception when {@link $key is invalid}.
     */
    public function setInput($key, $value = null) {
        if (is_string($key)) {
            $this->env['INPUT'][$key] = $value;
        } elseif (is_array($key)) {
            $this->env['INPUT'] = $key;
        } else {
            throw new \InvalidArgumentException("Argument 1 must be a string or array.", 422);
        }
        return $this;
    }

    /**
     * Gets the query on input depending on the http method.
     *
     * @param string|null $key Either a string key or null to get the entire array.
     * @param mixed|null $default The default to return if {@link $key} is not found.
     * @return mixed|array Returns the value at {@link $key} or the entire array.
     * @see Request::setData()
     * @see Request::getInput()
     * @see Request::getQuery()
     * @see Request::hasInput()
     */
    public function getData($key = null, $default = null) {
        if ($this->hasInput()) {
            return $this->getInput($key, $default);
        } else {
            return $this->getQuery($key, $default);
        }
    }

    /**
     * Sets the query on input depending on the http method.
     *
     * @param string|array $key Either a string key or an array to set the entire data.
     * @param mixed|null $value The value to set.
     * @return Request Returns $this for fluent call.
     * @see Request::getData()
     * @see Request::setInput()
     * @see Request::setQuery()
     * @see Request::hasInput()
     */
    public function setData($key, $value = null) {
        if ($this->hasInput()) {
            $this->setInput($key, $value);
        } else {
            $this->setQuery($key, $value);
        }
        return $this;
    }

    /**
     * Returns true if an http method has input (a post body).
     *
     * @param string $method The http method to test.
     * @return bool Returns true if the http method has input, false otherwise.
     */
    public function hasInput($method = '') {
        if (!$method) {
            $method = $this->getMethod();
        }

        switch (strtoupper($method)) {
            case self::METHOD_GET:
            case self::METHOD_DELETE:
            case self::METHOD_HEAD:
            case self::METHOD_OPTIONS:
                return false;
        }
        return true;
    }

    /**
     * Get the root directory (SCRIPT_NAME) of the request.
     *
     * @return Returns the root directory of the request as a string.
     * @see Request::setRoot()
     */
    public function getRoot() {
        return (string)$this->env['SCRIPT_NAME'];
    }

    /**
     * Set the root directory (SCRIPT_NAME) of the request.
     *
     * This method will modify the set root to include a leading slash if it does not have one.
     * A root of just "/" will be coerced to an empty string.
     *
     * @param string $root The new root directory.
     * @return Request Returns $this for fluent calls.
     * @see Request::getRoot()
     */
    public function setRoot($root) {
        $value = trim($root, '/');
        if ($value) {
            $value = '/'.$value;
        }
        $this->env['SCRIPT_NAME'] = $value;
        return $this;
    }

    /**
     * Get the request scheme.
     *
     * The request scheme is usually http or https.
     *
     * @return string Retuns the scheme.
     * @see Request::setScheme()
     */
    public function getScheme() {
        return (string)$this->env['URL_SCHEME'];
    }

    /**
     * Set the request scheme.
     *
     * The request scheme is usually http or https.
     *
     * @param string $scheme The new scheme to set.
     * @return Request Returns $this for fluent calls.
     * @see Request::getScheme()
     */
    public function setScheme($scheme) {
        $this->env['URL_SCHEME'] = $scheme;
        return $this;
    }

    /**
     * Get the full url of the request.
     *
     * @return string Returns the full url of the request.
     * @see Request::setUrl()
     */
    public function getUrl() {
        $query = $this->getQuery();
        return
            $this->getScheme().
            '://'.
            $this->getHostAndPort().
            $this->getRoot().
            $this->getPath().
            (!empty($query) ? '?'.http_build_query($query) : '');
    }

    /**
     * Set the full url of the request.
     *
     * @param string $url The new url.
     * @return Request $this Returns $this for fluent calls.
     * @see Request::getUrl()
     */
    public function setUrl($url) {
        // Parse the url and set the individual components.
        $url_parts = parse_url($url);

        if (isset($url_parts['scheme'])) {
            $this->setScheme($url_parts['scheme']);
        }

        if (isset($url_parts['host'])) {
            $this->setHost($url_parts['host']);
        }

        if (isset($url_parts['port'])) {
            $this->setPort($url_parts['port']);
        } elseif (isset($url_parts['scheme'])) {
            $this->setPort($this->getScheme() === 'https' ? 443 : 80);
        }

        if (isset($url_parts['path'])) {
            $path = $url_parts['path'];

            $this->setFullPath($path);
        }

        if (isset($url_parts['query']) && is_string($url_parts['query'])) {
            parse_str($url_parts['query'], $query);
            $this->setQuery($query);
        }
        return $this;
    }

    /**
     * Construct a url on the current site.
     *
     * @param string $path The path of the url.
     * @param mixed $domain Whether or not to include the domain. This can be one of the following.
     * - false: The domain will not be included.
     * - true: The domain will be included.
     * - http: Force http.
     * - https: Force https.
     * - //: A schemeless domain will be included.
     * - /: Just the path will be returned.
     * @return string Returns the url.
     */
    public function makeUrl($path, $domain = false) {
        if (!$path) {
            $path = $this->getPath();
        }

        // Check for a specific scheme.
        $scheme = $this->getScheme();
        if ($domain === 'http' || $domain === 'https') {
            $scheme = $domain;
            $domain = true;
        }

        if ($domain === true) {
            $prefix = $scheme.'://'.$this->getHostAndPort().$this->getRoot();
        } elseif ($domain === false) {
            $prefix = $this->getRoot();
        } elseif ($domain === '//') {
            $prefix = '//'.$this->getHostAndPort().$this->getRoot();
        } else {
            $prefix = '';
        }

        return $prefix.'/'.ltrim($path, '/');
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        return $this->env;
    }
}
