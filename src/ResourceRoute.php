<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden;

use Garden\Exception\ClientException;
use Garden\Exception\NotFoundException;

/**
 * Maps paths to controllers that act as RESTful resources.
 *
 * - METHOD /controller/:id -> method
 * - METHOD /controller/:id/action -> methodAction
 * - GET /controller -> index
 * - METHOD /controller/action -> methodAction
 */
class ResourceRoute extends Route {
    protected $controllerPattern = '%sApiController';

    public function __construct($pattern = '', $controllerPattern = null) {
        $this->pattern($pattern);

        if ($controllerPattern !== null) {
            $this->controllerPattern = $controllerPattern;
        }
    }

    /**
     * Dispatch the route.
     *
     * @param array &$args The args to pass to the dispatch.
     * These are the arguments returned from {@link Route::matches()}.
     * @throws NotFoundException
     * @throws ClientException
     */
    public function dispatch(array &$args) {
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
                    throw new NotFoundException("Missing argument $i for {$args['controller']}::initialize().", 404);
                } elseif ($this->failsCondition($initParams[$i], $pathArgs[$i])) {
                    // This isn't a valid value for a required parameter.
                    throw new NotFoundException("Invalid argument '{$pathArgs[$i]}' for {$initParams[$i]}.", 404);
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

            $action = $this->actionExists($controller, $pathArg, $method);
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
                // The http method isn't allowed.
                $allowed = array_map('strtoupper', array_keys($actions));
                throw new ClientException(
                    strtoupper($method).' not allowed.',
                    405,
                    ['Allow' => implode(', ', $allowed)]
                );
            }

            $action = $actions[$method];
            if (!$this->actionExists($controller, $action)) {
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
                    throw new ClientException(strtoupper($method).' not allowed.', 405, ['Allow' => implode(', ', $allowed)]);
                } else {
                    // The action does not exist at all.
                    throw new NotFoundException("{$args['path']} not found");
                }
            }
        }

        $args['initialize'] = $initialize;
        $args['initArgs'] = $initArgs;
        if ($initialize) {
            Event::callUserFuncArray([$controller, 'initialize'], $initArgs);
        }
        // Make sure the number of action arguments match the action method.
        $actionMethod = new \ReflectionMethod($controller, $action);
        $actionParams = $actionMethod->getParameters();
        $actionArgs = array_slice($pathArgs, $actionIndex);

        if (count($actionArgs) > count($actionParams)) {
            // Double check to see if the first argument might be a method, but one that isn't allowed.
            if (count($actionArgs) > 0) {
                $allowed = $this->allowedMethods($controller, $actionArgs[0]);
                if (count($allowed) > 0) {
                    // At least one method was allowed for this action so throw an exception.
                    throw new ClientException(
                        strtoupper($method).' not allowed.',
                        405,
                        ['Allow' => implode(', ', $allowed)]
                    );
                }
            }

            // Too many arguments were passed.
            throw new Exception\NotFoundException("{$args['path']} not found");
        }
        // Fill in missing default parameters.
        foreach ($actionParams as $param) {
            $i = $param->getPosition();

            if (!isset($actionArgs[$i]) || !$actionArgs[$i]) {
                if ($param->isDefaultValueAvailable()) {
                    $actionArgs[$i] = $param->getDefaultValue();
                } else {
                    throw new NotFoundException("Missing argument $i for {$args['controller']}::$action().");
                }
            } elseif ($this->failsCondition($param->getName(), $actionArgs[$i])) {
                $name = $param->getName();
                throw new NotFoundException("Invalid argument '{$actionArgs[$i]}' for {$name}.", 404);
            }
        }

        $args += [
            'action' => $action,
            'actionArgs' => $actionArgs
        ];
        Event::callUserFuncArray([$controller, $action], $actionArgs);
    }

    /**
     * Tests whether or not a string is a valid identifier.
     *
     * @param string $str The string to test.
     * @return bool Returns true if {@link $str} can be used as an identifier.
     */
    protected static function isIdentifier($str) {
        if (preg_match('`^p\d+$`i', $str)) {
            // This is a page number.
            return false;
        }
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
     * @return string Returns the name of the action method or an empty string if it doesn't exist.
     */
    protected function actionExists($object, $action, $method = '') {
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
        $path = $request->path();

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
        if (class_exists('Addons', false)) {
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
            'method' => $request->method(),
            'path' => $path,
            'pathArgs' => $pathParts,
            'query' => $request->query()
        );
        return $result;
    }
}
