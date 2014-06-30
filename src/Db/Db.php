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
    abstract protected function addTable($tablename, array $tabledef, array $options = []);

    /**
     * Alter a table in the database.
     *
     * When altering a table you pass an array with three optional keys: add, drop, and alter.
     * Each value is consists of a table definition in a format that would be passed to {@link Db::setTable()}.
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
    public function getTable($tableName) {
        // Check to see if the table isn't in the cache first.
        if ($this->allTablesFetched & Db::FETCH_TABLENAMES &&
            !isset($this->table[$tableName])) {
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
    public function setTable($tableName, array $tableDef, array $options = []) {
        $drop = val('drop', $options);
        $curTable = $this->getTable($tableName);

        if (!$curTable) {
            $this->addTable($tableName, $tableDef, $options);
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
                val('required', $curr) !== val('required', $new)) {
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

        // Update the cached schema. The driver-specific call can also update it.
        $this->tables[$tableName] = $tableDef;

        // Alter the table.
        $this->alterTable($tableName, $alterDef, $options);
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
        if ($a > $b) {
            return 1;
        } elseif ($a < $b) {
            return -1;
        }

        return strcmp(val('type', $a, ''), val('type', $b, ''));
    }

    abstract public function get($tablename, array $where, array $options);

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
