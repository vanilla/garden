<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @package Garden Framework
 * @subpackage Core Functions
 */

/**
 * This file is part of the array_column library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2013 Ben Ramsey <http://benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */

if (!function_exists('array_column')) {

    /**
     * Returns the values from a single column of the input array, identified by
     * the $columnKey.
     *
     * Optionally, you may provide an $indexKey to index the values in the returned
     * array by the values from the $indexKey column in the input array.
     *
     * @param array $input A multi-dimensional array (record set) from which to pull
     *                     a column of values.
     * @param mixed $columnKey The column of values to return. This value may be the
     *                         integer key of the column you wish to retrieve, or it
     *                         may be the string key name for an associative array.
     * @param mixed $indexKey (Optional.) The column to use as the index/keys for
     *                        the returned array. This value may be the integer key
     *                        of the column, or it may be the string key name.
     * @return array
     * @category Array Functions
     */
    function array_column($input = null, $columnKey = null, $indexKey = null) {
        // Using func_get_args() in order to check for proper number of
        // parameters and trigger errors exactly as the built-in array_column()
        // does in PHP 5.5.
        $argc = func_num_args();
        $params = func_get_args();

        if ($argc < 2) {
            trigger_error("array_column() expects at least 2 parameters, {$argc} given", E_USER_WARNING);
            return null;
        }

        if (!is_array($params[0])) {
            trigger_error('array_column() expects parameter 1 to be array, '.gettype($params[0]).' given', E_USER_WARNING);
            return null;
        }

        if (!is_int($params[1])
            && !is_float($params[1])
            && !is_string($params[1])
            && $params[1] !== null
            && !(is_object($params[1]) && method_exists($params[1], '__toString'))
        ) {
            trigger_error('array_column(): The column key should be either a string or an integer', E_USER_WARNING);
            return false;
        }

        if (isset($params[2])
            && !is_int($params[2])
            && !is_float($params[2])
            && !is_string($params[2])
            && !(is_object($params[2]) && method_exists($params[2], '__toString'))
        ) {
            trigger_error('array_column(): The index key should be either a string or an integer', E_USER_WARNING);
            return false;
        }

        $paramsInput = $params[0];
        $paramsColumnKey = ($params[1] !== null) ? (string)$params[1] : null;

        $paramsIndexKey = null;
        if (isset($params[2])) {
            if (is_float($params[2]) || is_int($params[2])) {
                $paramsIndexKey = (int)$params[2];
            } else {
                $paramsIndexKey = (string)$params[2];
            }
        }

        $resultArray = array();

        foreach ($paramsInput as $row) {

            $key = $value = null;
            $keySet = $valueSet = false;

            if ($paramsIndexKey !== null && array_key_exists($paramsIndexKey, $row)) {
                $keySet = true;
                $key = (string)$row[$paramsIndexKey];
            }

            if ($paramsColumnKey === null) {
                $valueSet = true;
                $value = $row;
            } elseif (is_array($row) && array_key_exists($paramsColumnKey, $row)) {
                $valueSet = true;
                $value = $row[$paramsColumnKey];
            }

            if ($valueSet) {
                if ($keySet) {
                    $resultArray[$key] = $value;
                } else {
                    $resultArray[] = $value;
                }
            }

        }

        return $resultArray;
    }
}


/**
 * Take all of the items in an array and make a new array with them specified by mappings.
 *
 * @param array $array The input array to translate.
 * @param array $mappings The mappings to translate the array.
 * @return array
 *
 * @category Array Functions
 */
function array_translate($array, $mappings) {
    $array = (array)$array;
    $result = array();
    foreach ($mappings as $index => $value) {
        if (is_numeric($index)) {
            $key = $value;
            $newkey = $value;
        } else {
            $key = $index;
            $newkey = $value;
        }
        if (isset($array[$key]))
            $result[$newkey] = $array[$key];
        else
            $result[$newkey] = NULL;
    }
    return $result;
}

/**
 * Returns the average rating based in the Wilson score interval.
 *
 *
 * @param int $positive The number of positive ratings.
 * @param int $total The total number of ratings.
 * @param float $confidence Your confidence level.
 * @return int
 *
 * @see http://stackoverflow.com/questions/9478741/mysql-php-for-wilson-score-interval-with-time-gravity
 * @see http://evanmiller.org/how-not-to-sort-by-average-rating.html
 */
