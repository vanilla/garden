<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

use PDO;

/**
 * A {@link Db} class for connecting to SQLite.
 */
class SqliteDb extends MySqlDb {
    /**
     * {@inheritdoc}
     */
    protected function buildInsert($tablename, array $row, $quotevals = true, $options = []) {
        if (val(Db::OPTION_UPSERT, $options)) {
            throw new \Exception("Upsert is not supported.");
        } elseif (val(Db::OPTION_IGNORE, $options)) {
            $sql = 'insert or ignore into ';
        } elseif (val(Db::OPTION_REPLACE, $options)) {
            $sql = 'insert or replace into ';
        } else {
            $sql = 'insert into ';
        }
        $sql .= $this->backtick($this->px.$tablename);

        // Add the list of values.
        $sql .=
            "\n".$this->bracketList(array_keys($row), '`').
            "\nvalues".$this->bracketList($row, $quotevals ? "'" : '');

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildUpdate($tablename, array $set, array $where, $quotevals = true, array $options = []) {
        $sql = 'update '.
            (val(Db::OPTION_IGNORE, $options) ? 'or ignore ' : '').
            $this->backtick($this->px.$tablename).
            "\nset\n  ";

        $parts = [];
        foreach ($set as $key => $value) {
            $parts[] = $this->backtick($key).' = '.$this->quoteVal($value, $quotevals);
        }
        $sql .= implode(",\n  ", $parts);

        if (!empty($where)) {
            $sql .= "\nwhere ".$this->buildWhere($where, Db::OP_AND, $quotevals);
        }

        return $sql;
    }

    /**
     * Construct a column definition string from an array defintion.
     *
     * @param string $name The name of the column.
     * @param array $def The column definition.
     * @return string Returns a string representing the column definition.
     */
    protected function columnDefString($name, array $def) {
        // Auto-increments MUST be of type integer.
        if (val('autoincrement', $def)) {
            $def['type'] = 'integer';
        }

        $result = $this->backtick($name).' '.$this->columnTypeString($def['type']);

        if (val('primary', $def)) {
            $result .= ' primary key';

            if (val('autoincrement', $def)) {
                $result .= ' autoincrement';
                $def['primary'] = true;
            }
        } elseif (isset($def['default'])) {
            $result .= ' default '.$this->quoteVal($def['default']);
        } elseif (val('required', $def)) {
            $result .= ' not null';
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function createTable($tablename, array $tabledef, array $options = []) {
        // The table doesn't exist so this is a create table.
        $parts = array();
        foreach ($tabledef['columns'] as $name => $def) {
            $parts[] = $this->columnDefString($name, $def);
        }

        // Add just primary keys and unique indexes here.
        foreach (val('indexes', $tabledef, []) as $index) {
            switch (val('type', $index, Db::INDEX_IX)) {
                case Db::INDEX_PK:
                    $parts[] = 'primary key '.$this->bracketList($index['columns'], '`');
                    break;
                case Db::INDEX_UNIQUE:
                    $parts[] = 'unique '.$this->bracketList($index['columns'], '`');
                    break;
            }
        }

        $fullTablename = $this->backtick($this->px.$tablename);
        $sql = "create table $fullTablename (\n  ".
            implode(",\n  ", $parts).
            "\n)";

        if (val('collate', $options)) {
            $sql .= "\n collate {$options['collate']}";
        }

        $this->query($sql, Db::QUERY_DEFINE);
    }

    /**
     * Force a value into the appropriate php type based on its Sqlite type.
     *
     * @param mixed $value The value to force.
     * @param string $type The sqlite type name.
     * @return mixed Returns $value cast to the appropriate type.
     */
    protected function forceType($value, $type) {
        $type = strtolower($type);

        if ($type === 'null') {
            return null;
        } elseif (in_array($type, ['int', 'integer', 'tinyint', 'smallint',
            'mediumint', 'bigint', 'unsigned big int', 'int2', 'int8', 'boolean'])) {
            return force_int($value);
        } elseif (in_array($type, ['reall', 'double', 'double precision', 'float',
            'numeric', 'decimal(10,5)'])) {
            return floatval($value);
        } else {
            return (string)$value;
        }
    }

    /**
     * Get the columns for tables and put them in {MySqlDb::$tables}.
     *
     * @param string $tablename The table to get the columns for or blank for all columns.
     * @return array|null Returns an array of columns if {@link $tablename} is specified, or null otherwise.
     */
    protected function getColumns($tablename = '') {
        if (!$tablename) {
            $tablenames = $this->getTablenames();
            foreach ($tablenames as $tablename) {
                $this->getColumns($tablename);
            }
        }

        $cdefs = $this->query('pragma table_info('.$this->quoteVal($this->px.$tablename).')');
        $columns = [];
        foreach ($cdefs as $cdef) {
            $column = [
                'type' => $this->columnTypeString($cdef['type']),
                'required' => force_bool($cdef['notnull']),
            ];
            if ($cdef['pk'] && strcasecmp($cdef['type'], 'integer')) {
                $column['autoincrement'] = true;
            }
            if ($cdef['pk'] === 'PRI') {
                $column['primary'] = true;
            }
            if ($cdef['dflt_value'] !== null) {
                $column['default'] = $cdef['dflt_value'];
            }
            $columns[$cdef['name']] = $column;
        }
        $this->tables[$tablename] = ['columns' => $columns];
        return val($tablename, $this->tables, null);
    }

    /**
     * Get the indexes from the database.
     *
     * @param string $tablename The name of the table to get the indexes for or an empty string to get all indexes.
     * @return array|null
     */
    protected function getIndexes($tablename = '') {
        if (!$tablename) {
            $tablenames = $this->getTablenames();
            foreach ($tablenames as $tablename) {
                $this->getIndexes($tablename);
            }
        }

        // Reset the index list for the table.
        $this->tables[$tablename]['indexes'] = [];

        $indexInfos = $this->query('pragma index_list('.$this->quoteVal($this->px.$tablename).')');
        foreach ($indexInfos as $row) {
            $indexName = $row['name'];
            if ($row['unique']) {
                $type = Db::INDEX_UNIQUE;
            } else {
                $type = Db::INDEX_IX;
            }

            // Query the columns in the index.
            $columns = $this->query('pragma index_info('.$this->quoteVal($indexName).')');

            $index = [
                'name' => $indexName,
                'columns' => array_column($columns, 'name'),
                'type' => $type
            ];
            $this->tables[$tablename]['indexes'] = $index;
        }

        return $this->tables[$tablename]['indexes'];
    }

    /**
     * Get the primary or secondary keys from the given rows.
     *
     * @param string $tablename The name of the table.
     * @param array $rows The rows to examine.
     * @param bool $quick Whether or not to quickly look for <tablename>ID for the primary key.
     * @return array|null Returns the primary keys and values from {@link $rows} or null if the primary key isn't found.
     */
    protected function getPK($tablename, array $rows, $quick = false) {
        if ($quick && isset($rows[$tablename.'ID'])) {
            return [$tablename.'ID' => $rows[$tablename.'ID']];
        }

        $tdef = $this->getTableDef($tablename);
        if (isset($tdef['indexes'])) {
            foreach ($tdef['indexes'] as $idef) {
                $cols = array_intersect_key($rows, $idef['columns']);
                if (count($cols) === count($idef['columns']) &&
                    val('type', $idef, Db::INDEX_IX) !== Db::INDEX_IX) {
                    return $cols;
                }
            }
        }

        return null;
    }

    /**
     * Get the all of tablenames in the database.
     *
     * @return array Returns an array of table names with prefixes stripped.
     */
    protected function getTablenames() {
        // Get the table names.
        $tables = $this->get(
            'sqlite_master',
            [
                'type' => 'table',
                'name' => [Db::OP_LIKE => $this->px.'%']
            ],
            [
                'columns' => ['name'],
                'escapeTable' => false
            ]
        );

        // Strip the table prefixes.
        $tables = array_map(function ($name) {
            return ltrim_substr($name, $this->px);
        }, array_column($tables, 'name'));

        return $tables;
    }



    /**
     * {@inheritdoc}
     */
    public function insert($tablename, array $rows, array $options = []) {
        // Sqlite doesn't support upsert so do upserts manually.
        if (val(Db::OPTION_UPSERT, $options)) {
            unset($options[Db::OPTION_UPSERT]);

            $keys = $this->getPK($tablename, $rows, true);
            if (!$keys) {
                throw new \Exception("Cannot upsert with no key.", 500);
            }
            // Try updating first.
            $updated = $this->update(
                $tablename,
                array_diff_key($rows, $keys),
                $keys,
                $options
            );
            if ($updated) {
                // Updated.
                if (count($keys) === 1) {
                    return array_pop($keys);
                } else {
                    return true;
                }
            }
        }

        $result = parent::insert($tablename, $rows, $options);
        return $result;
    }

    /**
     * Gets the {@link PDO} object for this connection.
     *
     * @return \PDO
     */
    public function pdo() {
        $dsn = 'sqlite:'.$this->config['path'];

        if (!isset($this->pdo)) {
            $this->pdo = new PDO($dsn, val('username', $this->config, null), val('password', $this->config, null));
        }
        return $this->pdo;
    }

    /**
     * Optionally quote a where value.
     *
     * @param mixed $value The value to quote.
     * @param bool $quote Whether or not to quote the value.
     * @return string Returns the value, optionally quoted.
     */
    public function quoteVal($value, $quote = true) {
        if ($value instanceof Literal) {
            /* @var Literal $value */
            return $value->getValue('mysql');
        } elseif (in_array(gettype($value), ['integer', 'double'])) {
            return (string)$value;
        } elseif ($value === true) {
            return '1';
        } elseif ($value === false) {
            return '0';
        } elseif ($quote) {
            return $this->pdo()->quote($value);
        } else {
            return $value;
        }
    }
}
