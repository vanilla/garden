<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

use PDO;

class MySqlDb extends Db {
    /// Properties ///

    /**
     * @var \PDO
     */
    protected $pdo;

    protected $config;

    /// Methods ///

    /**
     * Initialize an instance of the {@link MySqlDb} class.
     *
     * @param array $config The database config.
     */
    public function __construct(array $config = []) {
        $this->config = $config;
    }

    /**
     * Gets the {@link PDO} object for this connection.
     *
     * @return \PDO
     */
    public function pdo() {
        $dsnParts = array_translate($this->config, ['host', 'dbname', 'port']);
        $dsn = 'mysql:'.implode_assoc(';', '=', $dsnParts);

        if (!isset($this->pdo)) {
            $this->pdo = new PDO($dsn, val('username', $this->config, ''), val('password', $this->config, ''));
            $this->pdo->query('set names utf8'); // send this statement outside our query function.
        }
        return $this->pdo;
    }

    /**
     * Get the current database name.
     *
     * @return mixed
     */
    public function getDbName() {
        return val('dbname', $this->config);
    }

    /**
     * {@inheritdoc}
     */
    protected function addTable($tablename, array $tabledef, array $options = []) {
        // The table doesn't exist so this is a create table.
        $parts = array();
        foreach ($tabledef['columns'] as $name => $def) {
            $parts[] = $this->columnDefString($name, $def);
        }

        foreach (val('indexes', $tabledef, []) as $index) {
            $indexDef = $this->indexDefString($tablename, $index);
            if ($indexDef) {
                $parts[] = $indexDef;
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
     * {@inheritdoc}
     */
    protected function alterTable($tablename, array $alterdef, array $options = []) {
        // TODO: Implement alterTable() method.
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($tablename, array $options = []) {
        $sql = 'drop table '.$this->backtick($this->px.$tablename);
        $this->query($sql);
    }

    /**
     * {@inheritdoc}
     */
    public function getTable($tablename) {
        $table = parent::getTable($tablename);
        if ($table || $table === null) {
            return $table;
        }

        $table = val($tablename, $this->tables, []);
        if (!isset($table['columns'])) {
            $table['columns'] = $this->getColumns($tablename);
        }
        if (!isset($table['indexes'])) {
            $table['indexes'] = $this->getIndexes($tablename);
        }
        $this->tables[$tablename] = $table;
        return $table;
    }

    /**
     * {@inheritdoc}
     */
    public function getAllTables($withDefs = false) {
        $tables = parent::getAllTables($withDefs);
        if ($tables !== null) {
            return $tables;
        }

        // Grab the tablenames first.
        if ($this->allTablesFetched & Db::FETCH_TABLENAMES) {
            $tablenames = array_keys($this->tables);
        } else {
            $tablenames = $this->getTablenames();
            $this->tables = array_fill_keys($tablenames, null);
            $this->allTablesFetched = Db::FETCH_TABLENAMES;
        }

        if (!$withDefs) {
            return $tablenames;
        }

        $this->getColumns();
        $this->allTablesFetched |= Db::FETCH_COLUMNS;

        $this->getIndexes();
        $this->allTablesFetched |= Db::FETCH_INDEXES;

        return $this->tables;
    }

    /**
     * Get the all of tablenames in the database.
     *
     * @return array Returns an array of table names with prefixes stripped.
     */
    protected function getTablenames() {
        // Get the table names.
        $tables = $this->get(
            'information_schema.TABLES',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => [Db::OP_LIKE => $this->px.'%']
            ],
            [
                'columns' => ['TABLE_NAME'],
                'escapeTable' => false
            ]
        );

        // Strip the table prefixes.
        $tables = array_map(function ($name) {
            return ltrim_substr($name, $this->px);
        }, array_column($tables, 'TABLE_NAME'));

        return $tables;
    }

    /**
     * Get the columns for tables and put them in {MySqlDb::$tables}.
     *
     * @param string $tablename The table to get the columns for or blank for all columns.
     * @return array|null Returns an array of columns if {@link $tablename} is specified, or null otherwise.
     */
    protected function getColumns($tablename = '') {
        $columns = $this->get(
            'information_schema.COLUMNS',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => $tablename ? $this->px.$tablename : [Db::OP_LIKE => $this->px.'%']
            ],
            [
                'escapeTable' => false,
                'order' => ['TABLE_NAME', 'ORDINAL_POSITION']
            ]
        );

        $tables = [];
        foreach ($columns as $cdef) {
            $column = [
                'type' => $this->columnTypeString($cdef['COLUMN_TYPE']),
                'required' => !force_bool($cdef['IS_NULLABLE']),
            ];
            if ($cdef['EXTRA'] === 'auto_increment') {
                $column['autoincrement'] = true;
            }
            if ($cdef['COLUMN_KEY'] === 'PRI') {
                $column['primary'] = true;
            }

            $tablename = ltrim_substr($cdef['TABLE_NAME'], $this->px);
            $tables[$tablename]['columns'][$cdef['COLUMN_NAME']] = $column;
        }
        $this->tables = array_replace($this->tables, $tables);
        if ($tablename) {
            return val($tablename, $this->tables, null);
        }
        return null;
    }

    /**
     * Get the indexes from the database.
     *
     * @param string $tablename The name of the table to get the indexes for or an empty string to get all indexes.
     * @return array|null
     */
    protected function getIndexes($tablename = '') {
        $indexRows = $this->get(
            'information_schema.STATISTICS',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => $tablename ? $this->px.$tablename : [Db::OP_LIKE => $this->px.'%']
            ],
            [
                'escapeTable' => false,
                'order' => ['TABLE_NAME', 'INDEX_NAME', 'SEQ_IN_INDEX']
            ]
        );

        $indexes = [];
        foreach ($indexRows as $row) {
            $itablename = ltrim_substr($row['TABLE_NAME'], $this->px);
            $indexname = $row['INDEX_NAME'];

            if ($indexname === 'PRIMARY') {
                $type = Db::INDEX_PK;
            } else {
                $type = $row['NON_UNIQUE'] ? Db::INDEX_IX : Db::INDEX_UNIQUE;
            }

            $indexes[$itablename][$indexname]['name'] = $indexname;
            $indexes[$itablename][$indexname]['columns'][] = $row['COLUMN_NAME'];
            $indexes[$itablename][$indexname]['type'] = $type;
        }

        // Add the indexes to the tables.
        foreach ($indexes as $itablename => $tableIndexes) {
            $this->tables[$itablename]['indexes'] = array_values($tableIndexes);
        }
        if ($tablename) {
            return $this->tables[$tablename]['indexes'];
        }
        return null;
    }

    /**
     * Execute a query on the database.
     *
     * @param string $sql The sql query to execute.
     * @param string $type One of the Db::QUERY_* constants.
     *
     * Db::QUERY_READ
     * : The query reads from the database.
     *
     * Db::QUERY_WRITE
     * : The query writes to the database.
     *
     * Db::QUERY_DEFINE
     * : The query alters the structure of the datbase.
     *
     * @param array $options Additional options for the query.
     *
     * Db::GET_UNBUFFERED
     * : Don't internally buffer the data when selecting from the database.
     *
     * @return array|PDOStatement|bool The result of the query.
     *
     * - array: When selecting from the database.
     * - PDOStatement: When performing an unbuffered query.
     * - int: When performing an update or an insert this will return the number of rows affected.
     * - false: When the query was not successful.
     */
    protected function query($sql, $type = Db::QUERY_READ, $options = array()) {
//        $start_time = microtime(true);

//        $this->pdo()->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, !val(Db::GET_UNBUFFERED, $options, false));

//        if ($this->mode === Db::MODE_ECHO && $type != Db::QUERY_READ) {
//            echo rtrim($sql, ';').";\n\n";
//            return true;
//        } else {
//         $result = $this->mysqli->query($sql, $resultmode);
            $result = $this->pdo->query($sql);

            if (!$result) {
                list($code, $dbCode, $message) = $this->pdo->errorInfo();
//            die($message);
//                fwrite(STDERR, $sql);
                trigger_error($message, E_USER_ERROR);
//            trigger_error($this->mysqli->error."\n\n".$sql, E_USER_ERROR);
            }
//        }

        if ($type == Db::QUERY_READ) {
//            if (isset($options[Db::GET_COLUMN])) {
//                $result->setFetchMode(PDO::FETCH_COLUMN, $options[Db::GET_COLUMN]);
//            } else {
                $result->setFetchMode(PDO::FETCH_ASSOC);
//            }

//            if (!val(Db::GET_UNBUFFERED, $options)) {
                $result = $result->fetchAll();
                $this->rowCount = count($result);
//            }
        }

        if (is_object($result) && method_exists($result, 'rowCount')) {
            $this->rowCount = $result->rowCount();
        }

//        $this->time += microtime(true) - $start_time;

        return $result;
    }

    /**
     * Surround a field with backticks.
     *
     * @param string $field The field to backtick.
     * @return string Returns the field properly escaped and backticked.
     * @link http://www.php.net/manual/en/pdo.quote.php#112169
     */
    protected function backtick($field) {
        return '`'.str_replace('`', '``', $field).'`';
    }

    /**
     * Convert an array into a bracketed list suitable for MySQL clauses.
     *
     * @param array $row The row to expand.
     * @param string $quote The quotes to surroud the items with. There are two special cases.
     * ' (single quote)
     * : The row will be passed through {@link PDO::quote()}.
     * ` (backticks)
     * : The row will be passed through {@link MySqlDb::backtick()}.
     * @return string Returns the bracket list.
     */
    public function bracketList($row, $quote = "'") {
        switch ($quote) {
            case "'":
                $row = array_map([$this->pdo(), 'quote'], $row);
                $quote = '';
                break;
            case '`':
                $row = array_map([$this, 'backtick'], $row);
                $quote = '';
                break;
        }

        return "($quote".implode("$quote, $quote", $row)."$quote)";
    }

    protected function columnDefString($name, $def) {
        $result = $this->backtick($name).' '.$this->columnTypeString($def['type']);

        if (val('required', $def)) {
            $result .= ' not null';
        }

        if (val('autoincrement', $def)) {
            $result .= ' auto_increment';
            $def['primary'] = true;
        }

        if (val('primary', $def)) {
            $result .= ' primary key';
        }
        return $result;
    }

    protected function indexDefString($tablename, $def) {
        $indexName = $this->backtick($this->buildIndexName($tablename, $def));
        switch (val('type', $def, Db::INDEX_IX)) {
            case Db::INDEX_IX:
                return "index $indexName ".$this->bracketList($def['columns'], '`');
                break;
            case Db::INDEX_UNIQUE:
                return  "unique $indexName ".$this->bracketList($def['columns'], '`');
        }
        return null;
    }

    /**
     * Parse a column type string and return it in a way that is suitible for a create/alter table statement.
     *
     * @param string $typeString The string to parse.
     * @return string Returns a canonical typestring.
     */
    protected function columnTypeString($typeString) {
        $type = null;

        if (substr($type, 0, 4) === 'enum') {
            // This is an enum which will come in as an array.
            if (preg_match_all("`'([^']+)'`", $typeString, $matches)) {
                $type = $matches[1];
            }
        } else {
            if (preg_match('`([a-z]+)\s*(?:\((\d+(?:\s*,\s*\d+)*)\))?\s*(unsigned)?`', $typeString, $matches)) {
                //         var_dump($matches);
                $str = $matches[1];
                $length = @$matches[2];
                $unsigned = @$matches[3];

                if (substr($str, 0, 1) == 'u') {
                    $unsigned = true;
                    $str = substr($str, 1);
                }

                // Remove the length from types without real lengths.
                if (in_array($str, array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'float', 'double'))) {
                    $length = null;
                }

                $type = $str;
                if ($length) {
                    $length = str_replace(' ', '', $length);
                    $type .= "($length)";
                }
                if ($unsigned) {
                    $type .= ' unsigned';
                }
            }
        }

        if (!$type) {
            debug_print_backtrace();
            trigger_error("Couldn't parse type $typeString", E_USER_ERROR);
        }

        return $type;
    }

