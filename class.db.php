<?php

define('OP_EQ', 'eq');
define('OP_GT', 'gt');
define('OP_GTE', 'gte');
define('OP_IN', 'in');
define('OP_LT', 'lt');
define('OP_LTE', 'lte');
define('OP_NE', 'ne');

define('OP_AND', 'and');
define('OP_OR', 'or');

define('OP_ASC', 'asc');
define('OP_DESC', 'desc');

/**
 * Base class for all database access.
 */
abstract class Db {
   /// Constants ///
   
   const GET_UNBUFFERED = 'unbuffered';
   const INSERT_REPLACE = 'replace';
   const INSERT_IGNORE = 'ignore';
   const UPDATE_UPSERT = 'upsert';
   
   const INDEX_PK = 'primary';
   const INDEX_FK = 'key';
   const INDEX_IX = 'index';
   const INDEX_UNIQUE = 'unique';
   
   const MODE_EXEC = 'exec';
   const MODE_CAPTURE = 'capture';
   const MODE_ECHO = 'echo';
   
   const QUERY_DEFINE = 'define';
   const QUERY_READ = 'read';
   const QUERY_WRITE = 'write';
   
   /// Methods ///
   
   /**
    * Join data from the database to a result array. This is the basic code join.
    * In a scalable system doing joins in the database can be costly and thus moving
    * joins to code is necessary.
    * 
    * @param array $data
    * @param string|array $on The on column.
    * @param array $childcolumns
    * @param array $options
    */
   function join(&$data, $on, $childcolumns = array(), $options = array()) {
      
   }
   
   /**
    * Define a table in the database.
    * 
    * @param string $table The name of the table.
    * @param array $columns An array of columns in the following format:
    * 
    *  array(
    *      columnName => array('type' => 'dbtype' [,'required' => bool] [, 'index' => string|array])
    *  )
    * 
    * @param array $options Additional options for the table.
    * 
    * collate
    * : The database collation for the table.
    */
   abstract function defineTable($table, $columns, $options = array());
}