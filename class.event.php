<?php

class Event {
   const PRIORITY_LOW = 20;
   const PRIORITY_MEDIUM = 10;
   const PRIORITY_HIGH = 1;
   
   /// Properties ///
   
   protected static $handlers = array();
   
   protected static $toSort = array();
   
   /// Methods ///
   
   public static function bind($event, $callback, $priority = self::PRIORITY_MEDIUM) {
      self::$handlers[$event][$priority][] = $callback;
      self::$toSort[$event] = true;
   }
   
   /**
    * Fire an event.
    * 
    * @param string $event The name of the event.
    * @return int How many times the event was handled.
    */
   public static function fire($event) {
      $handlers = self::getHandlers($event);
      if ($handlers === false)
         return 0;
      
      // Grab the handlers and call them.
      $args = array_slice(func_get_args(), 1);
      $count = 0;
      foreach ($handlers as $callbacks) {
         foreach ($callbacks as $callback) {
            call_user_func_array($callback, $args);
            $count++;
         }
      }
      return $count;
   }
   
   /**
    * Chain several event handlers together.
    * This method will fire the first handler and pass its result as the first argument
    * to the next event handler and so on. A chained event handler can have more than one parameter,
    * but must have at least one parameter.
    * 
    * @param string $name
    * @param mixed The value to pass into the filter.
    * @return mixed The result of the chained event or `$value` if there were no handlers.
    */
   public static function fireChained($event, $value) {
      $handlers = self::getHandlers($event);
      if ($handlers === false)
         return $value;
      
      $args = array_slice(func_get_args(), 1);
      foreach ($handlers as $callbacks) {
         foreach ($callbacks as $callback) {
            $value = call_user_func_array($callback, $args);
            $args[0] = $value;
         }
      }
      return $value;
   }
   
   /**
    * Get all of the handlers bound to an event.
    * 
    * @param string $name
    * @return boolean
    */
   public static function getHandlers($name) {
      if (!isset(self::$handlers[$name]))
         return false;
      
      // See if the handlers need to be sorted.
      if (isset(self::$toSort[$name])) {
         ksort(self::$handlers[$name]);
         unset(self::$toSort[$name]);
      }
      
      return self::$handlers[$name];
   }
}
