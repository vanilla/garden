<?php defined('APPLICATION') or die();
/**
 * issued
 * : The unix timestamp when the request was issued.
 * expires
 * : The unix timestamp when the request expires.
 * rtoken
 * : A token the request must return in its response.
 */


class VanillaSSO {
   /// Properties ///
   
   public $get;
   
   public $clientID;
   
   public $secret;
   
   /// Methods ///
   
   public function decodeRequest($request) {
      $parts = explode('.', $request, 2);
      
      $this->validateRequired(1, 'signature');
      $requestString = $parts[1];
      $signature = $parts[2];
      $calcSignature = $this->sign($requestString);
      
      if ($signature !== $calcSignature)
         $this->error('invalid', 'signature', "The request's signature was invalid.");
      
      // Decode the request now.
      $request = json_decode(base64UrlDecode($requestString));
      return $request;
   }
   
   protected function error($error, $field, $description, &$errors = null) {
      if ($errors === null) {
         $this->write(array(
            'error' => "{$error}_{$field}",
            'error_description' => $description));
      } else {
         $errors["{$error}_{$field}"] = $description;
      }
   }
   
   public function sign($string) {
      return base64UrlEncode(hash_hmac('sha256', $data, $this->secret));
   }
   
   public function response($user, $get = null) {
      if (!$get)
         $get = $_GET;
      
      $this->get = $get;
      
      // Decode the request.
      $this->validateRequired('request');
      $request = $this->decodeRequest($this->get['request']);
   }
   
   protected function validateRequired($field, $name = null, &$errors = null) {
      if (!$name)
         $name = $field;
      
      if (!isset($this->get[$field]) || !trim($this->get[$field])) {
         $this->error('missing', $name, "The request is missing the required $name parameter.", $errors);
      }
   }
   
   public function write($response) {
      $json = json_encode($response);
   
      if (isset($this->get['callback'])) {
         echo "{$this->get['callback']}($json);";
      } else {
         echo $json;
      }
      
      die();
   }
}
