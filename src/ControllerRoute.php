<?php

namespace Garden;

/**
 * A route that maps urls to controller actions.
 *
 *
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license LGPL
 * @since 1.0
 */
class ControllerRoute extends Route {
    /// Properties ///

    /**
     * @var array An array of allowed controllers or an empty array to allow all controllers.
     */
    protected $controllers;

    /// Methods ///

    public function __construct($pattern = '', $controllers = array()) {
        $this->pattern($pattern);
        $this->controllers($controllers);
    }

    /**
     * Gets or sets an array of allowed controllers and aliases. The array has the following form.
     *
     * ```
     * array (
     *     'Comments', // base name of controller
     *     'alias' => 'Discussions' // alias mapping to class name of controller
     * )
     *
     * array() // an empty array is all controllers
     * ```
     * @param array $value If supplied this will set the controller array.
     * @return array
     */
    public function controllers($value = null) {
        if ($value !== null) {
            // Canonicalize the list of controllers.
            $controllers = array();
            foreach ($value as $key => $value) {
                $value = rtrim_substr($value, 'controller');
                if (is_numeric($key)) {
                    $key = strtolower($value);
                }
                $controllers[$key] = $value;
            }
            $this->controllers = $controllers;
        }
        return $this->controllers;
    }

    public function dispatch(array &$args) {
        // Instantiate the controller.
        $controller = new $args['controller'];
        $callback = array($controller, $args['action']);
        $callback_args = reflectArgs($callback, $args['path_args'], $args['query']);

        $result = Event::callUserFuncArray($callback, $callback_args);
        return $result;
    }

    /**
     * Route a request as a default restful request.
     *
     * Urls that successfully route here are in the following form:
     *
     * - /controller/method[/args][.ext]
     * - /controlleritem/{id}/method[/args][.ext]
     *
     * @param Request $request The request to match against.
     * @param Application $app The application that is invoking the request.
     * @return array|null
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

        $path_parts = explode('/', ltrim($path, '/'));

        // Check for a file extension.
        $ext = '';
        if (count($path_parts) > 0) {
            $file = end($path_parts);
            if (substr($file, -1) !== '/' && $pos = strrpos($file, '.')) {
                $ext = strtolower(substr($file, $pos));
                $path_parts[count($path_parts) - 1] = substr($file, 0, $pos);
            }
        }

        // The controller name is the first part of the path.
        if (!empty($path_parts)) {
            $controller_key = array_shift($path_parts);
        } else {
            $controller_key = 'root';
        }

        if (!$controller_key)
            return null;

        // Check to see if the controller is allowed.
        if (!empty($this->controllers)) {
            if (isset($this->controllers[$controller_key])) {
                $controller_name = $this->controllers[$controller_key];
            } else {
                // The controller isn't allowed.
                return null;
            }
        } else {
            $controller_name = ucfirst($controller_key);
        }

        // Look for an id.
        $id = array_shift($path_parts);
        if (is_id($id, true)) {
            $controller_name .= 'Item';
        } elseif ($id !== false) {
            array_unshift($path_parts, $id);
        }

        // If we are requesting an html endpoint then we just request the naked controller.
        // Otherwise we request the api controller.
        if (in_array($ext, array('', '.htm', '.html', '.txt'))) {
            // This is a regular endpoint. It will check for the api endpoint, but will fall back to the api controller.
            $alt_controller_name = $controller_name . 'ApiController';
            $controller_name .= 'Controller';
        } else {
            // This is an api endpoint and must route to an api controller.
            $controller_name = $controller_name . 'ApiController';
        }

        // Check to see if the controller exists.
        if (class_exists($controller_name)) {
            // do nothing
        } elseif (isset($alt_controller_name) && class_exists($alt_controller_name)) {
            $controller_name = $alt_controller_name;
        } else {
            return null;
        }

        if (!empty($path_parts)) {
            $action_name = array_shift($path_parts);
        } else {
            $action_name = 'index';
        }

        // First check to see if there is an override.
        $specific_action_name = $action_name . '_' . $request->method();

        // Check for a specific http method version of the method.
        if (Event::methodExists($controller_name, $specific_action_name)) {
            $action_name = $specific_action_name;
        } elseif (!Event::methodExists($controller_name, $action_name)) {
            // Fall back to the index() method if the method doesn't exist.
            if (Event::methodExists($controller_name, 'index_'.$request->method())) {
                array_unshift($path_parts, $action_name);
                $action_name = 'index_'.$request->method();
            } elseif (Event::methodExists($controller_name, 'index')) {
                array_unshift($path_parts, $action_name);
                $action_name = 'index';
            } else {
                return null;
            }
        }

        $result = array(
            'controller' => $controller_name,
            'action' => $action_name,
            'method' => $request->method(),
            'path_args' => $path_parts,
            'query' => $request->query(),
            'ext' => $ext
        );
        return $result;
    }
}