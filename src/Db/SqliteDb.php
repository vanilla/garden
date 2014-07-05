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
    protected function alterTable($tablename, array $alterdef, array $options = []) {
        $this->alterTableMigrate($tablename, $alterdef, $options);
    }

    /**
     * Alter a table by creating a new table and copying the old table's data to it.
     *
     * @param string $tablename The table to alter.
     * @param array $alterDef The new definition.
     * @param array $options An array of options for the migration.
     */
    protected function alterTableMigrate($tablename, array $alterDef, array $options = []) {
        $currentDef = $this->getTableDef($tablename);

        // Merge the table definitions if we aren't dropping stuff.
        if (!val(Db::OPTION_DROP, $options)) {
            $tableDef = $this->mergeTableDefs($currentDef, $alterDef);
        } else {
            $tableDef = $alterDef['def'];
        }

        // Drop all of the indexes on the current table.
        foreach (val('indexes', $currentDef, []) as $indexDef) {
            if (val('type', $indexDef, Db::INDEX_IX) === Db::INDEX_IX) {
                $this->dropIndex($indexDef['name']);
            }
        }

        $tmpTablename = $tablename.'_'.time();

        // Rename the current table.
        $this->renameTable($tablename, $tmpTablename);

        // Create the new table.
        $this->createTable($tablename, $tableDef, $options);

        // Figure out the columns that we can insert.
        $columns = array_keys(array_intersect_key($tableDef['columns'], $currentDef['columns']));

        // Build the insert/select statement.
        $sql = 'insert into '.$this->backtick($this->px.$tablename)."\n".
            $this->bracketList($columns, '`')."\n".
            $this->buildSelect($tmpTablename, [], ['columns' => $columns]);

        $this->query($sql, Db::QUERY_WRITE);

        // Drop the temp table.
        $this->dropTable($tmpTablename);
    }

    /**
     * Rename a table.
     *
     * @param string $oldname The old name of the table.
     * @param string $newname The new name of the table.
     */
    protected function renameTable($oldname, $newname) {
        $renameSql = 'alter table '.
            $this->backtick($this->px.$oldname).
            ' rename to '.
            $this->backtick($this->px.$newname);
        $this->query($renameSql, Db::QUERY_WRITE);
    }

    /**
     * Merge a table def with its alter def so that no columns/indexes are lost in an alter.
     *
     * @param array $tableDef The table def.
     * @param array $alterDef The alter def.
     * @return array The new table def.
     */
    protected function mergeTableDefs(array $tableDef, array $alterDef) {
        $result = $tableDef;

        $result['columns'] = array_merge($result['columns'], $alterDef['def']['columns']);
        $result['indexes'] = array_merge($result['indexes'], $alterDef['add']['indexes']);

        return $result;
    }

    /**
     * Drop an index.
     *
     * @param string $indexName The name of the index to drop.
     */
    protected function dropIndex($indexName) {
        $sql = 'drop index if exists '.
            $this->backtick($indexName);
        $this->query($sql, Db::QUERY_DEFINE);
    }

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
    protected function buildLike($column, $value, $quotevals) {
        return "$column like ".$this->quoteVal($value, $quotevals)." escape '\\'";
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

        if (val('primary', $def) && val('autoincrement', $def)) {
//            if (val('autoincrement', $def)) {
                $result .= ' primary key autoincrement';
                $def['primary'] = true;
//            }
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
        $parts = array();

        $autoinc = false;
        foreach ($tabledef['columns'] as $name => $def) {
            $parts[] = $this->columnDefString($name, $def);
            $autoinc |= val('autoincrement', $def, false);
        }

        // Add the prinary key.
        if (isset($tabledef['indexes']['primary']) && !$autoinc) {
            $pkIndex = $tabledef['indexes']['primary'];
            $parts[] = 'primary key '.$this->bracketList($pkIndex['columns'], '`');
        }

        $fullTablename = $this->backtick($this->px.$tablename);
        $sql = "create table $fullTablename (\n  ".
            implode(",\n  ", $parts).
            "\n)";

        $this->query($sql, Db::QUERY_DEFINE);

        // Add the rest of the indexes.
        foreach (val('indexes', $tabledef, []) as $index) {
            if (val('type', $index, Db::INDEX_IX) !== Db::INDEX_PK) {
                $this->createIndex($tablename, $index, $options);
            }
        }
    }

    /**
     * Create an index.
     *
     * @param string $tablename The name of the table to create the index on.
     * @param array $indexDef The index definition.
     * @param array $options Additional options for the index creation.
     */
    public function createIndex($tablename, array $indexDef, $options = []) {
        $sql = 'create '.
            (val('type', $indexDef) === Db::INDEX_UNIQUE ? 'unique ' : '').
            'index '.
            (val(Db::OPTION_IGNORE, $options) ? 'if not exists ' : '').
            $this->buildIndexName($tablename, $indexDef).
            ' on '.
            $this->backtick($this->px.$tablename).
            $this->bracketList($indexDef['columns'], '`');

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
        } elseif (in_array($type, ['real', 'double', 'double precision', 'float',
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
        if (empty($cdefs)) {
            return null;
        }

        $columns = [];
        $pk = [];
        foreach ($cdefs as $cdef) {
            $column = [
                'type' => $this->columnTypeString($cdef['type']),
                'required' => force_bool($cdef['notnull']),
            ];
            if ($cdef['pk']) {
                $pk[] = $cdef['name'];
                if (strcasecmp($cdef['type'], 'integer') === 0) {
                    $column['autoincrement'] = true;
                } else {
                    $column['primary'] = true;
                }
            }
            if ($cdef['dflt_value'] !== null) {
                $column['default'] = $cdef['dflt_value'];
            }
            $columns[$cdef['name']] = $column;
        }
        $tdef = ['columns' => $columns];
        if (!empty($pk)) {
            $tdef['indexes'][Db::INDEX_PK] = [
                'columns' => $pk,
                'type' => Db::INDEX_PK
            ];
        }
        $this->tables[$tablename] = $tdef;
        return $columns;
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

        $pk = valr(['indexes', Db::INDEX_PK], $this->tables[$tablename]);

        // Reset the index list for the table.
        $this->tables[$tablename]['indexes'] = [];

        if ($pk) {
            $this->tables[$tablename]['indexes'][Db::INDEX_PK] = $pk;
        }

        $indexInfos = $this->query('pragma index_list('.$this->quoteVal($this->px.$tablename).')');
        foreach ($indexInfos as $row) {
            $indexName = $row['name'];
            if ($row['unique']) {
                $type = Db::INDEX_UNIQUE;
            } else {
                $type = Db::INDEX_IX;
            }

            // Query the columns in the index.
            $columns = (array)$this->query('pragma index_info('.$this->quoteVal($indexName).')');

            $index = [
                'name' => $indexName,
                'columns' => array_column($columns, 'name'),
                'type' => $type
            ];
            $this->tables[$tablename]['indexes'][] = $index;
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
        $tables = (array)$this->get(
            'sqlite_master',
            [
                'type' => 'table',
                'name' => [Db::OP_LIKE => addcslashes($this->px, '_%').'%']
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
