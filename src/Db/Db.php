<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

/**
 * Defines a standard set of methods that all database drivers must conform to.
 */
abstract class Db {
    /// Constants ///

    const QUERY_DEFINE = 'define';
    const QUERY_READ = 'read';
    const QUERY_WRITE = 'write';

    const INDEX_PK = 'primary';
    const INDEX_IX = 'index';
    const INDEX_UNIQUE = 'unique';

    const OPTION_REPLACE = 'replace';
    const OPTION_IGNORE = 'ignore';
    const OPTION_UPSERT = 'upsert';
    const OPTION_TRUNCATE = 'truncate';

    const OP_EQ = '=';
    const OP_GT = '>';
    const OP_GTE = '>=';
    const OP_IN = 'in';
    const OP_LIKE = 'like';
    const OP_LT = '<';
    const OP_LTE = '<=';
    const OP_NE = '<>';

    const OP_AND = 'and';
    const OP_OR = 'or';

    const OP_ASC = 'asc';
    const OP_DESC = 'desc';

    const FETCH_TABLENAMES = 0x1;
    const FETCH_COLUMNS = 0x2;
    const FETCH_INDEXES = 0x3;

    /// Properties ///

    /**
     * @var string The database prefix.
     */
    protected $px = 'gdn_';

    /**
     * @var array A cached copy of the table schemas.
     */
    protected $tables = [];

    /**
     * @var int Whether or not all the tables have been fetched.
     */
    protected $allTablesFetched = 0;

    /**
     * @var int The number of rows that were affected by the last query.
     */
    protected $rowCount;

    /// Methods ///

    /**
     * Create the appropriate db driver given a config.
     *
     * @param array $config The configuration used to initialize the object.
     * The config must have a driver key which names the db class to create.
     * @throws \Exception Throws an exception when the config isn't complete.
     */
    public static function create($config) {
        $driver = val('driver', $config);
        if (!$driver) {
            throw new \Exception('You must specify a driver.', 500);
        }

        if (strpos($driver, '\\') === false) {
            $class = '\Garden\Db\\'.$driver;
        } else {
            // TODO: Check against a white list of db drivers.
            $class = $driver;
        }

        if (!class_exists($class)) {
            throw new \Exception("Class $class does not exist.", 500);
        }

        $db = new $class($config);
        return $db;
    }

    /**
     * Add a table to the database.
     *
     * @param string $tablename The name of the table.
     * @param array $tabledef The table definition.
     * @param array $options An array of additional options when adding the table.
     */
    abstract protected function createTable($tablename, array $tabledef, array $options = []);

    /**
     * Alter a table in the database.
     *
     * When altering a table you pass an array with three optional keys: add, drop, and alter.
     * Each value is consists of a table definition in a format that would be passed to {@link Db::setTableDef()}.
     *
     * @param string $tablename The name of the table.
     * @param array $alterdef The alter definition.
     * @param array $options An array of additional options when adding the table.
     */
    abstract protected function alterTable($tablename, array $alterdef, array $options = []);

    /**
     * Drop a table.
     *
     * @param string $tablename The name of the table to drop.
     * @param array $options An array of additional options when adding the table.
     */
    abstract public function dropTable($tablename, array $options = []);

    /**
     * Get a table definition.
     *
     * @param string $tableName The name of the table.
     * @return array|null Returns the table definition or null if the table does not exist.
     */
    public function getTableDef($tableName) {
        // Check to see if the table isn't in the cache first.
        if ($this->allTablesFetched & Db::FETCH_TABLENAMES &&
            !isset($this->tables[$tableName])) {
            return null;
        }

        if (
            isset($this->tables[$tableName]) &&
            is_array($this->tables[$tableName]) &&
            isset($this->tables[$tableName]['columns'], $this->tables[$tableName]['indexes'])
        ) {
            return $this->tables[$tableName];
        }
        return [];
    }

    /**
     * Get all of the tables in the database.
     *
     * @param bool $withDefs Whether or not to return the full table definitions or just the table names.
     * @return array Returns an array of either the table definitions or the table names.
     */
    public function getAllTables($withDefs = false) {
        if ($withDefs && ($this->allTablesFetched & Db::FETCH_COLUMNS)) {
            return $this->tables;
        } elseif (!$withDefs && ($this->allTablesFetched & Db::FETCH_TABLENAMES)) {
            return array_keys($this->tables);
        } else {
            return null;
        }
    }

    /**
     * Set a table definition to the database.
     *
     * @param string $tableName The name of the table.
     * @param array $tableDef The table definition.
     * @param array $options An array of additional options when adding the table.
     */
    public function setTableDef($tableName, array $tableDef, array $options = []) {
        $drop = val('drop', $options);
        $curTable = $this->getTableDef($tableName);

        $this->fixPrimaryKey($tableDef);

        if (!$curTable) {
            $this->createTable($tableName, $tableDef, $options);
            $this->tables[$tableName] = $tableDef;
            return;
        }
        // This is the alter statement.
        $alterDef = [];

        // Figure out the columns that have changed.
        $curColumns = (array)val('columns', $curTable, []);
        $newColumns = (array)val('columns', $tableDef, []);

        $alterDef['add']['columns'] = array_diff_key($newColumns, $curColumns);
        $alterDef['alter']['columns'] = array_uintersect_assoc($newColumns, $curColumns, function ($new, $curr) {
            // Return 0 if the values are different, not the same.
            if (val('type', $curr) !== val('type', $new) ||
                val('required', $curr) !== val('required', $new) ||
                val('default', $curr) !== val('required', $new)) {
                return 0;
            }
            return 1;
        });
        if ($drop) {
            $alterDef['drop']['columns'] = array_diff_key($curColumns, $newColumns);
        }

        // Figure out the indexes that have changed.
        $curIndexes = (array)val('indexes', $curTable, []);
        $newIndexes = (array)val('indexes', $tableDef, []);

        $alterDef['add']['indexes'] = array_udiff($newIndexes, $curIndexes, [$this, 'indexCompare']);
        if ($drop) {
            $alterDef['drop']['indexes'] = array_udiff($curIndexes, $newIndexes, [$this, 'indexCompare']);
        }

        $alterDef['def'] = $tableDef;

        // Update the cached schema. The driver-specific call can also update it.
        $this->tables[$tableName] = $tableDef;

        // Alter the table.
        $this->alterTable($tableName, $alterDef, $options);
    }

