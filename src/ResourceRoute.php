<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;

use Garden\Exception\NotFoundException;
use Garden\Exception\MethodNotAllowedException;

/**
 * Maps paths to controllers that act as RESTful resources.
 *
 * The following are examples of urls that will map using this resource route.
 * - METHOD /controller/:id -> method
 * - METHOD /controller/:id/action -> methodAction
 * - GET /controller -> index
 * - METHOD /controller/action -> methodAction
 */
class ResourceRoute extends Route {
    protected $controllerPattern = '%sApiController';

    /**
     * @var array An array of controller method names that can't be dispatched to by name.
     */
    public static $specialActions = ['delete', 'get', 'index', 'initialize', 'options', 'patch', 'post'];

    /**
     * Initialize an instance of the {@link ResourceRoute} class.
     *
     * @param string $root The url root of that this route will map to.
     * @param string $controllerPattern A pattern suitable for {@link sprintf} that will map
     * a path to a controller object name.
     */
    public function __construct($root = '', $controllerPattern = null) {
        $this->pattern($root);

        if ($controllerPattern !== null) {
            $this->controllerPattern = $controllerPattern;
        }
    }

    /**
     * Dispatch the route.
     *
     * @param Request $request The current request we are dispatching against.
     * @param array &$args The args to pass to the dispatch.
     * These are the arguments returned from {@link Route::matches()}.
     * @return mixed Returns the result from the controller method.
     * @throws NotFoundException Throws a 404 when the path doesn't map to a controller action.
     * @throws MethodNotAllowedException Throws a 405 when the http method does not map to a controller action,
     * but other methods do.
     */
    public function dispatch(Request $request, array &$args) {
        $controller = new $args['controller']();
        $method = strtolower($args['method']);
        $pathArgs = $args['pathArgs'];

        // See if the initialize method can take any of the parameters.
        $initialize = false;
        $initArgs = [];
        $initParams = [];
        $actionIndex = 0;
        if (method_exists($controller, 'initialize')) {
            $initialize = true;
            $initMethod = new \ReflectionMethod($controller, 'initialize');

            // Walk through the initialize() arguments and supply all of the ones that are required.
            foreach ($initMethod->getParameters() as $initParam) {
                $i = $initParam->getPosition();
                $initArgs[$i] = null;
                $initParams[$i] = $initParam->getName();

                if ($initParam->isDefaultValueAvailable()) {
                    $initArgs[$i] = $initParam->getDefaultValue();
                } elseif (!isset($pathArgs[$i])) {
                    throw new NotFoundException('Page', "Missing argument $i for {$args['controller']}::initialize().");
                } elseif ($this->failsCondition($initParams[$i], $pathArgs[$i])) {
                    // This isn't a valid value for a required parameter.
                    throw new NotFoundException('Page', "Invalid argument '{$pathArgs[$i]}' for {$initParams[$i]}.");
                } else {
                    $initArgs[$i] = $pathArgs[$i];
                    $actionIndex = $i + 1;
                }
            }
        }

        $action = '';
        $initComplete = false;
        for ($i = $actionIndex; $i < count($pathArgs); $i++) {
            $pathArg = $pathArgs[$i];

            $action = $this->actionExists($controller, $pathArg, $method, true);
            if ($action) {
                // We found an action and can move on to the next step.
                $actionIndex = $i + 1;
                break;
            } else {
                // This is a method argument. See whether to add it to the initialize method or not.
                if (!$initComplete && $actionIndex < count($initArgs)) {
                    // Make sure the argument is valid.
                    if ($this->failsCondition($initParams[$actionIndex], $pathArg)) {
                        // The argument doesn't validate against its condition so this can't be an init argument.
                        $initComplete = true;
                    } else {
                        // The argument can be added to initialize().
                        $initArgs[$actionIndex] = $pathArg;
                        $actionIndex++;
                    }
                }
            }
        }

        if (!$action) {
            // There is no specific action at this point so we have to check for a resource action.
            if ($actionIndex === 0) {
                $actions = ['get' => 'index', 'post' => 'post', 'options' => 'options'];
            } else {
                $actions = ['get' => 'get', 'patch' => 'patch', 'put' => 'put', 'delete' => 'delete'];
            }

            if (!isset($actions[$method])) {
                if ($actionIndex < count($pathArgs)) {
                    // There are more path args left to go then just throw a 404.
                    throw new NotFoundException();
                } else {
                    // The http method isn't allowed.
                    $allowed = array_keys($actions);
                    throw new MethodNotAllowedException($method, $allowed);
                }
            }

            $action = $actions[$method];
            if (!$this->actionExists($controller, $action)) {
                // If there are more path args left to go then just throw a 404.
                if ($actionIndex < count($pathArgs)) {
                    throw new NotFoundException();
                }

                // Check to see what actions are allowed.
                unset($actions[$method]);
                $allowed = [];
                foreach ($actions as $otherMethod => $otherAction) {
                    if ($this->actionExists($controller, $otherAction)) {
                        $allowed[] = strtoupper($otherMethod);
                    }
                }

                if (!empty($allowed)) {
                    // Other methods are allowed. Show them.
                    throw new MethodNotAllowedException($method, $allowed);
                } else {
                    // The action does not exist at all.
                    throw new NotFoundException();
                }
            }
        }

        // Make sure the number of action arguments match the action method.
        $actionMethod = new \ReflectionMethod($controller, $action);
        $action = $actionMethod->getName(); // make correct case.
        $actionParams = $actionMethod->getParameters();
        $actionArgs = array_slice($pathArgs, $actionIndex);

        if (count($actionArgs) > count($actionParams)) {
            // Double check to see if the first argument might be a method, but one that isn't allowed.
            $allowed = $this->allowedMethods($controller, $actionArgs[0]);
            if (count($allowed) > 0) {
                // At least one method was allowed for this action so throw an exception.
                throw new MethodNotAllowedException($method, $allowed);
            }

            // Too many arguments were passed.
            throw new NotFoundException();
        }
        // Fill in missing default parameters.
        foreach ($actionParams as $param) {
            $i = $param->getPosition();
            $paramName = $param->getName();

            if ($this->isMapped($paramName)) {
                // The parameter is mapped to a specific request item.
                array_splice($actionArgs, $i, 0, [$this->mappedData($paramName, $request)]);
            } elseif (!isset($actionArgs[$i]) || !$actionArgs[$i]) {
                if ($param->isDefaultValueAvailable()) {
                    $actionArgs[$i] = $param->getDefaultValue();
                } else {
                    throw new NotFoundException('Page', "Missing argument $i for {$args['controller']}::$action().");
                }
            } elseif ($this->failsCondition($paramName, $actionArgs[$i])) {
                throw new NotFoundException('Page', "Invalid argument '{$actionArgs[$i]}' for {$paramName}.");
            }
        }

        $args = array_replace($args, [
            'init' => $initialize,
            'initArgs' => $initArgs,
            'action' => $action,
            'actionArgs' => $actionArgs
        ]);

        if ($initialize) {
            Event::callUserFuncArray([$controller, 'initialize'], $initArgs);
        }

        $result = Event::callUserFuncArray([$controller, $action], $actionArgs);
        return $result;
    }