//function averageRating($positive, $total, $confidence = 0.95) {
//   if ($total == 0)
//      return 0;
//
//   if ($confidence == 0.95)
//      $z = 1.96;
//   else
//      $z = pnormaldist(1 - (1 - $confidence) / 2, 0, 1);
//   $p = 1.0 * $positive / $total;
//   $s = ($p + $z * $z / (2 * $total) - $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $total)) / $total)) / (1 + $z * $z / $total);
//   return $s;
//}

/**
 * Base64 Encode a string, but make it suitable to be passed in a url.
 *
 * @param string $str The string to encode.
 * @return string Returns the encoded string.
 * @category String Functions
 * @see base64_urldecode()
 * @see base64_encode()
 */
function base64_urlencode($str) {
    return strtr(base64_encode($str), '+/', '-_');
}

/**
 * Decode a string that was encoded using {@link base64_urlencode()}
 *
 * @param string $str The encoded string.
 * @return string The decoded string.
 * @category String Functions
 * @see base64_urldecode()
 * @see base64_decode()
 */
function base64_urldecode($str) {
    return base64_decode(strtr($str, '-_', '+/'));
}

/**
 * An alias of config().
 * Get a value from the config.
 *
 * @param string $key The config key.
 * @param string $default The default value if the config setting isn't available.
 * @return string The config value.
 * @see config()
 */
function c($key, $default) {
    return config($key, $default);
}

//function checkRoute($className, $methodName, &$routed) {
//   if ($routed)
//      return false;
//   if (class_exists($className) && method_exists($className, $methodName))
//      return $routed = true;
//   return $routed = false;
//}

/**
 * Get a value from the config.
 *
 * @param string $key The config key.
 * @param string $default The default value if the config setting isn't available.
 * @return mixed The config value.
 */
function config($key, $default = null) {
    return Garden\Config::instance()->get($key, $default);
}

/**
 * Compare two dates formatted as either timestamps or strings.
 *
 * @param mixed $date1 The first date to compare expressed as an integer timestamp or a string date.
 * @param mixed $date2 The second date to compare expressed as an integer timestamp or a string date.
 * @return int Returns `1` if {@link $date1} > {@link $date2}, `-1` if {@link $date1} > {@link $date2},
 * or `0` if the two dates are equal.
 * @category Date/Time Functions
 */
function datecmp($date1, $date2) {
    if (is_numeric($date1))
        $timestamp1 = $date1;
    else
        $timestamp1 = strtotime($date1);

    if (is_numeric($date2))
        $timestamp2 = $date2;
    else
        $timestamp2 = strtotime($date2);

    if ($timestamp1 == $timestamp2)
        return 0;
    elseif ($timestamp1 > $timestamp2)
        return 1;
    else
        return -1;
}

/**
 * @param type $value
 * @param type $prefix
 */
function decho($value, $prefix = 'debug') {
    if (PHP_SAPI === 'cli')
        fwrite(STDERR, "$prefix: ".var_export($value, true)."\n");
    else
        echo '<pre class="decho">'.$prefix.': '.htmlspecialchars(var_export($value, true)).'</pre>';
}

/**
 * Mark something as deprecated.
 *
 * When passing the {@link $name} argument, try using the following naming convention for names.
 *
 * - Functions: function_name()
 * - Classes: ClassName
 * - Static methods: ClassName::methodName()
 * - Instance methods: ClassName->methodName()
 *
 * @param string $name The name of the deprecated function.
 * @param string $newname The name of the new function that should be used instead.
 */
function deprecated($name, $newname = '') {
    $msg = $name.' is deprecated.';
    if ($newname) {
        $msg .= " Use $newname instead.";
    }

    trigger_error($msg, E_USER_DEPRECATED);
}

/**
 * A version of file_put_contents() that is multi-thread safe.
 *
 * @param string $filename Path to the file where to write the data.
 * @param mixed $data The data to write. Can be either a string, an array or a stream resource.
 * @param int $mode The permissions to set on a new file.
 * @return boolean
 * @category Filesystem Functions
 * @see http://php.net/file_put_contents
 */
