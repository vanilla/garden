<?php

/**
 * An ErrorException that also contains the context from the error.
 */
class ExceptionFromError  extends ErrorException {
   protected $context;
   
   public function __construct($message, $errno, $file, $line, $context) {
      parent::__construct($message, $errno, 0, $file, $line);
      $this->context = $context;
   }
   
   public function getContext() {
      return $this->context;
   }
}

/**
 * The base error handler that changes an error into an exception.
 * 
 * @param int $errno The code of the error.
 * @param string $message The error message.
 * @param string $file The file that the error occurred in.
 * @param int $line The line that the error occurred on.
 * @param array $context All of the variables that are currently defined. 
 * @return boolean Returns false if the error is below the current error reporting level.
 * @throws ExceptionFromError All errors that are thrown throw this exception.
 */
function errorHandler($errno, $message, $file, $line, $context) {
   $reporting = error_reporting();
   
   // Ignore errors that are below the current error reporting level.
   if (($reporting & $errno) != $errno)
      return FALSE;
   
//   $backtrace = debug_backtrace();
   
//   if (($errono & (E_NOTICE | E_USER_NOTICE)) > 0 & function_exists('Trace')) {
//      $Tr = '';
//      $i = 0;
//      foreach ($Backtrace as $Info) {
//         if (!isset($Info['file']))
//            continue;
//         
//         $Tr .= "\n{$Info['file']} line {$Info['line']}.";
//         if ($i > 2)
//            break;
//         $i++;
//      }
//      Trace("$errstr{$Tr}", TRACE_NOTICE);
//      return FALSE;
//   }
   
   var_dump($context);
   
   throw new ExceptionFromError($message, $errno, $file, $line, $context);
}

set_error_handler('errorHandler', E_ALL);