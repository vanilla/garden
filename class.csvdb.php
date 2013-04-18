<?php defined('APPLICATION') or die('@!');


class CsvDb extends Db {
   /// Constants ///

   const DELIM = ',';
   const ESCAPE = '\\';
   const NEWLINE = "\n";
   const NULL = '\N';
   const QUOTE = '"';
   
   
   /// Properties ///
   
   /**
    * How many rows to buffer before writing to a csv file.
    * Specifying a value of 1 or less means no buffering.
    * @var int 
    */
   public $buffer = 100;
   
   
   /// Protected Properties ///
   
   /**
    * The directory that the csv files are in.
    * @var string 
    */
   protected $dir;
   
   /**
    * The names of the currently loading columns.
    * 
    * @var array
    */
   protected $loadColumns;
   
   /**
    * A pointer to the currently loading table.
    * 
    * @var resource
    */
   protected $loadfp;
   
   /**
    * Whether or not the mb_* functions are supported.
    * 
    * @var bool 
    */
   protected static $mb = false;
   
   /**
    * 
    * @var array 
    */
   protected $structure = array();
   
   /// Methods ///
   
   public function __construct($dir) {
      $dir = rtrim($dir, '/');
      
      // Create the directory.
      ensureDir($dir);
      
      $this->dir = $dir;
      $this->loadStructure();
      
      self::$mb = function_exists('mb_detect_encoding');
   }

   public function defineIndex($table, $column, $type, $suffix = null) {
      $def = parent::defineIndex($table, $column, $type, $suffix);
      $this->structure[$table]['indexes'][$def['name']] = $def;
      $this->saveStructure();
   }

   public function defineTable($tabledef, $options = array()) {
      $tabledef = $this->fixTableDef($tabledef, $options);
      $table = $tabledef['name'];
      
      $path = $this->tablePath($table);
      
      
      $currentDef = $this->tableDefinition($table);
      
      if ($currentDef) {
         // There is already a definition which won't work if there is already data in the table.
         if (file_exists($path)) {
            // Make sure the tables have the same columns.
            if (array_keys($tabledef['columns']) !== array_keys($currentDef['columns'])) {
               throw new Exception("You can't change the definition of the $table.csv table after it has data in it.", 400);
            }
         }
         
         // We can merge indexes.
         if (isset($currentDef['indexes'])) {
            $tabledef['indexes'] = array_merge($currentDef['indexes'], $tabledef['indexes']);
         }
      }
      
      $this->structure[$tabledef['name']] = $tabledef;
      $this->saveStructure();
   }

   public function delete($table, $where) {
      if (!empty($where)) {
         throw new NotImplementedException("CsvDb->delete() does not implement a delete with a where filter.");
      }
      $path = $this->tablePath($table);
      if (file_exists($path))
         unlink($path);
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
   
   public function get($table, $where, $order = array(), $limit = false, $options = array()) {
      throw NotImplementedException(__CLASS__, 'get');
   }

   public function indexDefinitions($table) {
      $tabledef = $this->tableDefinition($table);
      if (!$tabledef)
         return null;
      return val('indexes', $tabledef, array());
   }

   public function insert($table, $row, $options = array()) {
      if ($this->buffer > 1) {
         // Buffer the insert.
         $this->insertBuffer[$table][] = $row;
         
         // Check to see if the insert buffer is full.
         if (count($this->insertBuffer[$table]) >= $this->buffer) {
            $this->insertMulti($table, $this->insertBuffer[$table], $options);
            unset($this->insertBuffer[$table]);
         }
      } else {
         return $this->insertMulti($table, array($row), $options);
      }
   }
   
   public function insertMulti($table, $rows, $options = array()) {
//      if (!$tabledef) {
//         // Try and define the table from the row.
//         $columns = array();
//         foreach ($row as $column => $value) {
//            $columns[$column] = array('type' => $this->guessType($value));
//         }
//         
//         $this->defineTable($table, $columns, $options);
//         $tabledef = $this->tableDefinition($table);
//      }

      // Loop through the rows and insert them.
      $columns = array_keys($tabledef['columns']);
      foreach ($rows as $row) {
         fwrite($fp, self::format($row, $columns));
      }
      
      fclose($fp);
   }
   
   public static function formatRow($row, $columns) {
      $outrow = array_fill_keys($columns, null);
         
      foreach ($columns as $column) {
         if (isset($row[$column])) {
            $outrow[$column] = self::formatValue($row[$column]);
         }
      }
      
      return implode(self::DELIM, $outrow).self::NEWLINE;
   }
   
   public function loadStart($table) {
      parent::loadStart($table);
      
      // Grab the table definition.
      $tabledef = $this->tableDefinition($table);
      
      if (!$tabledef)
         throw Exception("Table $table does not exist.");
      
      $context =& $this->loadContexts[$table];
      
      if (isset($context['fp']))
         $fp = $context['fp'];
      else {
         // Set up the file for inserting.
         $path = $this->tablePath($table);
         if (!file_exists($path)) {
            $fp = fopen($path, 'wb');
            $this->writeHeaderRow($fp, $tabledef);
         } else {
            $fp = fopen($path, 'ab');
         }
         $context['fp'] = $fp;
      }
      
      $this->loadfp = $fp;
      $this->loadColumns = array_keys($tabledef['columns']);
   }
   
   public function loadRow($row) {
      $line = self::formatRow($row, $this->loadColumns);
      fwrite($this->loadfp, $line);
      $this->loadContexts[$this->loadCurrent]['count']++;
   }
   
   public function loadFinish() {
      $table = $this->loadCurrent;
      $context =& $this->loadContexts[$table];
      parent::loadFinish();
      
      if ($context['calls'] <= 0 && is_resource($this->loadfp)) {
         fclose($this->loadfp);
         unset($context['fp']);
      }
      
      $this->loadfp = null;
      $this->loadColumns = null;
      
      return $context;
   }
   
   protected function loadStructure() {
      $path = $this->dir.'/structure.json';
      if (file_exists($path))
         $this->structure = json_decode(file_get_contents($path), true);
      else
         $this->structure = array();
   }
   
   protected function saveStructure() {
      $path = $this->dir.'/structure.json';
      file_put_contents($path, json_encode($this->structure));
   }

   public function tableDefinition($table) {
      return val($table, $this->structure, null);
   }
   
   protected function tablePath($table) {
      return $this->dir."/$table.csv";
   }

   public function tables($withdefs = false) {
      if ($withdefs) {
         return $this->structure;
      } else {
         return array_keys($this->structure);
      }
   }

   public function update($table, $row, $where, $options = array()) {
      throw new NotImplementedException(__CLASS__, 'update');
   }
   
   protected function writeHeaderRow($fp, $table) {
      if (is_string($table))
         $table = $this->tableDefinition($table);
      
      $columns = array_keys($table['columns']);
      fwrite($fp, implode(self::DELIM, $columns));
      fwrite($fp, self::NEWLINE);
   }
}