<?php if (!defined('APP')) return;

/**
 * HTTP Request representation
 * 
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2003 Vanilla Forums, Inc
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPLv2
 * @package quickapi
 * @since 1.0
 */

class Request {
   /// Constants ///
   const METHOD_DELETE = 'delete';
   const METHOD_GET = 'get';
   const METHOD_HEAD = 'head';
   const METHOD_POST = 'post';
   const METHOD_PUT = 'put';
   
   const P = '_p';
   
   /// Methods ///
   
   public function __construct($url = null, $get = null, $post = null, $method = null) {
      if ($url === null && $get === null && $post === null) {
         if (isset($_GET[self::P])) {
            $url = $_GET[self::P];
            unset($_GET[self::P]);
         }
         $get = $_GET;
         $post = $_POST;
      }

      if ($get !== null) {
         $this->_get = array_merge($this->_get, $get);
      }

      if ($post !== null) {
         $this->post($post);
      }
      
      // Host & Method
      
      $this->host(     isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? val('HTTP_X_FORWARDED_HOST',$_SERVER) : (isset($_SERVER['HTTP_HOST']) ? val('HTTP_HOST',$_SERVER) : val('SERVER_NAME',$_SERVER)));
      $this->method(   isset($_SERVER['REQUEST_METHOD']) ? val('REQUEST_METHOD',$_SERVER) : 'CONSOLE');

      // IP Address
      
      // Loadbalancers
      $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? val('HTTP_X_FORWARDED_FOR',$_SERVER) : $_SERVER['REMOTE_ADDR'];
      if (strpos($ip, ',') !== FALSE) $ip = substr($ip, 0, strpos($ip, ','));
      // Varnish
      $originalIP = val('HTTP_X_ORIGINALLY_FORWARDED_FOR', $_SERVER, NULL);
      if (!is_null($originalIP)) $ip = $originalIP;
      
      // Make sure we have a valid ip.
      if (preg_match('`(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})`', $ip, $m))
         $ip = $m[1];
      else
         $ip = '0.0.0.0'; // unknown ip
      
      $this->ip($ip);
      
      // Scheme
      
      $scheme = 'http';
      // Webserver-originated SSL
      if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') $scheme = 'https';
      // Loadbalancer-originated (and terminated) SSL
      if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https') $scheme = 'https';
      // Varnish
      $OriginalProto = val('HTTP_X_ORIGINALLY_FORWARDED_PROTO', $_SERVER, NULL);
      if (!is_null($OriginalProto)) $scheme = $OriginalProto;
      
      $this->scheme($scheme);
      
      // Figure out the root.
      $scriptName = $_SERVER['SCRIPT_NAME'];
      if ($scriptName && substr($scriptName, -strlen('index.php')) == 0) {
         $root = substr($scriptName, 0, -strlen('index.php'));
         $this->root($root);
      }
      
      if (is_string($url)) {
         $this->url($url);
      }
   }

   public function __toString() {
      return $this->url();
   }
   
   /**
    * Initialize a curl request from the information in this request.
    * 
    * @param resource $ch
    */
   public function curlInit(&$ch = null) {
      if (!is_resource($ch)) {
         $ch = curl_init();
      }
      curl_setopt($ch, CURLOPT_URL , $this->url());
      if (!empty($this->_post)) {
         curl_setopt($ch, CURLOPT_POST, 1);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->_post, null, '&'));
      }
      
      return $ch;
   }

   protected $_get = array();
   public function get($key = null, $default = null) {
      if ($key === null)
         return $this->_get;
      if (is_string($key))
         return isset($this->_get[$key]) ? $this->_get[$key] : $default;
      if (is_array($key))
         $this->_get = $key;
   }
   
   protected $_ip = array();
   public function ip($ip = null) {
      if ($ip === null)
         return $this->_ip;
      $this->_ip = $ip;
   }

   protected $_host = array();
   public function host($host = null) {
      if ($host === null)
         return $this->_host;
      $this->_host = $host;
   }
   
   protected $_method = array();
   public function method($method = null) {
      if ($method === null)
         return $this->_method;
      $this->_method = strtolower($method);
   }

   protected $_path = array();
   public function path($path = null) {
      if (is_string($path)) {
         $path = trim(trim($path, '/'));
         if (empty($path))
            $this->_path = array();
         else
            $this->_path = explode('/', $path);
      } elseif (is_array($path)) {
         $this->_path = $path;
      } elseif (is_numeric($path)) {
         if (array_key_exists($path, $this->_path))
            return $this->_path[$path];
         else
            return '';
      } else {
         return $this->_path;
      }
   }
   
//   protected $_pathArgs = NULL;
//   public function pathArgs($path = null) {
//      $pathArgs = is_null($this->_pathArgs) ? $this->path() : $this->_pathArgs;
//      if (is_string($path)) {
//         $path = trim(trim($path, '/'));
//         if (empty($path))
//            $this->_pathArgs = array();
//         else
//            $this->_pathArgs = explode('/', $path);
//      } elseif (is_array($path)) {
//         $this->_pathArgs = $path;
//      } elseif (is_numeric($path)) {
//         if (array_key_exists($path, $pathArgs))
//            return $pathArgs[$path];
//         else
//            return NULL;
//      } else {
//         return $pathArgs;
//      }
//   }

   protected $_post = array();
   public function post($key = null, $default = null) {
      if ($key === null)
         return $this->_post;
      if (is_string($key))
         return isset($this->_post[$key]) ? $this->_post[$key] : $default;
      if (is_array($key))
         $this->_post = $key;
   }
   
   protected $_root = '';
   public function root($value = null) {
      if ($value !== null) {
         $value = trim($value, '/');
         if (!empty($value))
            $value = '/'.$value;
         $this->_root = $value;
      }
      return $this->_root;
   }
   
   protected $_scheme = 'http';
   public function scheme($value = null) {
      if ($value === null)
         return $this->_scheme;
      $this->_scheme = $value;
   }

   public function url($url = null) {
      if ($url !== null) {
         $urlParts = parse_url($url);
         $this->path(trim($urlParts['path'], '/'));
         if (isset($urlParts['query']))
            $this->_get = parse_str($urlParts['query']);
      }

      $pathEncoded = implode('/', array_map('rawurlencode', $this->_path));
      return $this->scheme().'://'.$this->host().$this->root().'/'.$pathEncoded.(!empty($this->_get) ? '?'.http_build_query($this->_get) : '');
   }
}

