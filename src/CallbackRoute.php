<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;

/**
 * A route that maps urls to callbacks.
 */
class CallbackRoute extends Route {
    /// Properties ///

    /**
     *
     * @var callable The callback to call on a matching pattern.
     */
    protected $callback;

    /// Methods ///

    /**
     * Initialize an instance of the {@link CallbackRoute} class.
     *
     * @param string $pattern The pattern to match to.
     * @param callable $callback The callback to call when the url matches.
     */
    public function __construct($pattern, callable $callback) {
        $this->pattern($pattern);
        $this->callback($callback);
    }

    /**
     * Gets or sets the callback that is called on a matching pattern.
     *
     * @param \callable $callback The callback to call when the url matches.
     * @return callable|this Returns the current callback or $this for fluent sets.
     */
    public function callback(callable $callback = null) {
        if ($callback !== null) {
            $this->callback = $callback;
            return $this;
        }
        return $this->callback;
    }

    /**
     * Dispatch the matched route and call its callback.
     *
     * @param Request $request The request to dispatch.
     * @param array &$args The arguments returned from {@link CallbackRoute::dispatch()}.
     * @return mixed Returns the result of the callback.
     */
    public function dispatch(Request $request, array &$args) {
        $callback = $args['callback'];
        $callback_args = reflect_args($callback, $args['args']);

        $result = call_user_func_array($callback, $callback_args);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(Request $request, Application $app) {
        $path = $request->getPath();
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
     *
     * @param string $pattern The route pattern to convert into a regular expression.
     * @return string Returns the regex pattern for the route.
     */
    protected static function patternRegex($pattern) {
        $result = preg_replace_callback('`{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}`i', function ($match) {
            $param = $match[1];
            $param_pattern = '[^/]+';
            $result = "(?<$param>$param_pattern)";

            return $result;
        }, $pattern);

        $result = '`^'.$result.'$`i';
        return $result;
    }
}

