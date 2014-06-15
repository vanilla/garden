<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license MIT
 * @since 1.0
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
     * @param mixed $input The request input. This is the query string for GET requests or the body for other requests.
     */
    public function __construct($url = '', $method = '', $input = null) {
        if ($url) {
            $this->env = static::defaultEnvironment();
            // Instantiate the request from the url.
            $this->url($url);
            if ($method) {
                $this->method($method);
            }
            if ($input) {
                $this->input($input);
            }
        } else {
            // Instantiate the request from the global environment.
            $this->env = static::globalEnvironment();
            if ($method) {
                $this->method($method);
            }
            if ($input) {
                $this->input($input);
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
        return $this->url();
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
     * @param string|null $key Specifies a specific key in the environment array.
     * @param string|null $value Update the value at {@link $key} in the environment array.
     * @return array|mixed Returns the value at {@link $key} or the entire environment array.
     */
    public static function defaultEnvironment($key = null, $value = null) {
        if (self::$defaultEnv === null) {
            self::$defaultEnv = array(
                'REQUEST_METHOD' => 'GET',
                'SCRIPT_NAME' => '',
                'PATH_INFO' => '/',
                'EXT' => '',
                'QUERY' => array(),
                'SERVER_NAME' => 'localhost',
                'SERVER_PORT' => 80,
                'ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'ACCEPT_LANGUAGE' => 'en-US,en;q=0.8',
                'ACCEPT_CHARSET' => 'ISO-8859-1,utf-8;q=0.7,*;q=0.3',
                'USER_AGENT' => 'Vanilla Framework',
                'REMOTE_ADDR' => '127.0.0.1',
                'URL_SCHEME' => 'http',
                'INPUT' => array(),
            );
        }

        if ($key !== null && $value !== null) {
            self::$defaultEnv[$key] = $value;
            return self::$defaultEnv;
        } elseif ($value !== null) {
            return val($key, self::$defaultEnv);
        } else {
            return self::$defaultEnv;
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
            $env['SERVER_NAME'] = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? val('HTTP_X_FORWARDED_HOST', $_SERVER) : (isset($_SERVER['HTTP_HOST']) ? val('HTTP_HOST', $_SERVER) : val('SERVER_NAME', $_SERVER));

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

            // SERVER_PORT.
            if (isset($_SERVER['SERVER_PORT'])) {
                $server_port = (int) $_SERVER['SERVER_PORT'];
            } elseif ($url_scheme === 'https') {
                $server_port = 443;
            } else {
                $server_port = 80;
            }
            $env['SERVER_PORT'] = $server_port;

            $env['URL_SCHEME'] = $url_scheme;

            // INPUT: The entire input.
            // Input stream (readable one time only; not available for multipart/form-data requests)
            switch ($env['CONTENT_TYPE']) {
                case 'application/json':
                    $raw_input = @file_get_contents('php://input');
                    $input = @json_decode($raw_input, true);
                break;
            }
            if (isset($input)) {
                $env['INPUT'] = $input;
                $env['RAW_INPUT'] = $raw_input;
            } elseif (isset($_POST)) {
                $env['INPUT'] = $_POST;
            }

            if (isset($raw_input)) {
                $env['RAW_INPUT'] = $raw_input;
            }

            // IP Address.
            // Load balancers set a different ip address.
            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? val('HTTP_X_FORWARDED_FOR', $_SERVER) : val('REMOTE_ADDR', $_SERVER, '127.0.0.1');
            if (strpos($ip, ',') !== false) {
                $ip = substr($ip, 0, strpos($ip, ','));
            }
            // Varnish
            $original_ip = val('HTTP_X_ORIGINALLY_FORWARDED_FOR', $_SERVER, null);
            if (!is_null($original_ip)) {
                $ip = $original_ip;
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
            if (!in_array($method, $getMethods) || in_array($method, $getMethods)) {
                static::replaceEnv($env, 'REQUEST_METHOD', $method);
                unset($get['REQUEST_METHOD']);
            }
        }

        // Check to override the accepts header.
        switch (strtolower($env['EXT'])) {
            case '.json':
                static::replaceEnv($env, 'ACCEPT', 'application/json');
                break;
            case '.rss':
                static::replacEEnv($env, 'ACCEPT', 'application/rss+xml');
                break;
        }
    }

    /**
     * Gets a value from the environment.
     *
     * @param string $key The key to inspect.
     * @return mixed The environment value.
     */
    public function env($key) {
        $key = strtoupper($key);
        if (isset($this->env[$key])) {
            return $this->env[$key];
        }
        return null;
    }

    /**
     * Replace an environment variable with another one and back up the old one in a *_RAW key.
     *
     * @param array $env The environment array.
     * @param string $key The environment key
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
     * @param array $env The environment array.
     * @param string $key The environment key
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
     * An alias of {@link query}. Get an item from the query.
     *
     * @param string|null $key The key to get or null to get the entire array.
     * @param string|null $default The default value if getting a particular {@link $key}.
     * @return string|array Gets the value at {@link $key} or the entire array.
     */
    public function get($key = null, $default = null) {
        return $this->query($key, $default);
    }

    public function host($host = null) {
        if ($host !== null) {
            $this->env['SERVER_NAME'] = $host;
        }
        return $this->env['SERVER_NAME'];
    }

    public function hostAndPort() {
        $host = $this->host();
        $port = $this->port();

        // Only append the port if it is non-standard.
        if (($port == 80 && $this->scheme() === 'http') || ($port == 443 && $this->scheme() === 'https')) {
            $port = '';
        } else {
            $port = ':'.$port;
        }
        return $host.$port;
    }

    public function ip($ip = null) {
        if ($ip !== null)
            $this->env['REMOTE_ADDR'] = $ip;
        $this->env['REMOTE_ADDR'];
    }

    public function isDelete() {
        return $this->method() === self::METHOD_DELETE;
    }

    public function isGet() {
        return $this->method() === self::METHOD_GET;
    }

    public function isHead() {
        return $this->method() === self::METHOD_HEAD;
    }

    public function isOptions() {
        return $this->method() === self::METHOD_OPTIONS;
    }

    public function isPatch() {
        return $this->method() === self::METHOD_PATCH;
    }

    public function isPost() {
        return $this->method() === self::METHOD_POST;
    }

    public function isPut() {
        return $this->method() === self::METHOD_PUT;
    }

    public function method($method = null) {
        if ($method !== null) {
            $this->env['REQUEST_METHOD'] = strtoupper($method);
            return $this;
        }
        return $this->env['REQUEST_METHOD'];
    }

    public function path($path = null) {
        if ($path !== null) {
            $this->env['PATH_INFO'] = $path;
            return $this;
        }

        return $this->env['PATH_INFO'];
    }

    public function ext($ext = null) {
        if ($ext !== null) {
            if ($ext) {
                $this->env['EXT'] = '.'.ltrim($ext, '.');
            } else {
                $this->env['EXT'] = '';
            }
            return $this;
        }

        return $this->env['EXT'];
    }

    public function fullPath($path = null) {
        if ($path !== null) {
            // Strip the extension from the path.
            if (substr($path, -1) !== '/' && ($pos = strrpos($path, '.')) !== false) {
                $ext = substr($path, $pos);
                $path = substr($path, 0, $pos);
                $this->env['EXT'] = $ext;
            }
            $this->env['PATH_INFO'] = $path;
            return $this;
        }

        return $this->env['PATH_INFO'].$this->env['EXT'];
    }

    public function port($port = null) {
        if ($port !== null) {
            $this->env['SERVER_PORT'] = $port;
            return $this;
        }
        return $this->env['SERVER_PORT'];
    }

    /**
     * Get an item from the query or the entire query array.
     *
     * @param string|null $key The key to get or null to get the entire array.
     * @param string|null $default The default value if getting a particular {@link $key}.
     * @return string|array Gets the value at {@link $key} or the entire array.
     */
    public function query($key = null, $default = null) {
        if ($key === null) {
            return $this->env['QUERY'];
        }
        if (is_string($key)) {
            return isset($this->env['QUERY'][$key]) ? $this->env['QUERY'][$key] : $default;
        }
        if (is_array($key)) {
            $this->env['QUERY'] = $key;
        }
    }

    public function input($key = null, $default = null) {
        if ($key === null) {
            return $this->env['INPUT'];
        }
        if (is_string($key)) {
            return isset($this->env['INPUT'][$key]) ? $this->env['INPUT'][$key] : $default;
        }
        if (is_array($key)) {
            $this->env['INPUT'] = $key;
        }
    }

    public function root($value = null) {
        if ($value !== null) {
            $value = rtrim($value, '/');
            $this->env['SCRIPT_NAME'] = $value;
        }
        return $this->env['SCRIPT_NAME'];
    }

    public function scheme($value = null) {
        if ($value !== null) {
            $this->env['URL_SCHEME'] = $value;
        }
        return $this->env['URL_SCHEME'];
    }

    public function url($url = null) {
        if ($url !== null) {
            // Parse the url and set the individual components.
            $url_parts = parse_url($url);

            if (isset($url_parts['scheme'])) {
                $this->scheme($url_parts['scheme']);
            }

            if (isset($url_parts['host'])) {
                $this->host($url_parts['host']);
            }

            if (isset($url_parts['port'])) {
                $this->port($url_parts['port']);
            } elseif (isset($url_parts['scheme'])) {
                $this->port($this->scheme() === 'https' ? 443 : 80);
            }

            if (isset($url_parts['path'])) {
                $path = $url_parts['path'];

                // Try stripping the root out of the path first.
                $root = static::globalEnvironment('SCRIPT_NAME');

                if (strpos($path, $root) === 0) {
                    $path = substr($path, strlen($root));

                    if (substr($path, 0, 1) === '/') {
                        // The root was part of the path, but wasn't a directory.
                        $this->root($root);
                        $this->path($path);
                    } else {
                        $this->root('');
                        $this->path($root.$path);
                    }
                } else {
                    $this->root('');
                    $this->path($path);
                }
            }

            if (isset($url_parts['query'])) {
                parse_str($url_parts['query'], $query);
                $this->query($query);
            }
        } else {
            $query = $this->query();
            return $this->scheme() . '://' . $this->host() . $this->root() . $this->path() . (!empty($query) ? '?' . http_build_query($query) : '');
        }
    }

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        return $this->env;
    }
}