    /**
     * Move the primary key index into the correct place for database drivers.
     *
     * @param array &$tableDef The table definition.
     */
    protected function fixPrimaryKey(array &$tableDef) {
        // Massage the primary key index.
        foreach (val('indexes', $tableDef, []) as $i => $indexDef) {
            if (val('type', $indexDef) === Db::INDEX_PK) {
                if (count($indexDef['columns']) === 1) {
                    $tableDef['columns'][$indexDef['columns'][0]]['primary'] = true;
                    unset($tableDef['indexes'][$i]);
                } else {
                    foreach ($indexDef['columns'] as $column) {
                        $tableDef['columns']['primary'] = false;
                    }
                }
            }
        }
    }

    /**
     * Get the database prefix.
     *
     * @return string Returns the current db prefix.
     */
    public function getPx() {
        return $this->px;
    }

    /**
     * Set the database prefix.
     *
     * @param string $px The new database prefix.
     */
    public function setPx($px) {
        $this->px = $px;
    }

    /**
     * Compare two index definitions to see if they have the same columns and same type.
     *
     * @param array $a The first index.
     * @param array $b The second index.
     * @return int Returns an integer less than, equal to, or greater than zero if {@link $a} is
     * considered to be respectively less than, equal to, or greater than {@link $b}.
     */
    protected function indexCompare(array $a, array $b) {
        if ($a['columns'] > $b['columns']) {
            return 1;
        } elseif ($a['columns'] < $b['columns']) {
            return -1;
        }

        return strcmp(val('type', $a, ''), val('type', $b, ''));
    }

    /**
     * Get data from the database.
     *
     * @param string $tablename The name of the table to get the data from.
     * @param array $where An array of where conditions.
     * @param array $options An array of additional options.
     * @return mixed Returns the result set.
     */
    abstract public function get($tablename, array $where, array $options = []);

    /**
     * Get a single row from the database.
     *
     * This is a conveinience method that calls {@link Db::get()} and shifts off the first row.
     *
     * @param string $tablename The name of the table to get the data from.
     * @param array $where An array of where conditions.
     * @param array $options An array of additional options.
     * @return array|false Returns the row or false if there is no row.
     */
    public function getOne($tablename, array $where, array $options = []) {
        $options['limit'] = 1;
        $rows = $this->get($tablename, $where, $options);
        return array_shift($rows);
    }

    /**
     * Insert a row into a table.
     *
     * @param string $tablename The name of the table to insert into.
     * @param array $row The row of data to insert.
     * @param array $options An array of options for the insert.
     *
     * Db::OPTION_IGNORE
     * : Whether or not to ignore inserts that lead to a duplicate key. *default false*
     * Db::OPTION_REPLACE
     * : Whether or not to replace duplicate keys. *default false*
     * Db::OPTION_UPSERT
     * : Whether or not to update the existing data when duplicate keys exist.
     *
     * @return mixed Should return the id of the inserted record.
     * @see Db::load()
     */
    abstract public function insert($tablename, array $row, array $options = []);

    /**
     * Load many rows into a table.
     *
     * @param string $tablename The name of the table to insert into.
     * @param \Traversable|array $rows A dataset to insert.
     * Note that all rows must contain the same columns.
     * The first row will be looked at for the structure of the insert and the rest of the rows will use this structure.
     * @param array $options An array of options for the inserts. See {@link Db::insert()} for details.
     * @return mixed
     * @see Db::insert()
     */
    abstract public function load($tablename, $rows, array $options = []);


    /**
     * Update a row or rows in a table.
     *
     * @param string $tablename The name of the table to update.
     * @param array $set The values to set.
     * @param array $where The where filter for the update.
     * @param array $options An array of options for the update.
     * @return mixed
     */
    abstract public function update($tablename, array $set, array $where, array $options = []);

    /**
     * Delete rows from a table.
     *
     * @param string $tablename The name of the table to delete from.
     * @param array $where The where filter of the delete.
     * @param array $options An array of options.
     *
     * Db:OPTION_TRUNCATE
     * : Truncate the table instead of deleting rows. In this case {@link $where} must be blank.
     * @return mixed
     */
    abstract public function delete($tablename, array $where, array $options = []);

    /**
     * Reset the internal table definition cache.
     *
     * @return Db Returns $this for fluent calls.
     */
    public function reset() {
        $this->tables = [];
        $this->allTablesFetched = 0;
        $this->rowCount = 0;
        return $this;
    }

    /**
     * Build a standardized index name from an index definition.
     *
     * @param string $tablename The name of the table the index is in.
     * @param array $indexDef The index definition.
     * @return string Returns the index name.
     */
    protected function buildIndexName($tablename, array $indexDef) {
        $type = val('type', $indexDef, Db::INDEX_IX);

        if ($type === Db::INDEX_PK) {
            return 'primary';
        }
        $px = val($type, [Db::INDEX_IX => 'ix_', Db::INDEX_UNIQUE => 'ux_'], 'ix_');
        $sx = val('suffix', $indexDef);
        $result = $px.$tablename.'_'.($sx ?: implode('', $indexDef['columns']));
        return $result;
    }
}
