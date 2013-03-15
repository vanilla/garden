<?php

if (!defined('APPLICATION'))
   exit();

$config = array();

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

$urlTranslations = array('–' => '-', '—' => '-', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'Ae', 'Ä' => 'A', 'Å' => 'A', 'Ā' => 'A', 'Ą' => 'A', 'Ă' => 'A', 'Æ' => 'Ae', 'Ç' => 'C', 'Ć' => 'C', 'Č' => 'C', 'Ĉ' => 'C', 'Ċ' => 'C', 'Ď' => 'D', 'Đ' => 'D', 'Ð' => 'D', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ē' => 'E', 'Ě' => 'E', 'Ĕ' => 'E', 'Ė' => 'E', 'Ĝ' => 'G', 'Ğ' => 'G', 'Ġ' => 'G', 'Ģ' => 'G', 'Ĥ' => 'H', 'Ħ' => 'H', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I', 'Ī' => 'I', 'Ĩ' => 'I', 'Ĭ' => 'I', 'Į' => 'I', 'İ' => 'I', 'Ĳ' => 'IJ', 'Ĵ' => 'J', 'Ķ' => 'K', 'Ł' => 'K', 'Ľ' => 'K', 'Ĺ' => 'K', 'Ļ' => 'K', 'Ŀ' => 'K', 'Ñ' => 'N', 'Ń' => 'N', 'Ň' => 'N', 'Ņ' => 'N', 'Ŋ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'Oe', 'Ö' => 'Oe', 'Ō' => 'O', 'Ő' => 'O', 'Ŏ' => 'O', 'Œ' => 'OE', 'Ŕ' => 'R', 'Ŗ' => 'R', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ŝ' => 'S', 'Ť' => 'T', 'Ţ' => 'T', 'Ŧ' => 'T', 'Ț' => 'T', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'Ue', 'Ū' => 'U', 'Ü' => 'Ue', 'Ů' => 'U', 'Ű' => 'U', 'Ŭ' => 'U', 'Ũ' => 'U', 'Ų' => 'U', 'Ŵ' => 'W', 'Ý' => 'Y', 'Ŷ' => 'Y', 'Ÿ' => 'Y', 'Ź' => 'Z', 'Ž' => 'Z', 'Ż' => 'Z', 'Þ' => 'T', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'ae', 'ä' => 'ae', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a', 'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ĉ' => 'c', 'ċ' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ĕ' => 'e', 'ė' => 'e', 'ƒ' => 'f', 'ĝ' => 'g', 'ğ' => 'g', 'ġ' => 'g', 'ģ' => 'g', 'ĥ' => 'h', 'ħ' => 'h', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĩ' => 'i', 'ĭ' => 'i', 'į' => 'i', 'ı' => 'i', 'ĳ' => 'ij', 'ĵ' => 'j', 'ķ' => 'k', 'ĸ' => 'k', 'ł' => 'l', 'ľ' => 'l', 'ĺ' => 'l', 'ļ' => 'l', 'ŀ' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n', 'ŉ' => 'n', 'ŋ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'oe', 'ö' => 'oe', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o', 'ŏ' => 'o', 'œ' => 'oe', 'ŕ' => 'r', 'ř' => 'r', 'ŗ' => 'r', 'š' => 's', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'ue', 'ū' => 'u', 'ü' => 'ue', 'ů' => 'u', 'ű' => 'u', 'ŭ' => 'u', 'ũ' => 'u', 'ų' => 'u', 'ŵ' => 'w', 'ý' => 'y', 'ÿ' => 'y', 'ŷ' => 'y', 'ž' => 'z', 'ż' => 'z', 'ź' => 'z', 'þ' => 't', 'ß' => 'ss', 'ſ' => 'ss', 'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'YO', 'Ж' => 'ZH', 'З' => 'Z', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'ș' => 's', 'ț' => 't', 'Ț' => 'T',  'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya');


/**
 * Replaces all non-url-friendly characters with dashes.
 *
 * @param string $str An object, array, or string to be formatted.
 * @return string
 */
function formatUrl($str) {
   global $urlTranslations;
   
   $str = trim($str);
   $str = strip_tags(html_entity_decode($str, ENT_COMPAT, 'UTF-8'));
   $str = strtr($str, $urlTranslations);
   $str = preg_replace('`([^\PP.\-_])`u', '', $str); // get rid of punctuation
   $str = preg_replace('`([^\PS+])`u', '', $str); // get rid of symbols
   $str = preg_replace('`[\s\-/+.]+`u', '-', $str); // replace certain characters with dashes
   $str = rawurlencode(strtolower($str));
   $str = trim($str, '.-');
   return $str;
}

/**
 * Load configuration information from an ini file.
 * 
 * @global array $config The global config array.
 * 
 * @param string $path
 * @param bool $merge
 */
function loadConfig($path, $merge = true) {
   global $config;

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

   if ($merge && !empty($config))
      $config = array_merge($config, $loaded);
   else
      $config = $loaded;
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