function file_put_contents_safe($filename, $data, $mode = 0644) {
    $temp = tempnam(dirname($filename), 'atomic');

    if (!($fp = @fopen($temp, 'wb'))) {
        $temp = dirname($filename).DIRECTORY_SEPARATOR.uniqid('atomic');
        if (!($fp = @fopen($temp, 'wb'))) {
            trigger_error("file_put_contents_atomic() : error writing temporary file '$temp'", E_USER_WARNING);
            return false;
        }
    }

    fwrite($fp, $data);
    fclose($fp);

    if (!@rename($temp, $filename)) {
        @unlink($filename);
        @rename($temp, $filename);
    }

    @chmod($filename, $mode);
    return true;
}

/**
 * Force a value into a boolean.
 *
 * @param mixed $value The value to force.
 * @return boolean Returns the boolean value of {@link $value}.
 */
function force_bool($value) {
    if (is_string($value)) {
        switch (strtolower($value)) {
            case 'disabled':
            case 'false':
            case 'no':
            case 'off':
            case '':
                return false;
        }
        return true;
    }
    return (bool)$value;
}

/**
 * Force a value to be an integer.
 *
 * @param mixed $value The value to force.
 * @return int Returns the integer value of {@link $value}.
 */
function force_int($value) {
    if (is_string($value)) {
        switch (strtolower($value)) {
            case 'disabled':
            case 'false':
            case 'no':
            case 'off':
            case '':
                return 0;
            case 'true':
            case 'yes':
            case 'on':
                return 1;
        }
    }
    return intval($value);
}

/**
 * Like {@link implode()}, but joins array keys and values.
 *
 * @param string $elemglue The string that separates each element of the array.
 * @param string $keyglue The string that separates keys and values.
 * @param array $pieces The array of strings to implode.
 * @return string Returns the imploded array as a string.
 *
 * @category Array Functions
 * @category String Functions
 */
function implode_assoc($elemglue, $keyglue, $pieces) {
    $result = '';

    foreach ($pieces as $key => $value) {
        if ($result)
            $result .= $elemglue;

        $result .= $key.$keyglue.$value;
    }
    return $result;
}

/**
 * Finds whether the type given variable is a database id.
 *
 * @param mixed $val The variable being evaluated.
 * @param bool $allow_slugs Whether or not slugs are allowed in the url.
 * @return bool Returns `true` if the variable is a database id or `false` if it isn't.
 */
function is_id($val, $allow_slugs = false) {
    return is_numeric($val);
}

/**
 * Strip a substring from the beginning of a string.
 *
 * @param string $mainstr
 * @param string $substr
 * @return string
 *
 * @category String Functions
 */
function ltrim_substr($mainstr, $substr) {
    if (strncasecmp($mainstr, $substr, strlen($substr)) === 0)
        return substr($mainstr, strlen($substr));
    return $mainstr;
}

/**
 * Get the file extension from a mime-type.
 * @param string $mime
 * @param string $ext If this argument is specified then this extension will be added to the list of known types.
 * @return string The file extension without the dot.
 */
function mime2ext($mime, $ext = null) {
    static $known = array('text/plain' => 'txt', 'image/jpeg' => 'jpg');
    $mime = strtolower($mime);

    if ($ext !== null) {
        $known[$mime] = ltrim($ext, '.');
    }

    if (array_key_exists($mime, $known))
        return $known[$mime];

    // We don't know the mime type so we need to just return the second part as the extension.
    $result = trim(strrchr($mime, '/'), '/');

    if (substr($result, 0, 2) === 'x-')
        $result = substr($result, 2);

    return $result;
}

/**
 * Calculate the polynomial distribution of a probability.
 * @param float $qn A probability between 0 and 1.
 * @return real
 */
function pnormaldist($qn) {
    $b = array(1.570796288, 0.03706987906, -0.8364353589e-3, -0.2250947176e-3, 0.6841218299e-5,
        0.5824238515e-5, -0.104527497e-5, 0.8360937017e-7, -0.3231081277e-8, 0.3657763036e-10, 0.6936233982e-12);

    if ($qn < 0.0 || 1.0 < $qn)
        return 0.0;

    if ($qn == 0.5)
        return 0.0;

    $w1 = $qn;

    if ($qn > 0.5)
        $w1 = 1.0 - $w1;

    $w3 = -log(4.0 * $w1 * (1.0 - $w1));
    $w1 = $b[0];

    for ($i = 1; $i <= 10; $i++) {
        $w1 += $b[$i] * pow($w3, $i);
    }

    if ($qn > 0.5)
        return sqrt($w1 * $w3);

    return -sqrt($w1 * $w3);
}

