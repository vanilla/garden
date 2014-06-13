<?php namespace Garden;

abstract class Route {
    /// Properties ///

    protected $methods;

    protected $pattern;

    /// Methods ///

    /**
     * Create and return a new route.
     *
     * @param string $pattern The pattern for the route.
     * @param mixed $callback
     * @return \Garden\Route Returns the new route.
     */
    public static function create($pattern, $callback) {
        if (is_callable($callback)) {
            $route = new UrlRoute($pattern, $callback);
        } elseif (str_begins($pattern, '/api/')) {
            $route = new ResourceRoute($pattern, $callback);
        } else {
            $route = new ControllerRoute($pattern, $callback); // callback is ControllerRoute->controllers()
        }
        return $route;
    }

    /**
     * Dispatch the route.
     *
     * @param array &$args The args to pass to the dispatch.
     * These are the arguments returned from {@link Route::matches()}.
     */
    abstract public function dispatch(array &$args);

    /**
     * Try matching a route to a request.
     *
     * @param Request $request The request to match the route with.
     * @param Application $app The application instantiating the route.
     * @return array|null Whether or not the route matches the request.
     * If the route matches an array of args is returned, otherwise the function returns null.
     */
    abstract public function matches(Request $request, Application $app);

    public function pattern($pattern = null) {
        if ($pattern !== null) {
            $this->pattern = '/'.ltrim($pattern, '/');
        }
        return $this->pattern;
    }
}

