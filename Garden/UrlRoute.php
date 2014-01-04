<?php

namespace Garden;

class UrlRoute extends Route {
    /// Properties ///

    /**
     *
     * @var callable The callback to call on a matching pattern.
     */
    protected $callback;

    /// Methods ///

    public function __construct($pattern, $callback) {
        $this->pattern($pattern);
        $this->callback($callback);
    }

    /**
     * Gets or sets the callback that is called on a matching pattern.
     * @param \callable $callback
     */
    public function callback($callback = null) {
        if ($callback !== null) {
            $this->callback = $callback;
        }
        return $this->callback;
    }

    public function dispatch(array $args) {
        $callback = $args['callback'];
        $callback_args = reflectArgs($callback, $args['args']);

        $result = call_user_func_array($callback, $callback_args);
        return $result;
    }

    public function matches(Request $request, Application $app) {
        $path = $request->path();
        $regex = static::patternRegex($this->pattern());

        if (preg_match($regex, $path, $matches)) {
            // This route matches so extract the args.
            $args = array();
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $args[$key] = $value;
                }
            }
            $result = array(
                'callback' => $this->callback,
                'args' => $args,
                );
            return $result;
        } else {
            return null;
        }
    }

    /**
     * Convert a path pattern into its regex.
     * @param string $pattern
     */
    protected static function patternRegex($pattern) {
        $result = preg_replace_callback('`{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}`i', function($match) {
            $param = $match[1];
            $param_pattern = '[^/]+';
            $result = "(?<$param>$param_pattern)";

            return $result;
        }, $pattern);

        $result = '`^'.$result.'$`i';
        return $result;
    }
}

