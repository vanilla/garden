<?php

namespace Garden;

use Garden\Exception\NotFoundException;

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
     * @param Request $request The {@link Request} to match against.
     * @return array An array of arrays corresponding to matching routes and their args.
     */
    public function matchRoutes(Request $request) {
        $result = array();

        foreach ($this->routes as $route) {
            $matches = $route->matches($request, $this);
            if ($matches) {
                $result[] = array($route, $matches);
            }
        }
        return $result;
    }

    /**
     * Add a new route.
     *
     * @param string|Route $pathOrRoute The path to the route or the {@link Route} object itself.
     * @param callable|string|null $callback Either a callback to map the route to or a string representing
     * a format for {@link sprintf()}.
     * @return Route Returns the route that was added.
     * @throws \InvalidArgumentException Throws an exceptio if {@link $path} isn't a string or {@link Route}.
     */
    public function route($pathOrRoute, $callback = null) {
        if (is_object($pathOrRoute) && $pathOrRoute instanceof Route) {
            $route = $pathOrRoute;
        } elseif (is_string($pathOrRoute) && $callback !== null) {
            $route = Route::create($pathOrRoute, $callback);
        } else {
            throw new \InvalidArgumentException("Argument #1 must be either a Garden\\Route or a string.", 500);
        }
        $this->routes[] = $route;
        return $route;
    }

    /**
     * Route to a GET request.
     *
     * @param string $pattern The url pattern to match.
     * @param callable $callback The callback to execute on the route.
     * @return CallbackRoute Returns the new route.
     */
    public function get($pattern, callable $callback) {
        return $this->route($pattern, $callback)->methods('GET');
    }

    /**
     * Route to a POST request.
     *
     * @param string $pattern The url pattern to match.
     * @param callable $callback The callback to execute on the route.
     * @return CallbackRoute Returns the new route.
     */
    public function post($pattern, callable $callback) {
        return $this->route($pattern, $callback)->methods('POST');
    }

    /**
     * Route to a PUT request.
     *
     * @param string $pattern The url pattern to match.
     * @param callable $callback The callback to execute on the route.
     * @return CallbackRoute Returns the new route.
     */
    public function put($pattern, callable $callback) {
        return $this->route($pattern, $callback)->methods('PUT');
    }

    /**
     * Route to a PATCH request.
     *
     * @param string $pattern The url pattern to match.
     * @param callable $callback The callback to execute on the route.
     * @return CallbackRoute Returns the new route.
     */
    public function patch($pattern, callable $callback) {
        return $this->route($pattern, $callback)->methods('PATCH');
    }

    /**
     * Route to a DELETE request.
     *
     * @param string $pattern The url pattern to match.
     * @param callable $callback The callback to execute on the route.
     * @return CallbackRoute Returns the new route.
     */
    public function delete($pattern, callable $callback) {
        return $this->route($pattern, $callback)->methods('DELETE');
    }

    /**
     * Run the application against a {@link Request}.
     *
     * @param Request|null $request A {@link Request} to run the application against or null to run against a request
     * on the current environment.
     * @return mixed Returns a response appropriate to the request's ACCEPT header.
     */
    public function run(Request $request = null) {
        if ($request === null) {
            $request = new Request();
        }
        $this->request = $request;
        $requestBak = Request::current($request);

        // Grab all of the matched routes.
        $routes = $this->matchRoutes($this->request);

        // Try all of the matched routes in turn.
        $dispatched = false;
        $result = null;
        try {
            foreach ($routes as $route_args) {
                list($route, $args) = $route_args;

                try {
                    // Dispatch the first matched route.
                    ob_start();
                    $response = $route->dispatch($request, $args);
                    $body = ob_get_clean();

                    $result = [
                        'routing' => $args,
                        'response' => $response,
                        'body' => $body
                    ];

                    // Once a route has been successfully dispatched we break and don't dispatch anymore.
                    $dispatched = true;
                    break;
                } catch (Exception\Pass $pex) {
                    // If the route throws a pass then continue on to the next route.
                    continue;
                }
            }

            if (!$dispatched) {
                throw new NotFoundException();
            }
        } catch (\Exception $ex) {
            $result = $ex;
        }

        Request::current($requestBak);

        return $this->finalize($result);
    }

    /**
     * Finalize the result from a dispatch.
     *
     * @param mixed $result The result of the dispatch.
     * @return mixed Returns relevant debug data or processes the response.
     * @throws \Exception Throws an exception when finalizing internal content types and the result is an exception.
     */
    protected function finalize($result) {
        $response = Response::create($result);
        $response->meta(['request' => $this->request], true);
        $response->contentTypeFromAccept($this->request->getEnv('HTTP_ACCEPT'));
        $response->contentAsset($this->request->getEnv('HTTP_X_ASSET'));

        $contentType = $response->contentType();

        // Check for known response types.
        switch ($contentType) {
            case 'application/internal':
                if ($result instanceof \Exception) {
                    throw $result;
                }

                if ($response->contentAsset() === 'response') {
                    return $response;
                } else {
                    return $response->jsonSerialize();
                }
                // No break because everything returns.
            case 'application/json':
                $response->flushHeaders();
                echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                break;
            default:
                $response->status(415);
                $response->flushHeaders();
                echo "Unsupported response type: $contentType";
                break;
        }
        return null;
    }
}
