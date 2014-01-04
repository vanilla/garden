<?php namespace Vanilla;

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
        $this->request = new Request();
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
     * @param \Vanilla\Request $request
     * @return array An array of arrays corresponding to matching routes and their args.
     */
    public function matchRoutes(\Vanilla\Request $request) {
        $result = array();

        foreach ($this->routes as $route) {
            $matches = $route->matches($request);
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
     */
    public function route($path, $callback = null) {
        if (is_a($path, '\Vanilla\Route')) {
            $route = $path;
        } else {
            $route = Route::create($path, $callback);
        }
        $this->routes[] = $route;
        return $route;
    }

    public function run() {
        $this->request = new Request();

        // Grab all of the matched routes.
        $routes = $this->matchRoutes($this->request);

        // Try all of the matched routes in turn.
        foreach ($routes as $route_args) {
            list($route, $args) = $route_args;

            $dispatched = false;
            try {
                // Dispatch the first matched route.
                $route->dispatch($args);

                // Once a route has been successfully dispatched we break and don't dispatch anymore.
                $dispatched = true;
                break;
            } catch (\Vanilla\Exception\Pass $pex) {
                // If the route throws a pass then continue on to the next route.
                continue;
            }
        }

    }
}