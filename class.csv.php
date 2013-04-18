<?php

class Csv {
   /// Constants ///

   const DELIM = ',';
   const ESCAPE = '\\';
   const NEWLINE = "\n";
   const NULL = '\N';
   const QUOTE = '"';

   /// Properties ///

   /**
    * @var MySqlDb 
    */
   public $db;
   
   protected static $mb = false;

   /// Methods ///
   
   public function __construct($db = null) {
      $this->db = $db;
      self::$mb = function_exists('mb_detect_encoding');
   }

   function dump($path) {
      // Figure out the file.
      if (file_exists($path)) {
         if (is_dir($path))
            $dir = $path;
         else {
            $dir = tempnam(__DIR__, 'dbdump_');
         }
      } else {
         if (substr($path, -1) === '/') {
            // This is a dir.
            mkdir($path, 0777, true);
            $dir = $path;
            $path = false;
         } else {
            $dir = mkdir(tempnam(__DIR__, 'dbdump_'));
         }
      }
      $dir = rtrim($dir, '/');

      // Dump the table defs.
      $defs = $this->db->tables(true);
      $path = $dir . "/structure.json";
      fwrite(STDERR, $path . "\n");
      file_put_contents($path, json_encode($defs, JSON_PRETTY_PRINT));

      // Dump the tables.
      foreach ($defs as $table => $def) {
         $this->dumpTable($def, $dir);
      }
   }

   /**
    * 
    * @param MySqlDb $db
    * @param string|array $table
    * @param string $path
    */
   function dumpTable($table, $path) {
      $db = $this->db;
      
      // Get the table structure.
      if (is_string($table)) {
         $table = $db->tableDefinition($table);
      }

      // Prepare teh export path.
      if (is_dir($path)) {
         $path .= "/{$table['name']}.csv";
      }
      fwrite(STDERR, $path . "\n");
      $fp = fopen($path, 'wb');

      // Write the table header.
      $columns = array_keys($table['columns']);
      fwrite($fp, implode(self::DELIM, $columns));
      fwrite($fp, self::NEWLINE);

      // Grab the data and write.
      $result = $db->get($table['name'], null, null, null, array(Db::GET_UNBUFFERED => true));
      
      foreach ($result as $row) {
         $outrow = array_fill_keys($columns, null);

         foreach ($columns as $column) {
            if (isset($row[$column])) {
               $outrow[$column] = self::formatValue($row[$column]);
            }
         }

         fwrite($fp, implode(self::DELIM, $outrow));
         fwrite($fp, self::NEWLINE);
      }
      fclose($fp);
   }

   static function formatValue($value) {
      // Format the value for writing.
      if (is_null($value)) {
         $value = self::NULL;
      } elseif (is_numeric($value)) {
         // Do nothing, formats as is.
      } elseif (is_string($value)) {
         if (self::$mb && mb_detect_encoding($value) != 'UTF-8')
            $value = utf8_encode($value);

         $value = str_replace(array("\r\n", "\r"), array(self::NEWLINE, self::NEWLINE), $value);
         $value = self::QUOTE .
            str_replace(array(self::ESCAPE, self::DELIM, self::NEWLINE, self::QUOTE), array(self::ESCAPE . self::ESCAPE, self::ESCAPE . self::DELIM, self::ESCAPE . self::NEWLINE, self::ESCAPE . self::QUOTE), $value) .
            self::QUOTE;
      } elseif (is_bool($value)) {
         $value = $value ? 1 : 0;
      } else {
         // Unknown format.
         $value = self::NULL;
      }
      return $value;
   }
}
