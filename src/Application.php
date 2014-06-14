<?php

namespace Garden;

class Application {
    /// Properties ///
    protected static $instances;

    /**
     * @var Request The current request.
     */
    public $request;

    /**
     *
     * @var Response The current response.
     */
    public $response;

    /**
     * @var array An array of route objects.
     */
    protected $routes;

    /// Methods ///

    public function __construct($name = 'default') {
        $this->routes = array();

        self::$instances[$name] = $this;
    }

    public static function instance($name = 'default') {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new Application($name);
        }
        return self::$instances[$name];
    }

    /**
     * Get all of the matched routes for a request.
     *
     * @param \Garden\Request $request
     * @return array An array of arrays corresponding to matching routes and their args.
     */
    public function matchRoutes(\Garden\Request $request) {
        $result = array();

        foreach ($this->routes as $route) {
            $matches = $route->matches($request, $this);
            if ($matches)
                $result[] = array($route, $matches);
        }
        return $result;
    }

    /**
     * Add a new route.
     *
     * @param string|Route $path The path to the route or the {@link Route} object itself.
     * @param mixed $callback
     * @return Route Returns the route that was added.
     */
    public function route($path, $callback = null) {
        if (is_a($path, '\Garden\Route')) {
            $route = $path;
        } else {
            $route = Route::create($path, $callback);
        }
        $this->routes[] = $route;
        return $route;
    }

    public function run(Request $request = null) {
        if ($request === null) {
            $request = new Request();
        }
        $this->request = $request;
        $requestBak = Request::current();

        // Grab all of the matched routes.
        $routes = $this->matchRoutes($this->request);

        // Try all of the matched routes in turn.
        foreach ($routes as $route_args) {
            list($route, $args) = $route_args;

            $dispatched = false;
            try {
                // Dispatch the first matched route.
                ob_start();
                $response = $route->dispatch($args);
                $output = ob_get_clean();

                $result = [
                    'routing' => $args,
                    'response' => $response,
                    'output' => $output
                ];

                // Once a route has been successfully dispatched we break and don't dispatch anymore.
                $dispatched = true;
                break;
            } catch (Exception\Pass $pex) {
                // If the route throws a pass then continue on to the next route.
                continue;
            }
        }

        Request::current($requestBak);

        return $this->finalize($result);
    }

    /**
     * Finalize the result from a dispatch.
     *
     * @param array $result The result of the dispatch.
     * @return mixed Returns relevant debug data or processes the response.
     */
    protected function finalize(array $result) {
        $accept = $this->request->env('accept');

        if (str_begins($accept, 'debug/')) {
            // Return debug information.
            $part = trim(strtolower(strstr($accept, '/')), '/');
            if ($part === 'all') {
                return $result;
            } elseif (isset($result[$part])) {
                return $result[$part];
            } else {
                return null;
            }
        } else {

        }
    }
}
