<?php namespace Garden;

use Garden\Exception\ClientException;

define('OP_EQ', 'eq');
define('OP_GT', 'gt');
define('OP_GTE', 'gte');
define('OP_IN', 'in');
define('OP_LIKE', 'like');
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
    const GET_COLUMN = 'getcolumn';

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

    const COLUMNS = 'columns';
    const LIMIT = 'limit';
    const ORDERBY = 'orderby';

    /// Properties ///

    /**
     * @var string The database table prefix.
     */
    public $px = 'gdn_';

    /**
     * Context data for tables that are currently loading.
     *
     * @var array An array in the form:
     *
     *     array(
     *        tablename => array([context information])
     *     )
     */
    protected $loadContexts;

    /**
     * The name of the currently loading table.
     * @var string
     */
    protected $loadCurrent;

    /**
     * @var int
     */
    protected $rowCount;

    /// Methods ///

    /**
     * Initialize this object.
     * @param array $config A config array used to initialize this object.
     */
    public function __construct($config = []) {

    }

    /**
     * Create the appropriate db driver given a config.
     * @param array $config The configuration used to initialize the object.
     * The config must have a driver key which names the db class to create.
     * @throws Exception\ClientException Throws an exception when the config isn't complete.
     */
    public static function create($config) {
        $driver = val('driver', $config);
        if (!$driver) {
            throw new ClientException('You must specify a driver.', 400);
        }

        if (strpos($driver, '\\') === false) {
            $class = '\Garden\Driver\\'.$driver;
        } else {
            // TODO: Check against a white list of db drivers.
            $class = $driver;
        }

        if (!class_exists($class)) {
            throw new ClientException("Class $class does not exist.", 404);
        }

        $db = new $class($config);
        return $db;
    }

    /**
     * Define an index in the database.
     *
     * Db->defineIndex() will check to see if the index already exists, and if it doesn't then it will create it.
     *
     * @param string $table The name of the table that the index is on.
     * @param array|string $column The name(s) of the columns in the index.
     * @param string $type One of the Db::INDEX_* constants.
     *
     * Db::INDEX_PK
     * : This index is the primary key
     *
     * Db::INDEX_FK
     * : This index is a foreign key. Its column(s) point to the primary key of another table.
     *
     * Db::INDEX_IX
     * : This is a regular index.
     *
     * Db::INDEX_UNIQUE
     * : This is a unique index.
     *
     * @param string $suffix By default the index will be named based on the column that it's on.
     *    This suffix overrides that.
     * @return array The index definition. This array will have the following keys.
     *
     * name
     * : The name of the index.
     *
     * type
     * : The type of the index.
     *
     * columns
     * : The names of the columns in the index.
     */
    public function defineIndex($table, $column, $type, $suffix = null) {
        $columns = (array)$column;

        // Determine the name of the new index.
        if ($type === Db::INDEX_PK) {
            $name = 'PRIMARY';
        } else {
            $prefixes = array(Db::INDEX_FK => 'FK_', Db::INDEX_IX => 'IX_', Db::INDEX_UNIQUE => 'UX_');
            $px = val($type, $prefixes, 'IX_');
            if (!$suffix && $type != Db::INDEX_UNIQUE)
                $suffix = implode('', $columns);
            $name = "{$px}{$table}".($suffix ? "_{$suffix}" : '');
        }

        $result = array(
            'name' => $name,
            'type' => $type,
            'columns' => $columns);

        return $result;
    }

    /**
     * Define a table in the database.
     *
     * @param array $table The table structure definition. The array should have the following keys:
     *
     * name
     * : The name of the table.
     *
     * columns
     * : The table's columns. This array should have the following format:
     *     <pre>
     *     array(
     *        columnName => array('type' => 'dbtype' [,'required' => bool] [, 'index' => string|array])
     *     )
     *     </pre>
     *
     * indexes
     * : Any additional indexes the table should have.
     *
     * @param array $options Additional options for the table.
     *
     * collate
     * : The database collation for the table. Not all database drivers support this option.
     */
    abstract public function defineTable($tabledef, $options = array());

    /**
     * Delete data from a table.
     *
     * @param string $table The table to delete from.
     * @param array $where An array specifying the where clause.
     */
    abstract public function delete($table, $where);

    public abstract function get($table, $where, $options = array());

    /**
     * Guess a database type from a value.
     * @param mixed $value
     * @return string
     */
    public function guessType($value) {
        $type = 'varchar(255)';

        if (is_bool($value))
            $type = 'tinyint';
        elseif (is_int($value))
            $type = 'int';
        elseif (is_float($value))
            $type = 'float';
        elseif (is_double($value))
            $type = 'double';
        elseif (preg_match('`\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)Z?`', $value))
            $type = 'datetime';
        elseif (is_string($value)) {
            if (strlen($value) > 255)
                $type = 'text';
        }

        return $type;
    }

    /**
     * Get the index definitions for a table.
     *
     * @param string $table The name of the table
     * @return array An array in the form:
     *
     *     array (
     *        index name => array('columns' => array('column'), 'type' => Db::INDEX_TYPE)
     *     )
     */
    abstract public function indexDefinitions($table);

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
    public function join(&$data, $on, $childcolumns = array(), $options = array()) {

    }

    /**
     * Start loading a table.
     * This method is part of the data loading api.
     * The loadStart(), loadRow(), and loadFinish() methods are meant to be used together for this.
     *
     * @param string $table The name of the table to load.
     */
    public function loadStart($table) {
        $this->loadContexts = (array)$this->loadContexts;
        array_touch($table, $this->loadContexts, array('calls' => 0, 'count' => 0));
        $this->loadContexts[$table]['calls']++;

        $this->loadCurrent = $table;
    }

    /**
     * Load a row in a table.
     * This method is part of the data loading api.
     * The loadStart(), loadRow(), and loadFinish() methods are meant to be used together for this.
     *
     * @param array $row The row to load.
     */
    public function loadRow($row) {
        $this->insert($this->loadCurrent, $row);
        $this->loadContexts[$this->loadCurrent]['count']++;
    }

    /**
     * Insert a row into the database.
     * @param string $table The name of the table to insert into.
     * @param array $row The row to insert into the table.
     * @param array $options Options to modify the insert.
     */
    abstract public function insert($table, $row, $options = array());

    /**
     * Finish loading a table.
     *
     * This method is part of the data loading api.
     * The loadStart(), loadRow(), and loadFinish() methods are meant to be used together for this.
     */
    public function loadFinish() {
        $context = $this->loadContexts[$this->loadCurrent];
        $this->loadContexts[$this->loadCurrent]['calls']--;
        $this->loadCurrent = null;

        return $context;
    }

    /**
     * Returns the number of rows affected by the last query.
     * @return int
     */
    public function rowCount() {
        return $this->rowCount;
    }

    public function tableExists($table) {
        return $this->tableDefinition($table) !== null;
    }

    /**
     * Return the definition for a table.
     *
     * @param string $table The name of the table.
     * @return array
     */
    abstract public function tableDefinition($table);

    abstract public function tables($withdefs = false);

    abstract public function update($table, $row, $where, $options = array());

    protected function fixTableDef($table, $columns = null) {
        $tabledef = array();
        $columns = array();
        $indexes = array();

        if (is_array($table)) {
            $tabledef = $table;
            if (isset($tabledef['columns']))
                $columns = $tabledef['columns'];
        } else {
            $tabledef = array('name' => $table);
        }

        foreach ($columns as $name => $def) {
            $index = val('index', $def);
            if ($index) {
                // The column has one or more indexes on them.
                foreach ((array)$index as $typeString) {
                    $parts = explode('.', $typeString, 2);
                    $type = strtolower($parts[0]);
                    $suffix = val(1, $parts);

                    if ($type == Db::INDEX_PK)
                        $columns[$name]['required'] = true;

                    // Save the index for later.
                    if ($type == Db::INDEX_PK || $type == Db::INDEX_UNIQUE || $suffix) {
                        // There is only one index of this type.
                        $indexes[$typeString]['columns'][] = $name;
                        $indexes[$typeString]['type'] = $type;
                        $indexes[$typeString]['suffix'] = $suffix;
                    } else {
                        // One columns, one index.
                        $indexes[] = array('columns' => $name, 'type' => $type);
                    }
                }
            }
        }

        $tabledef['columns'] = $columns;
        $tabledef['indexes'] = $indexes;

        return $tabledef;
    }
}