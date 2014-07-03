<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

/**
 * A helper class for creating database tables.
 */
class DbDef implements \JsonSerializable {
    /// Properties ///

    /**
     * @var Db The database connection to send definitions to.
     */
    protected $db;

    /**
     * @var array The columns that need to be set in the table.
     */
    protected $columns;

    /**
     *
     * @var string The name of the currently working table.
     */
    protected $table;

    /**
     * @var array An array of indexes.
     */
    protected $indexes;

    /// Methods ///

    /**
     * Initialize an instance of the {@link DbDef} class.
     *
     * @param Db $db The database to execute against.
     */
    public function __construct($db) {
        $this->db = $db;
        $this->reset();
    }

    /**
     * Reset the internal state of this object so that it can be re-used.
     *
     * @return DbDef Returns $this for fluent calls.
     */
    public function reset() {
        $this->table = null;
        $this->columns = [];
        $this->indexes = [];

        return $this;
    }

    /**
     * Define a column.
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
        $this->columns[$name] = $this->columnDef($type, $nullDefault);

        $index = (array)$index;
        foreach ($index as $typeStr) {
            if (strpos($typeStr, '.') === false) {
                $indexType = $typeStr;
                $suffix = '';
            } else {
                list($indexType, $suffix) = explode('.', $typeStr);
            }
            $this->index($name, $indexType, $suffix);
        }

        return $this;
    }

    /**
     * Get an array column def from a structured function call.
     *
     * @param string $type The database type of the column.
     * @param mixed $nullDefault Whether or not to allow null or the default value.
     *
     * null|true
     * : The column is not required.
     * false
     * : The column is required.
     * Anything else
     * : The column is required and this is its default.
     *
     * @return array Returns the column def as an array.
     */
    protected function columnDef($type, $nullDefault = false) {
        $column = ['type' => $type];

        if ($nullDefault === null || $nullDefault == true) {
            $column['required'] = false;
        }
        if ($nullDefault === false) {
            $column['required'] = true;
        } else {
            $column['required'] = true;
            $column['default'] = $nullDefault;
        }

        return $column;
    }

    /**
     * Define the primary key in the database.
     *
     * @param string $name The name of the column.
     * @param string $type The datatype for the column.
     * @return DbDef
     */
    public function primaryKey($name, $type = 'int') {
        $column = $this->columnDef($type, false);
        $column['autoincrement'] = true;

        $this->columns[$name] = $column;

        // Add the pk index.
        $this->index($name, Db::INDEX_PK);

        return $this;
    }

    /**
     * Execute the table def against the database.
     *
     * @param bool $reset Whether or not to reset the db def upon completion.
     * @return DbDef $this Returns $this for fluent calls.
     */
    public function exec($reset = true) {
        $this->db->setTableDef(
            $this->table,
            $this->jsonSerialize()
        );

        if ($reset) {
            $this->reset();
        }

        return $this;
    }

    /**
     * Set the name of the table.
     *
     * @param string|null $name The name of the table.
     * @return DbDef|string Returns $this for fluent calls.
     */
    public function table($name = null) {
        if ($name !== null) {
            $this->table = $name;
            return $this;
        }
        return $this->table;
    }

    /**
     * Add or update an index.
     *
     * @param string|array $columns An array of columns or a single column name.
     * @param string $type One of the `Db::INDEX_*` constants.
     * @param string $suffix An index suffix to group columns together in an index.
     * @return DbDef Returns $this for fluent calls.
     */
    public function index($columns, $type, $suffix = '') {
        $type = strtolower($type);
        $columns = (array)$columns;
        $suffix = strtolower($suffix);

        // Look for a current index row.
        $currentIndex = null;
        foreach ($this->indexes as $i => $index) {
            if ($type !== $index['type']) {
                continue;
            }

            $indexSuffix = val('suffix', $index, '');

            if ($type === Db::INDEX_PK ||
                ($type === Db::INDEX_UNIQUE && $suffix == $indexSuffix) ||
                ($type === Db::INDEX_IX && $suffix && $suffix == $indexSuffix) ||
                ($type === Db::INDEX_IX && !$suffix && array_diff($index['columns'], $columns) == [])
            ) {
                $currentIndex =& $this->indexes[$i];
                break;
            }
        }

        if ($currentIndex) {
            $currentIndex['columns'] = array_unique(array_merge($currentIndex['columns'], $columns));
        } else {
            $this->indexes[] = [
                'type' => $type,
                'columns' => $columns,
                'suffix' => $suffix
            ];
        }

        return $this;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by {@link json_encode()},
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize() {
        return [
            'name' => $this->table,
            'columns' => $this->columns,
            'indexes' => $this->indexes
        ];
    }

    /**
     * Get the db connection to send definitions to.
     *
     * @return Db Returns the db connection.
     * @see DbDef::setDb()
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * Set the db connection to send definitions to.
     *
     * @param Db $db The new database connection.
     * @see DbDef::getDef()
     */
    public function setDb($db) {
        $this->db = $db;
    }
}