/**
 * Reflect the arguments on a callback and returns them as an associative array.
 *
 * @param callback $callback A callback to the function.
 * @param array $args An array of arguments.
 * @param array $get An optional other array of arguments.
 * @return array The arguments in an associative array, in order ready to be passed to call_user_func_array().
 * @throws Exception Throws an exception when {@link callback} isn't a valid callback.
 */
function reflectArgs($callback, $args, $get = null) {
    $result = array();

    if (is_string($callback) && !function_exists($callback))
        throw new Exception("Function $callback does not exist");

    if (is_array($callback) && !method_exists($callback[0], $callback[1]))
        throw new Exception("Method {$callback[1]} does not exist.");

    if (is_array($get))
        $args = array_merge($get, $args);
    $args = array_change_key_case($args);

    if (is_string($callback) || is_a($callback, 'Closure')) {
        $meth = new ReflectionFunction($callback);
        $meth_name = $meth;
    } else {
        $meth = new ReflectionMethod($callback[0], $callback[1]);
        if (is_string($callback[0])) {
            $meth_name = $callback[0].'::'.$meth->getName();
        } else {
            $meth_name = get_class($callback[0]).'->'.$meth->getName();
        }
    }

    $meth_params = $meth->getParameters();

    $call_args = array();
    $missing_args = array();

    // Set all of the parameters.
    foreach ($meth_params as $index => $meth_param) {
        $param_name = $meth_param->getName();
        $param_namel = strtolower($param_name);

        if (isset($args[$param_namel]))
            $param_value = $args[$param_namel];
        elseif (isset($args[$index]))
            $param_value = $args[$index];
        elseif ($meth_param->isDefaultValueAvailable())
            $param_value = $meth_param->getDefaultValue();
        else {
            $param_value = NULL;
            $missing_args[] = '$'.$param_name;
        }

        $call_args[$param_name] = $param_value;
    }

    // Add optional parameters so that methods that use get_func_args() will still work.
    for ($index = count($call_args); array_key_exists($index, $args); $index++) {
        $call_args[$index] = $args[$index];
    }

    if (count($missing_args) > 0) {
        trigger_error("$meth_name() expects the following parameters: ".implode(', ', $missing_args).'.', E_USER_NOTICE);
    }

    return $call_args;
}

/**
 * Strip a substring rom the end of a string.
 *
 * @param string $mainstr
 * @param string $substr
 * @return string
 * @category String Functions
 */
function rtrim_substr($mainstr, $substr) {
    if (strcasecmp(substr($mainstr, -strlen($substr)), $substr) === 0)
        return substr($mainstr, 0, -strlen($substr));
    return $mainstr;
}

/**
 * Saves an array of configuration values to a given {@link $path}.
 * @param string $path The path to save to.
 * @param array $values The values to save to the config file.
 * @throws Exception Throws an exception when the file specified by {@link $path} is not a recognized file format.
 */