    /**
     * Tests whether or not a string is a valid identifier.
     *
     * @param string $str The string to test.
     * @return bool Returns true if {@link $str} can be used as an identifier.
     */
    protected static function isIdentifier($str) {
        if (preg_match('`[_a-zA-Z][_a-zA-Z0-9]{0,30}`i', $str)) {
            return true;
        }
        return false;
    }

    /**
     * Tests whether a controller action exists.
     *
     * @param object $object The controller object that the method should be on.
     * @param string $action The name of the action.
     * @param string $method The http method.
     * @param bool $special Whether or not to blacklist the special methods.
     * @return string Returns the name of the action method or an empty string if it doesn't exist.
     */
    protected function actionExists($object, $action, $method = '', $special = false) {
        if ($special && in_array($action, self::$specialActions)) {
            return '';
        }

        // Short circuit on a badly named action.
        if (!$this->isIdentifier($action)) {
            return '';
        }

        if ($method && $method !== $action) {
            $calledAction = $method.$action;
            if (Event::methodExists($object, $calledAction)) {
                return $calledAction;
            }
        }
        $calledAction = $action;
        if (Event::methodExists($object, $calledAction)) {
            return $calledAction;
        }
        return '';
    }

    /**
     * Find the allowed http methods on a controller object.
     *
     * @param object $object The object to test.
     * @param string $action The action to test.
     * @return array Returns an array of allowed http methods.
     */
    protected function allowedMethods($object, $action) {
        $allMethods = [
            Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE,
            Request::METHOD_PATCH, Request::METHOD_PUT,
            Request::METHOD_HEAD, Request::METHOD_OPTIONS
        ];

        // Special actions should not be considered.
        if (in_array($action, self::$specialActions)) {
            return [];
        }

        if (Event::methodExists($object, $action)) {
            // The controller has the named action and thus supports all methods.
            return $allMethods;
        }

        // Loop through all the methods and check to see if they exist in the form $method.$action.
        $allowed = [];
        foreach ($allMethods as $method) {
            if (Event::methodExists($object, $method.$action)) {
                $allowed[] = $method;
            }
        }
        return $allowed;
    }

    /**
     * Try matching a route to a request.
     *
     * @param Request $request The request to match the route with.
     * @param Application $app The application instantiating the route.
     * @return array|null Whether or not the route matches the request.
     * If the route matches an array of args is returned, otherwise the function returns null.
     */
    public function matches(Request $request, Application $app) {
        if (!$this->matchesMethods($request)) {
            return null;
        }

        if ($this->getMatchFullPath()) {
            $path = $request->getFullPath();
        } else {
            $path = $request->getPath();
        }

        // If this route is off of a root then check that first.
        if ($root = $this->pattern()) {
            if (stripos($path, $root) === 0) {
                // Strip the root off the path that we are examining.
                $path = substr($path, strlen($root));
            } else {
                return null;
            }
        }

        $pathParts = explode('/', trim($path, '/'));

        $controller = array_shift($pathParts);
        if (!$controller) {
            return null;
        }

        // Check to see if a class exists with the desired controller name.
        // If a controller is found then it is responsible for the route, regardless of any other parameters.
        $basename = sprintf($this->controllerPattern, ucfirst($controller));
        if (class_exists('\Garden\Addons', false)) {
            list($classname) = Addons::classMap($basename);

            // TODO: Optimize this second check.
            if (!$classname && class_exists($basename)) {
                $classname = $basename;
            }
        } elseif (class_exists($basename)) {
            $classname = $basename;
        } else {
            $classname = '';
        }

        if (!$classname) {
            return null;
        }

        $result = array(
            'controller' => $classname,
            'method' => $request->getMethod(),
            'path' => $path,
            'pathArgs' => $pathParts,
            'query' => $request->getQuery()
        );
        return $result;
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
            $regex = $this->conditions[$name];
            return !preg_match("`^$regex$`", $value);
        }

        if (isset(self::$globalConditions[$name])) {
            $regex = self::$globalConditions[$name];
            return !preg_match("`^$regex$`", $value);
        }

        return null;
    }
}
