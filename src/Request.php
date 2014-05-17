<?php namespace Garden;

if (!defined('APP'))
    return;

/**
 * HTTP Request representation.
 *
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009 Vanilla Forums Inc.
 * @license LGPL-2.1
 * @package Vanilla
 * @since 1.0
 */
class Request {

    /// Constants ///
    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';
    const METHOD_OPTIONS = 'OPTIONS';
    const P = '_p';

    /// Properties ///

    /**
     *
     * @var array The data in this request.
     */
    protected $env;

    /**
     * @var array The global enviroment for the request.
     */
    protected static $globalEnv = null;

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

    public function __construct($url = null, $method = null, $input = null) {
        if ($url) {
            $this->env = static::defaultEnvironment();
            // Instantiate the request from the url.
            $this->url($url);
            if ($method)
                $this->method($method);
            if ($input) {
                $this->input($input);
            }
        } else {
            // Instantiate the request from the global environment.
            $this->env = static::globalEnvironment();
            if ($method)
                $this->method($method);
            if ($input)
                $this->input($input);
        }
    }

    public function __toString() {
        return $this->url();
    }

    public static function defaultEnvironment() {
        $defaults = array(
            'REQUEST_METHOD' => 'GET',
            'SCRIPT_NAME' => '',
            'PATH_INFO' => '/',
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
        return $defaults;
    }

    /**
     * Parse request information from the current environment.
     * The environment contains keys that support the Rack protocal (see http://rack.rubyforge.org/doc/SPEC.html).
     *
     * @param mixed $key The environment variable to look at.
     * - null: Return the entire environment.
     * - true: Force a reparse of the environment and return the entire environment.
     * - string: One of the environment variables.
     */
    public static function globalEnvironment($key = null) {
        // Check to parse the environment.
        if ($key === true || ($key === null && !isset(self::$globalEnv))) {
            $env = array();

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
            $get = $_GET;
            if (isset($get[self::P])) {
                $path = $get[self::P];
                unset($get[self::P]);
            } else {
                $path = '/';
            }
            $env['PATH_INFO'] = '/' . ltrim($path, '/');

            // QUERY.
            $env['QUERY'] = $get;

            // SERVER_NAME.
            $env['SERVER_NAME'] = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? val('HTTP_X_FORWARDED_HOST', $_SERVER) : (isset($_SERVER['HTTP_HOST']) ? val('HTTP_HOST', $_SERVER) : val('SERVER_NAME', $_SERVER));

            // SERVER_PORT.
            if (isset($_SERVER['SERVER_PORT']))
                $server_port = (int) $_SERVER['SERVER_PORT'];
            elseif ($Scheme === 'https')
                $server_port = 443;
            else
                $server_port = 80;
            $env['SERVER_PORT'] = $server_port;

            // HTTP_* headers.
            $env = array_replace($env, static::extractHeaders($_SERVER));

            // URL_SCHEME.
            $url_scheme = 'http';
            // Webserver-originated SSL.
            if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
                $url_scheme = 'https';
            // Loadbalancer-originated (and terminated) SSL.
            if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
                $url_scheme = 'https';
            // Varnish modifies the scheme.
            $org_protical = val('HTTP_X_ORIGINALLY_FORWARDED_PROTO', $_SERVER, NULL);
            if (!is_null($org_protical))
                $url_scheme = $org_protical;

            $env['URL_SCHEME'] = $url_scheme;

            // INPUT: The entire input.
            // Input stream (readable one time only; not available for multipart/form-data requests)
            $raw_input = @file_get_contents('php://input');
            if (!$raw_input) {
                $raw_input = '';
            }
            $env['INPUT'] = $raw_input;

            // IP Address.
            // Loadbalancers set a different ip address.
            $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? val('HTTP_X_FORWARDED_FOR', $_SERVER) : $_SERVER['REMOTE_ADDR'];
            if (strpos($ip, ',') !== FALSE)
                $ip = substr($ip, 0, strpos($ip, ','));
            // Varnish
            $original_ip = val('HTTP_X_ORIGINALLY_FORWARDED_FOR', $_SERVER, NULL);
            if (!is_null($original_ip))
                $ip = $original_ip;

            // Make sure we have a valid ip.
            if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m))
                $ip = $m[1];
            elseif ($ip === '::1')
                $ip = '127.0.0.1';
            else
                $ip = '0.0.0.0'; // unknown ip

            $env['REMOTE_ADDR'] = $ip;

            self::$globalEnv = $env;
        }

        if ($key) {
            return val($key, self::$globalEnv);
        }
        return self::$globalEnv;
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

    public function get($key = null, $default = null) {
        return $this->query($key, $null);
    }

    public function host($host = null) {
        if ($host !== null)
            $this->env['SERVER_NAME'] = $host;
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
        }
        return $this->env['REQUEST_METHOD'];
    }

    public function path($path = null) {
        if ($path !== null) {
            $this->env['PATH_INFO'] = $path;
        }

        return $this->env['PATH_INFO'];
    }

    public function port($port = null) {
        if ($port !== null)
            $this->env['SERVER_PORT'] = $port;
        return $this->env['SERVER_PORT'];
    }

    public function query($key = null, $default = null) {
        if ($key === null)
            return $this->env['QUERY'];
        if (is_string($key))
            return isset($this->env['QUERY'][$key]) ? $this->env['QUERY'][$key] : $default;
        if (is_array($key))
            $this->env['QUERY'] = $key;
    }

    public function input($key = null, $default = null) {
        if ($key === null)
            return $this->env['INPUT'];
        if (is_string($key))
            return isset($this->env['INPUT'][$key]) ? $this->env['INPUT'][$key] : $default;
        if (is_array($key))
            $this->env['INPUT'] = $key;
    }

    public function root($value = null) {
        if ($value !== null) {
            $value = rtrim($value, '/');
            $this->env['SCRIPT_NAME'] = $value;
        }
        return $this->env['SCRIPT_NAME'];
    }

    public function scheme($value = null) {
        if ($value !== null)
            $this->env['URL_SCHEME'] = $value;
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
}