    public function get($tablename, array $where, array $options = []) {
        $sql = $this->buildSelect($tablename, $where, $options);
        $result = $this->query($sql, Db::QUERY_READ);
        return $result;
    }

    public function buildSelect($table, array $where, array $options = []) {
        $sql = '';

        // Build the select clause.
        if (isset($options['columns'])) {
            $columns = array();
            foreach ($options['columns'] as $value) {
                $columns[] = $this->backtick($value);
            }
            $sql .= 'select '.implode(', ', $columns);
        } else {
            $sql .= "select *";
        }

        // Build the from clause.
        if (val('escapeTable', $options, true)) {
            $sql .= "\nfrom ".$this->backtick($this->px.$table);
        } else {
            $sql .= "\nfrom $table";
        }

        // Build the where clause.
        $whereString = $this->buildWhere($where, Db::OP_AND);
        if ($whereString) {
            $sql .= "\nwhere ".$whereString;
        }

        // Build the order.
        if (isset($options['order'])) {
            $order = $options['order'];
            $orders = array();
            foreach ($order as $key => $value) {
                if (is_int($key)) {
                    // This is just a column.
                    $orders[] = $this->backtick($value);
                } else {
                    // This is a column with a direction.
                    switch ($value) {
                        case OP_ASC:
                        case OP_DESC:
                            $orders[] = $this->backtick($key)." $value";
                            break;
                        default:
                            trigger_error("Invalid sort direction '$value' for column '$key'.", E_USER_WARNING);
                    }
                }
            }

            $sql .= "\norder by ".implode(', ', $orders);
        }

        // Build the limit, offset.
        $limit = 10;
        if (isset($options['limit'])) {
            $limit = (int)$options['limit'];
            $sql .= "\nlimit $limit";
        }

        if (isset($options['offset'])) {
            $sql .= ' offset '.((int)$options['offset']);
        } elseif (isset($options['page'])) {
            $offset = $limit * ($options['page'] - 1);
            $sql .= ' offset '.$offset;
        }

        return $sql;
    }

