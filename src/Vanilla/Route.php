<?php namespace Vanilla;

abstract class Route {
    /// Properties ///

    protected $pattern;

    /// Methods ///

    /**
     * Create and return a new route.
     *
     * @param string $pattern The pattern for the route.
     * @param mixed $callback
     * @return \Vanilla\Route Returns the new route.
     */
    public static function create($pattern, $callback) {
        if (is_callable($callback)) {
            $route = new UrlRoute($pattern, $callback);
        } else {
            $route = new ControllerRoute($pattern, $callback); // callback is ControllerRoute->controllers()
        }
        return $route;
    }

    /**
     * Dispatch the route.
     *
     * @param array $args The args to pass to the dispatch.
     * These are the arguments returned from {@link Route::matches()}.
     */
    public abstract function dispatch(array $args);

    /**
     * Try matching a route to a request.
     * @return array|null Whether or not the route matches the request.
     * If the route matches an array of args is returned, otherwise the function returns null.
     */
    public abstract function matches(\Vanilla\Request $request);

    public function pattern($pattern = null) {
        if ($pattern !== null) {
            $this->pattern = '/'.ltrim($pattern, '/');
        }
        return $this->pattern;
    }
}