function saveConfig($path, $values) {
    // Load the config into a temporary array so we know what to save.
    $array = [];
    loadConfig($path, $array);

    foreach ($values as $key => $value) {
        if ($value === null)
            unset($array[$key]);
        else
            $array[$key] = $value;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $basename = basename($path, $ext);
    $tmpPath = tempnam(dirname($path), $basename);

    switch ($ext) {
        case 'json':
            $json = json_encode($array);
            file_put_contents($tmpPath, $json);
            break;
        case 'php':
            $php = '$'.$basename.' = '.var_export($array, true);
            file_put_contents($tmpPath, $php);
            break;
        case 'ser':
            $ser = serialize($array);
            file_put_contents($tmpPath, $ser);
            break;
        default:
            throw new Exception("Unknown file type: $ext.", 422);
    }
    rename($tmpPath, $path);
}

/**
 * Returns whether or not a string begins with another string.
 * This function is not case-sensitive.
 *
 * @param string $haystack The string to test.
 * @param string $needle The substring to test against.
 * @return bool Whether or not `$string` begins with `$with`.
 * @category String Functions
 */
function str_begins($haystack, $needle) {
    return strncasecmp($haystack, $needle, strlen($needle)) === 0;
}

/**
 * Returns whether or not a string ends with another string.
 * This function is not case-sensitive.
 *
 * @param string $haystack The string to test.
 * @param string $needle The substring to test against.
 * @return bool Whether or not `$string` ends with `$with`.
 * @category String Functions
 */
function str_ends($haystack, $needle) {
    return strcasecmp(substr($haystack, -strlen($needle)), $needle) === 0;
}

$timers = array();

/**
 * Start a timer to time a piece of code.
 *
 * @global array $timers All of the active timers.
 * @param string $name The name of the timer.
 */
function timerStart($name) {
    global $timers;

    $str = '';

    $count = count($timers);

    // Mark my parent timer as such so it knows how to format.
    if ($count) {
        if (!isset($timers[$count - 1]['parent'])) {
            $timers[$count - 1]['parent'] = true;
            $str .= "\n";
        }
    }

    $timer = array('name' => $name, 'start' => microtime(true));
    $timers[] = $timer;

    $str .= str_repeat('  ', $count).
        "start $name";

    fwrite(STDERR, $str);
}

/**
 * Stop a timer that was called with timerStart().
 *
 * @global array $timers All of the active timers.
 */
function timerStop($data = null) {
    global $timers;

    $stop = microtime(true);
    $timer = array_pop($timers);

    if ($timer) {
        $timespan = $stop - $timer['start'];

        if (isset($timer['parent'])) {
            // This was a nested timer.
            $str = str_repeat('  ', count($timers)).
                "stop {$timer['name']}...".formatTimespan($timespan);
            fwrite(STDERR, $str);
        } else {
            // Not a nested timer.
            fwrite(STDERR, '...'.formatTimespan($timespan));
        }

        if (is_array($data) && count($data))
            fwrite(STDERR, ' '.implode_assoc(', ', ': ', $data));

        fwrite(STDERR, "\n");
    } else {
        // This really is an error, but probably isn't worth taking out the entire script for.
        trigger_error("timerStop() called without calling timerStart() first.", E_USER_NOTICE);
    }
}

/**
 * Make sure that a directory exists.
 *
 * @param string $dir The name of the directory.
 * @param int $mode The file permissions on the folder if it's created.
 * @throws Exception Throws an exception with {@link $dir} is a file.
 * @category Filesystem Functions
 */
function touchdir($dir, $mode = 0777) {
    if (!file_exists($dir)) {
        mkdir($dir, $mode, true);
    } elseif (!is_dir($dir)) {
        throw new Exception("The specified directory already exists as a file. ($dir)", 400);
    }
}

/**
 * Make sure that a key exists in an array.
 *
 * @param string|int $key The array key to ensure.
 * @param array $array The array to modify.
 * @param mixed $default The default value to set if key does not exist.
 * @category Array Functions
 */
function touchval($key, &$array, $default) {
    if (!array_key_exists($key, $array))
        $array[$key] = $default;
}

/**
 * Safely get a value out of an array.
 *
 * This function will always return a value even if the array key doesn't exist.
 * The val() function is one of the biggest workhorses of Vanilla and shows up a lot throughout other code.
 * It's much preferable to use this function if your not sure whether or not an array key exists rather than
 * using @ error suppression.
 *
 * This function uses optimizations found in the [facebook libphputil library](https://github.com/facebook/libphutil).
 *
 * @param string|int $key The array key.
 * @param array $array The array to get the value from.
 * @param mixed $default The default value to return if the key doesn't exist.
 * @return mixed The item from the array or `$default` if the array key doesn't exist.
 * @category Array Functions
 */
function val($key, array $array, $default = null) {
    // isset() is a micro-optimization - it is fast but fails for null values.
    if (isset($array[$key])) {
        return $array[$key];
    }

    // Comparing $default is also a micro-optimization.
    if ($default === null || array_key_exists($key, $array)) {
        return null;
    }

    return $default;
}

/**
 * Return the value from an associative array.
 * This function differs from val() in that $key can be an array that will be used to walk a nested array.
 *
 * @param string $keys The key or property name of the value.
 * @param mixed $array The array or object to search.
 * @param mixed $default The value to return if the key does not exist.
 * @return mixed The value from the array or object.
 * @category Array Functions
 */
function valr($keys, array $array, $default = null) {
    if (is_string($keys)) {
        $keys = explode('.', $keys);
    }

    $value = $array;
    for ($i = 0; $i < count($keys); ++$i) {
        $SubKey = $keys[$i];

        if (is_array($value) && isset($value[$SubKey])) {
            $value = $value[$SubKey];
        } elseif (is_object($value) && isset($value->$SubKey)) {
            $value = $value->$SubKey;
        } else {
            return $default;
        }
    }
    return $value;
}