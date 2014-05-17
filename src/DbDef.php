<?php
namespace Garden;

/**
 * A helper class for creating database tables.
 */
class DbDef {
   /// Properties ///

   /**
    * @var Db
    */
   public $db;

   /**
    * @var array
    */
   public $columns;

   /**
    *
    * @var string The name of the currently working table.
    */
   public $table;

   /// Methods ///

   public function __construct($db) {
      $this->db = $db;
      $this->reset();
   }

   /**
    *
    * @param string $name The column name.
    * @param string $type The column type.
    * @param mixed $nullDefault Whether the column is required or it's default.
    *
    * null|true
    * : The column is not required.
    * false
    * : The column is required.
    * Anything else
    * : The column is required and this is its default.
    *
    * @param string|array $index The index that the column participates in.
    * @return DbDef
    */
   public function column($name, $type, $nullDefault = false, $index = null) {
      $this->columns[$name] = $this->columnDef($type, $nullDefault, $index);

      return $this;
   }

   public function columnDef($type, $nullDefault = false, $index = null) {
      $column = array(
         'type' => $type);

      if ($nullDefault === null || $nullDefault == true)
         $column['required'] = false;
      if ($nullDefault === false)
         $column['required'] = true;
      else {
         $column['required'] = true;
         $column['default'] = $nullDefault;
      }

      if ($index)
         $column['index'] = $index;

      return $column;
   }

   /**
    * Define the primary key in the database.
    *
    * @param string $name The name of the column.
    * @param string $type The datatype for the column.
    * @return DbDef
    */
   public function primaryKey($name, $type = 'uint') {
      $column = $this->columnDef($type, false, Db::INDEX_PK);
      $column['autoincrement'] = true;

      $this->columns[$name] = $column;

      return $this;
   }

   public function reset() {
      $this->table = null;
      $this->columns = array();

      return $this;
   }

   public function set() {
      $this->db->defineTable(
         $this->table,
         $this->columns);

      $this->reset();

      return $this;
   }

   public function table($name) {
      $this->table = $name;
      return $this;
   }
}