    /**
     * Build a where clause from a where array.
     *
     * @param array $where There where string.
     * This is an array in the form `['column' => 'value']` with more advanced options for non-equality comparisons.
     * @param string $op The logical operator to join multiple field comparisons.
     * @param bool $quotevals Whether or not to quote the where values.
     * @return string The where string.
     */
    protected function buildWhere($where, $op = Db::OP_AND, $quotevals = true) {
        static $map = array(Db::OP_GT => '>', Db::OP_GTE => '>=', Db::OP_LT => '<', Db::OP_LTE => '<=', Db::OP_LIKE => 'like');

        if (!$where) {
            return '';
        }

        $result = '';
        foreach ($where as $column => $value) {
            if ($result) {
                $result .= " $op ";
            }

            if (is_array($value)) {
                foreach ($value as $vop => $rval) {
                    switch ($vop) {
                        case Db::OP_AND:
                        case Db::OP_OR:
                            $result .= '('.$this->buildWhere($rval, $vop, $quotevals).')';
                            break;
                        case Db::OP_EQ:
                            if ($value === null) {
                                $result .= "`$column` is null";
                            } elseif (is_array($rval)) {
                                $rval = array_map(array($this->pdo, 'quote'), $rval);
                                $result .= "`$column` in (".implode(',', $rval).')';
                            } else {
                                $result .= "`$column` = ".$this->pdo->quote($rval);
                            }
                            break;
                        case Db::OP_GT:
                        case Db::OP_GTE:
                        case Db::OP_LT:
                        case Db::OP_LTE:
                        case Db::OP_LIKE:
                            $result .= "`$column` {$map[$vop]} ".$this->quoteVal($rval, $quotevals);
                            break;
                        case Db::OP_IN:
                            // Quote the in values.
                            $rval = array_map(array($this->pdo, 'quote'), (array)$rval);
                            $result .= "`$column` in (".implode(', ', $rval).')';
                            break;
                        case Db::OP_NE:
                            if ($value === null) {
                                $result .= "`$column` is null";
                            } elseif (is_array($rval)) {
                                $rval = array_map(array($this->pdo, 'quote'), $rval);
                                $result .= "`$column` not in (".implode(',', $rval).')';
                            } else {
                                $result .= "`$column` = ".$this->quoteVal($rval, $quotevals);
                            }
                            break;
                    }
                }
            } else {
                // This is just an equality operator.
                if ($value === null) {
                    $result .= ' is null';
                } else {
                    $result .= "`$column` = ".$this->quoteVal($value, $quotevals);
                }
            }
        }
        return $result;
    }

    /**
     * Optionally quote a where value.
     *
     * @param mixed $value The value to quote.
     * @param bool $quote Whether or not to quote the value.
     * @return string Returns the value, optionally quoted.
     */
    public function quoteVal($value, $quote = true) {
        if ($quote) {
            return $this->pdo()->quote($value);
        } else {
            return $value;
        }
    }
}
