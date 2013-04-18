<?php defined('APPLICATION') or die('@!');

/**
 * 
 * @author Todd Burry <todd@vanillaforums.com>
 * @package Vanilla Framework
 * @subpackage Core Functions
 */


/**
 * The global config array.
 */
$config = array();

/**
 * Take all of the items in an array and make a new array with them specified by mappings.
 *
 * @param array $array The input array to translate.
 * @param array $mappings The mappings to translate the array.
 * @return array
 * 
 * @category Array Functions
 */
function arrayTranslate($array, $mappings) {
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
 * Simple autoloader that looks at a single directory.
 * 
 * This autoloader can load the following classes.
 * 
 * - Any [PSR-0](//github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) named class.
 * - Any other class should be in the directory named as `class.classname.php`. Make sure to use all lowercase in the filename.
 * 
 * @param string $class The name of the class to autoload.
 * @param string $dir The directory to look in.
 */
function autoLoadDir($class, $dir = null) {
   if ($dir === null)
      $dir = __DIR__;

   // Support namespaces and underscore classes.
   $class = str_replace(array('\\', '_'), '/', $class);

   $pos = strrpos($class, '/');
   if ($pos !== false) {
      // Load as a P0 compliant class.
      $subdir = '/' . substr($class, 0, $pos + 1);
      $filename = substr($class, $pos + 1) . '.php';
   } else {
      $subdir = '/';
      $filename = strtolower("class.$class.php");
   }

   $path = $dir . $subdir . $filename;
   if (file_exists($path)) {
      require_once $path;
      return true;
   }
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
function averageRating($positive, $total, $confidence = 0.95) {
   if ($total == 0)
      return 0;

   if ($confidence == 0.95)
      $z = 1.96;
   else
      $z = pnormaldist(1 - (1 - $confidence) / 2, 0, 1);
   $p = 1.0 * $positive / $total;
   $s = ($p + $z * $z / (2 * $total) - $z * sqrt(($p * (1 - $p) + $z * $z / (4 * $total)) / $total)) / (1 + $z * $z / $total);
   return $s;
}

/**
 * Base64 Encode a string, but make it suitable to be passed in a url.
 * 
 * @param string $str The string to encode.
 * @return string The encoded string.
 */
function base64UrlEncode($str) {
   return strtr(base64_encode($str), '+/', '-_');
}

/**
 * Decode a string that was encoded using base64UrlEncode().
 * 
 * @param string $str The encoded string.
 * @return string The decoded string.
 */
function base64UrlDecode($str) {
   return base64_decode(strtr($str, '-_', '+/'));
}

/**
 * An alias of config().
 * Get a value from the config.
 * 
 * @param string $key The config key.
 * @param string $default The default value if the config setting isn't available.
 * @return string The config value.
 */
function c($key, $default) {
   return config($key, $default);
}

function checkRoute($className, $methodName, &$routed) {
   if ($routed)
      return false;
   if (class_exists($className) && method_exists($className, $methodName))
      return $routed = true;
   return $routed = false;
}

/**
 * Get a value from the config.
 * 
 * @param string $key The config key.
 * @param string $default The default value if the config setting isn't available.
 * @return string The config value.
 */
function config($key, $default = null) {
   global $config;

   if (isset($config[$key]))
      return $config[$key];
   return $default;
}

/**
 * Get the values of a column of data as an array.
 * This function is useful for grabbing the values of a column to generate an in clause for another query.
 * 
 * @param data $data The data to grab the column from.
 * @param string $column
 * @return array The column values.
 * 
 * @category Data Functions
 */
function dataColumn($data, $column) {
   $result = array();
   
   foreach ($data as $row) {
      if (!isset($row[$column]))
         continue;
      
      $result[$row[$column]] = 1;
   }
   
   return array_keys($result);
}

/**
 * Compare two dates formatted as either timestamps or strings.
 * 
 * @param mixed $date1
 * @param mixed $date2
 * @return int
 */
function dateCompare($date1, $date2) {
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
   fwrite(STDERR, "$prefix: " . var_export($value, true) . "\n");
}

/**
 * Make sure that a directory exists.
 * 
 * @param string $dir The name of the directory.
 * @param int $mode The file permissions on the folder if it's created.
 */
function ensureDir($dir, $mode = 0777) {
   if (!file_exists($dir)) {
      mkdir($dir, $mode, true);
   } elseif (!is_dir($dir)) {
      throw new Exception("The specified directory already exists as a file. ($dir)", 400);
   }
}

/**
 * Force a value into a boolean.
 * 
 * @param mixed $value The value to force.
 * @return boolean
 */
function forceBool($value) {
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
   return boolval($value);
}

/**
 * Force a value to be an integer.
 * 
 * @param mixed $value The value to force.
 * @return int
 */
function forceInt($value) {
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
 * Like implode() but joins array keys and values.
 * @param string $elemglue The string that seperates each element of the array.
 * @param string $keyglue The string that seperates keys and values.
 * @param array $pieces The array of strings to implode.
 * 
 * @category Array Functions
 * @category String Functions
 */
function implodeAssoc($elemglue, $keyglue, $pieces) {
   $result = '';
   
   foreach ($pieces as $key => $value) {
      if ($result)
         $result .= $elemglue;
      
      $result .= $key.$keyglue.$value;
   }
   return $result;
}

/**
 * Encode an array in ini format.
 * The resulting array will work with parse_ini_file() and parse_ini_string().
 * 
 * @param array $data A flat, associative array of data.
 * @return string The data in ini format.
 */
function iniEncode($data) {
   ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
   
   $result = '';
   
   $lastSection = null;
   
   foreach ($data as $key => $value) {
      $section = trim(strstr($key, '.', true), '.');
      
      if ($section !== $lastSection) {
         if ($section) {
            $result .= "\n[$section]\n";
         }
         $lastSection = $section;
      }
      
      $result .= $key.' = ';
      
      if (is_bool($value)) {
         $str = $value ? 'true' : 'false';
      } elseif (is_numeric($value)) {
         $str = $value;
      } elseif (is_string($value)) {
         $str = '"'.addcslashes($value, "\"").'"';
      }
      $result .= $str."\n";
   }
   
   return $result;
}

/**
 * Load configuration information from an ini file.
 * 
 * @param string $path The path to the config file.
 * @param array $array The array to load the data into.
 * If this parameter is left blank then the file will be loaded into the global config array.
 */
function loadConfig($path, &$array = null) {
   global $config;
   
   if ($array === null)
      $array =& $config;

   $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
   switch ($ext) {
      case 'ini':
         $loaded = parse_ini_file($path, false, INI_SCANNER_RAW);
         break;
      case 'json':
         $loaded = json_decode(file_get_contents($path), true, 1);
         break;
      case 'php':
         include $path;
      case 'ser':
         $loaded = unserialize(file_get_contents($path));
         break;
   }

   if (empty($loaded))
      return;

   $array = array_merge($array, $loaded);
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
function ltrimString($mainstr, $substr) {
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
function mimeToExt($mime, $ext = null) {
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

   for ($i = 1; $i <= 10; $i++)
      $w1 += $b[$i] * pow($w3, $i);

   if ($qn > 0.5)
      return sqrt($w1 * $w3);

   return -sqrt($w1 * $w3);
}

/**
 * Route finder
 * 
 * Parses the Request and determines the method and controller.
 * 
 * @param Request $request
 * @return Request
 */
function route($request) {
   // Figure out the controller and method.
   $pathParts = $request->path();
   $get = array_change_key_case($request->get());
   $requestMethod = $request->method();

   // Look for a controller/method in the form: controller/method, controller[/index], [/index]method, [/index].
   $dispatchParts = array();
   $indexedArgs = array();
   if (empty($pathParts)) {
      $pathParts[0] = 'index';
      $pathParts[1] = 'index';
   }

   // TODO: Check for a route override.

   $routed = false;

   $className = $pathParts[0] . 'Controller'; // class names case-insensitive
   if (class_exists($className)) {
      $dispatchParts[0] = $pathParts[0];

      // Method with HTTP verb prefix?
      if (isset($pathParts[1]) && checkRoute($className, "{$requestMethod}_{$pathParts[1]}", $routed)) {
         $dispatchParts[1] = "{$requestMethod}_{$pathParts[1]}";
         $indexedArgs = array_slice($pathParts, 2);

         // Method without HTTP verb prefix?
      } elseif (isset($pathParts[1]) && checkRoute($className, $pathParts[1], $routed)) {
         $dispatchParts[1] = $pathParts[1];
         $indexedArgs = array_slice($pathParts, 2);

         // Controller with index method, HTTP verb prefix?
      } elseif (checkRoute($className, "{$requestMethod}_index", $routed)) {
         $dispatchParts[1] = "{$requestMethod}_index";
         $indexedArgs = array_slice($pathParts, 1);

         // Controller with index method, no HTTP verb prefix?
      } elseif (checkRoute($className, 'Index', $routed)) {
         $dispatchParts[1] = 'index';
         $indexedArgs = array_slice($pathParts, 1);
      }
   }

   // Index Controller, method with HTTP verb prefix?
   if (checkRoute('IndexController', "{$requestMethod}_{$pathParts[0]}", $routed)) {
      $dispatchParts[0] = 'index';
      $dispatchParts[1] = "{$requestMethod}_{$pathParts[0]}";
      $indexedArgs = array_slice($pathParts, 1);
   }

   // Index Controller, method without HTTP verb prefix?
   if (checkRoute('IndexController', $pathParts[0], $routed)) {
      $dispatchParts[0] = 'index';
      $dispatchParts[1] = $pathParts[0];
      $indexedArgs = array_slice($pathParts, 1);
   }

   // Home NotFound
   if (!$routed) {
      $dispatchParts[0] = 'home';
      $dispatchParts[1] = 'notfound';
      $get = array('url' => $request->url());
   }

   $result = new Request(
         implode('/', $dispatchParts),
         $get,
         $request->Post());
   $result->PathArgs($indexedArgs);

   return $result;
}

/**
 * Strip a substring rom the end of a string.
 * 
 * @param string $mainstr
 * @param string $substr
 * @return string
 */
function rtrimString($mainstr, $substr) {
   if (strcasecmp(substr($mainstr, -strlen($substr)), $substr) === 0)
      return substr($mainstr, -strlen($substr));
   return $substr;
}

function saveConfig($path, $values, $val = null) {
   if (!is_array($values)) {
      $values = array($values => $val);
   }
   
   // Load the config into a temporary array so we know what to save.
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
      case 'ini':
         $ini = iniEncode($array);
         file_put_contents($tmpPath, $ini);
         break;
      case 'json':
         $json = json_encode($array);
         file_put_contents($tmpPath, $json);
         break;
      case 'php':
         $php = '$'.$basename.' = '.var_export($array, true);
         file_put_contents($tmpPath, $php);
      case 'ser':
         $ser = serialize($array);
         file_put_contents($tmpPath, $php);
         break;
   }
   
}

/**
 * Returns whether or not a string begins with another string.
 * This function is not case-sensitive.
 * 
 * @param string $string The string to test.
 * @param string $with The substring to test against.
 * @return bool Whether or not `$string` begins with `$with`.
 */
function stringBeginsWith($string, $with) {
   return strncasecmp($string, $with, strlen($with)) === 0;
}

/**
 * Returns whether or not a string ends with another string.
 * This function is not case-sensitive.
 * 
 * @param string $string The string to test.
 * @param string $with The substring to test against.
 * @return bool Whether or not `$string` ends with `$with`.
 */
function stringEndsWith($string, $with) {
   return strcasecmp(substr($string, -strlen($with)), $with) === 0;
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
         fwrite(STDERR, ' '.implodeAssoc(', ', ': ', $data));
      
      fwrite(STDERR, "\n");
   } else {
      // This really is an error, but probably isn't worth taking out the entire script for.
      trigger_error("timerStop() called without calling timerStart() first.", E_USER_NOTICE);
   }
}

/**
 * Make sure that a key exists in an array.
 * 
 * @param string|int $key The array key to ensure.
 * @param array $array The array to modify.
 * @param mixed $default The default value to set if key does not exist.
 */
function touchValue($key, &$array, $default) {
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
 * @param string|int $key The array key.
 * @param array $array The array to get the value from.
 * @param mixed $default The default value to return if the key doesn't exist.
 * @return mixed The item from the array or `$default` if the array key doesn't exist.
 */
function val($key, $array, $default = null) {
   if (array_key_exists($key, $array))
      return $array[$key];
   return $default;
}

/**
 * Look up an item in an array and return a different value depending on whether or not that value is true/false.
 * 
 * @param string|int $key The key of the array.
 * @param array $array The array to look at.
 * @param mixed $trueValue The value to return if we have true.
 * @param mixed $falseValue The value to return if we have true.
 * @param bool $default The default value of the key isn't in the array.
 * @return mixed Either `$trueValue` or `$falseValue`.
 */
function valif($key, $array, $trueValue, $falseValue = null, $default = false) {
   if (!array_key_exists($key, $array))
      return $default ? $trueValue : $falseValue;
   elseif ($array[$key])
      return $trueValue;
   else
      return $falseValue;
}
