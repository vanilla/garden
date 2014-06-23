<?php namespace Garden;

abstract class Route {
    /// Constants ///

    const MAP_QUERY = 'query'; // map to the querystring.
    const MAP_INPUT = 'input'; // map to the input (post).
    const MAP_DATA = 'data'; // map to the querystring or input depending on the method.

    /// Properties ///

    /**
     * @var array[string] An array of allowed http methods for this route.
     */
    protected $methods;

    protected $pattern;

    /**
     * @var array An array of parameter conditions.
     */
    protected $conditions;

    /**
     * @var array An array of global parameter conditions.
     */
    protected static $globalConditions;

    /**
     * @var array An array of parameter mappings.
     */
    protected $mappings;

    /**
     * @var array An array of global parameter mappings.
     */
    protected static $globalMappings = [
        'data' => Route::MAP_DATA,
        'query' => Route::MAP_QUERY,
        'input' => Route::MAP_INPUT
    ];

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
            $route = new CallbackRoute($pattern, $callback);
        } else {
            $route = new ResourceRoute($pattern, $callback);
        }
        return $route;
    }

    /**
     * Dispatch the route.
     *
     * @param Request $request The current request we are dispatching against.
     * @param array &$args The args to pass to the dispatch.
     * These are the arguments returned from {@link Route::matches()}.
     */
    abstract public function dispatch(Request $request, array &$args);

    /**
     * Gets or sets the route's conditions.
     *
     * @param array|null $conditions An array of conditions to set.
     * @return Route|array
     */
    public function conditions($conditions = null) {
        if ($this->conditions === null) {
            $this->conditions = [];
        }

        if (is_array($conditions)) {
            $conditions = array_change_key_case($conditions);

            $this->conditions = array_replace(
                $this->conditions,
                array_map([__CLASS__, 'makeRegex'], $conditions)
            );
            return $this;
        }

        return $this->conditions;
    }

    /**
     * Gets or sets the allowed http methods for this route.
     *
     * @param array|string|null $methods Set a new set of allowed methods or pass null to get the current methods.
     * @return Route|array Returns the current methods or `$this` for fluent calls.
     */
    public function methods($methods = null) {
        if ($methods === null) {
            return $this->methods;
        }

        $this->methods = array_map('strtoupper', (array)$methods);
        return $this;
    }

    /**
     * Gets/sets the global conditions.
     *
     * @param array|null $conditions An array of conditions to set.
     * @return array The current global conditions.
     */
    public static function globalConditions($conditions = null) {
        if (self::$globalConditions === null) {
            self::$globalConditions = [];
        }

        if (is_array($conditions)) {
            $conditions = array_change_key_case($conditions);

            self::$globalConditions = array_replace(
                self::$globalConditions,
                array_map([__CLASS__, 'makeRegex'], $conditions)
            );
        }

        return self::$globalConditions;
    }

    /**
     * Gets or sets the mappings array that maps parameter names to mappings.
     *
     * @param array|null $mappings An array of mappings to set.
     * @return Route|array Returns the current mappings or `$this` for fluent calls.
     */
    public function mappings($mappings = null) {
        if ($this->mappings === null) {
            $this->mappings = [];
        }

        if (is_array($mappings)) {
            $mappings = array_change_key_case($mappings);

            $this->mappings = array_replace(
                $this->mappings,
                $mappings
            );
            return $this;
        }

        return $this->mappings;
    }

    /**
     * Gets or sets the global mappings array that maps parameter names to mappings.
     *
     * @param array|null $mappings An array of mappings to set.
     * @return array Returns the current global mappings.
     */
    public static function globalMappings($mappings = null) {
        if (self::$globalMappings === null) {
            self::$globalMappings = [];
        }

        if (is_array($mappings)) {
            $mappings = array_change_key_case($mappings);

            self::$globalMappings = array_replace(
                self::$globalMappings,
                $mappings
            );
        }

        return self::$globalMappings;
    }

    protected function isMapped($name) {
        $name = strtolower($name);
        return isset($this->mappings[$name]) || isset(self::$globalMappings[$name]);
    }

    protected function mappedData($name, Request $request) {
        $name = strtolower($name);

        if (isset($this->mappings[$name])) {
            $mapping = $this->mappings[$name];
        } elseif (isset(self::$globalMappings[$name])) {
            $mapping = self::$globalMappings[$name];
        } else {
            return null;
        }

        switch (strtolower($mapping)) {
            case self::MAP_DATA:
                switch ($request->method()) {
                    case Request::METHOD_GET:
                    case Request::METHOD_DELETE:
                    case Request::METHOD_HEAD:
                    case Request::METHOD_OPTIONS:
                        $result = $request->query();
                        break;
                    default:
                        $result = $request->input();
                        break;
                }
                break;
            case self::MAP_INPUT:
                $result = $request->input();
                break;
            case self::MAP_QUERY:
                $result = $request->query();
                break;
            default:
                return null;
        }
        return $result;
    }

    /**
     * Make a string into a regex pattern.
     *
     * This method uses the convention where a string that starts with a backtick (`) is assumed to be a regex.
     * Otherwise, backticks are added.
     *
     * @param string $str The string to make.
     * @return string Returns the string as a regex pattern.
     */
    protected static function makeRegex($str) {
        if ($str[0] === '`') {
            return $str;
        } else {
            return '`^'.$str.'$`';
        }
    }

    /**
     * Try matching a route to a request.
     *
     * @param Request $request The request to match the route with.
     * @param Application $app The application instantiating the route.
     * @return array|null Whether or not the route matches the request.
     * If the route matches an array of args is returned, otherwise the function returns null.
     */
    abstract public function matches(Request $request, Application $app);

    /**
     * Tests whether or not a route matches the allowed methods for this route.
     *
     * @param Request $request The request to test.
     * @return bool Returns `true` if the route allows the method, otherwise `false`.
     */
    protected function matchesMethods(Request $request) {
        if (empty($this->methods)) {
            return true;
        }
        return in_array($request->method(), $this->methods);
    }

    /**
     * Tests whether an argument fails against a condition.
     *
     * @param string $name The name of the parameter.
     * @param string $value The value of the argument.
     * @return bool|null Returns one of the following:
     * - true: The condition fails.
     * - false: The condition passes.
     * - null: There is no condition.
     */
    protected function failsCondition($name, $value) {
        $name = strtolower($name);
        if (isset($this->conditions[$name])) {
            return !preg_match($this->conditions[$name], $value);
        }

        if (isset(self::$globalConditions[$name])) {
            return !preg_match(self::$globalConditions[$name], $value);
        }

        return null;
    }

    /**
     * Gets or sets the route pattern.
     *
     * @param string|null $pattern The route pattern.
     * @return string Returns the pattern.
     */
    public function pattern($pattern = null) {
        if ($pattern !== null) {
            $this->pattern = '/'.ltrim($pattern, '/');
        }
        return $this->pattern;
    }
}
