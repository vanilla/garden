<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

use PDO;

/**
 * A {@link Db} class for connecting to MySQL.
 */
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
     * {@inheritdoc}
     */
    public function dropTable($tablename, array $options = []) {
        $sql = 'drop table '.
            (val(Db::OPTION_IGNORE, $options) ? 'if exists ' : '').
            $this->backtick($this->px.$tablename);
        $result = $this->query($sql, Db::QUERY_DEFINE);
        unset($this->tables[strtolower($tablename)]);

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
     * Db::OPTION_MODE
     * : Override {@link Db::$mode}.
     *
     * @return array|string|PDOStatement|int Returns the result of the query.
     *
     * array
     * : Returns an array when reading from the database and the mode is {@link Db::MODE_EXEC}.
     * string
     * : Returns the sql query when the mode is {@link Db::MODE_SQL}.
     * PDOStatement
     * : Returns a {@link \PDOStatement} when the mode is {@link Db::MODE_PDO}.
     * int
     * : Returns the number of rows affected when performing an update or an insert.
     */
    public function query($sql, $type = Db::QUERY_READ, $options = []) {
        $mode = val(Db::OPTION_MODE, $options, $this->mode);

        if ($mode & Db::MODE_ECHO) {
            echo trim($sql, "\n;").";\n\n";
        }
        if ($mode & Db::MODE_SQL) {
            return $sql;
        }

        $result = null;
        if ($mode & Db::MODE_EXEC) {
            $result = $this->pdo()->query($sql);

            if ($type == Db::QUERY_READ) {
                $result->setFetchMode(PDO::FETCH_ASSOC);
                $result = $result->fetchAll();
                $this->rowCount = count($result);
            } elseif (is_object($result) && method_exists($result, 'rowCount')) {
                $this->rowCount = $result->rowCount();
                $result = $this->rowCount;
            }
        } elseif ($mode & Db::MODE_PDO) {
            /* @var \PDOStatement $result */
            $result = $this->pdo()->prepare($sql);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getTableDef($tablename) {
        $table = parent::getTableDef($tablename);
        if ($table || $table === null) {
            return $table;
        }

        $ltablename = strtolower($tablename);
        $table = val($ltablename, $this->tables, []);
        if (!isset($table['columns'])) {
            $columns = $this->getColumns($tablename);
            if ($columns === null) {
                // A table with no columns does not exist.
                $this->tables[$ltablename] = ['name' => $tablename];
                return null;
            }

            $table['columns'] = $columns;
        }
        if (!isset($table['indexes'])) {
            $table['indexes'] = $this->getIndexes($tablename);
        }
        $table['name'] = $tablename;
        $this->tables[$ltablename] = $table;
        return $table;
    }

    /**
     * Get the columns for tables and put them in {MySqlDb::$tables}.
     *
     * @param string $tablename The table to get the columns for or blank for all columns.
     * @return array|null Returns an array of columns if {@link $tablename} is specified, or null otherwise.
     */
    protected function getColumns($tablename = '') {
        $ltablename = strtolower($tablename);
        /* @var \PDOStatement $stmt */
        $stmt = $this->get(
            'information_schema.COLUMNS',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => $tablename ? $this->px.$tablename : [Db::OP_LIKE => addcslashes($this->px, '_%').'%']
            ],
            [
                'columns' => [
                    'TABLE_NAME',
                    'COLUMN_TYPE',
                    'IS_NULLABLE',
                    'EXTRA',
                    'COLUMN_KEY',
                    'COLUMN_DEFAULT',
                    'COLUMN_NAME'
                ],
                Db::OPTION_MODE => Db::MODE_PDO,
                'escapeTable' => false,
                'order' => ['TABLE_NAME', 'ORDINAL_POSITION']
            ]
        );

        $stmt->execute();
        $tablecolumns = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

        foreach ($tablecolumns as $ctablename => $cdefs) {
            $ctablename = strtolower(ltrim_substr($ctablename, $this->px));
            $columns = [];

            foreach ($cdefs as $cdef) {
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

                if ($cdef['COLUMN_DEFAULT'] !== null) {
                    $column['default'] = $this->forceType($cdef['COLUMN_DEFAULT'], $column['type']);
                }

                $columns[$cdef['COLUMN_NAME']] = $column;
            }
            $this->tables[$ctablename]['columns'] = $columns;
        }
        if ($ltablename && isset($this->tables[$ltablename]['columns'])) {
            return $this->tables[$ltablename]['columns'];
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function get($tablename, array $where, array $options = []) {
        $sql = $this->buildSelect($tablename, $where, $options);
        $result = $this->query($sql, Db::QUERY_READ, $options);
        return $result;
    }

    /**
     * Build a sql select statement.
     *
     * @param string $table The name of the main table.
     * @param array $where The where filter.
     * @param array $options An array of additional query options.
     * @return string Returns the select statement as a string.
     * @see Db::get()
     */
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
            $order = array_quick($options['order'], Db::ORDER_ASC);
            $orders = array();
            foreach ($order as $key => $value) {
                switch ($value) {
                    case Db::ORDER_ASC:
                    case Db::ORDER_DESC:
                        $orders[] = $this->backtick($key)." $value";
                        break;
                    default:
                        trigger_error("Invalid sort direction '$value' for column '$key'.", E_USER_WARNING);
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

        $result = '';
        foreach ($where as $column => $value) {
            $btcolumn = $this->backtick($column);

            if (is_array($value)) {
                if (isset($value[0])) {
                    // This is a short in syntax.
                    $value = [Db::OP_IN => $value];
                }

                foreach ($value as $vop => $rval) {
                    if ($result) {
                        $result .= "\n  $op ";
                    }

                    switch ($vop) {
                        case Db::OP_AND:
                        case Db::OP_OR:
                            $innerWhere = [$column => $rval];
                            $result .= "(\n  ".
                                $this->buildWhere($innerWhere, $vop, $quotevals).
                                "\n  )";
                            break;
                        case Db::OP_EQ:
                            if ($rval === null) {
                                $result .= "$btcolumn is null";
                            } elseif (is_array($rval)) {
                                $result .= "$btcolumn in ".$this->bracketList($rval);
                            } else {
                                $result .= "$btcolumn = ".$this->quoteVal($rval, $quotevals);
                            }
                            break;
                        case Db::OP_GT:
                        case Db::OP_GTE:
                        case Db::OP_LT:
                        case Db::OP_LTE:
                            $result .= "$btcolumn {$map[$vop]} ".$this->quoteVal($rval, $quotevals);
                            break;
                        case Db::OP_LIKE:
                            $result .= $this->buildLike($btcolumn, $rval, $quotevals);
                            break;
                        case Db::OP_IN:
                            // Quote the in values.
                            $rval = array_map(array($this->pdo, 'quote'), (array)$rval);
                            $result .= "$btcolumn in (".implode(', ', $rval).')';
                            break;
                        case Db::OP_NE:
                            if ($rval === null) {
                                $result .= "$btcolumn is not null";
                            } elseif (is_array($rval)) {
                                $result .= "$btcolumn not in ".$this->bracketList($rval);
                            } else {
                                $result .= "$btcolumn <> ".$this->quoteVal($rval, $quotevals);
                            }
                            break;
                    }
                }
            } else {
                if ($result) {
                    $result .= "\n  $op ";
                }

                // This is just an equality operator.
                if ($value === null) {
                    $result .= "$btcolumn is null";
                } else {
                    $result .= "$btcolumn = ".$this->quoteVal($value, $quotevals);
                }
            }
        }
        return $result;
    }

    /**
     * Build a like expression.
     *
     * @param string $column The column name.
     * @param mixed $value The right-hand value.
     * @param bool $quotevals Whether or not to quote the values.
     * @return string Returns the like expression.
     */
    protected function buildLike($column, $value, $quotevals) {
        return "$column like ".$this->quoteVal($value, $quotevals);
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

    /**
     * Gets the {@link PDO} object for this connection.
     *
     * @return \PDO
     */
    public function pdo() {
        $dsnParts = array_translate($this->config, ['host', 'dbname', 'port']);
        $dsn = 'mysql:'.implode_assoc(';', '=', $dsnParts);

        if (!isset($this->pdo)) {
            $this->pdo = new PDO(
                $dsn,
                val('username', $this->config, ''),
                val('password', $this->config, ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'set names utf8'
                ]
            );
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
        } elseif ($quote) {
            return $this->pdo()->quote($value);
        } else {
            return $value;
        }
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
                $length = val(2, $matches);
                $unsigned = val(3, $matches);

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

    /**
     * Get the indexes from the database.
     *
     * @param string $tablename The name of the table to get the indexes for or an empty string to get all indexes.
     * @return array|null
     */
    protected function getIndexes($tablename = '') {
        $ltablename = strtolower($tablename);
        /* @var \PDOStatement */
        $stmt = $this->get(
            'information_schema.STATISTICS',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => $tablename ? $this->px.$tablename : [Db::OP_LIKE => addcslashes($this->px, '_%').'%']
            ],
            [
                'columns' => [
                    'INDEX_NAME',
                    'TABLE_NAME',
                    'NON_UNIQUE',
                    'COLUMN_NAME'
                ],
                'escapeTable' => false,
                'order' => ['TABLE_NAME', 'INDEX_NAME', 'SEQ_IN_INDEX'],
                Db::OPTION_MODE => Db::MODE_PDO
            ]
        );

        $stmt->execute();
        $indexDefs = $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_GROUP);

        foreach ($indexDefs as $indexName => $indexRows) {
            $row = reset($indexRows);
            $itablename = strtolower(ltrim_substr($row['TABLE_NAME'], $this->px));
            $index = [
                'name' => $indexName,
                'columns' => array_column($indexRows, 'COLUMN_NAME')
            ];

            if ($indexName === 'PRIMARY') {
                $index['type'] = Db::INDEX_PK;
                $this->tables[$itablename]['indexes'][Db::INDEX_PK] = $index;
            } else {
                $index['type'] = $row['NON_UNIQUE'] ? Db::INDEX_IX : Db::INDEX_UNIQUE;
                $this->tables[$itablename]['indexes'][] = $index;
            }
        }

        if ($ltablename) {
            return valr([$ltablename, 'indexes'], $this->tables, []);
        }
        return null;
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
            $this->tables = [];
            foreach ($tablenames as $tablename) {
                $this->tables[strtolower($tablename)] = ['name' => $tablename];
            }
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
        $tables = (array)$this->get(
            'information_schema.TABLES',
            [
                'TABLE_SCHEMA' => $this->getDbName(),
                'TABLE_NAME' => [Db::OP_LIKE => addcslashes($this->px, '_%').'%']
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
     * {@inheritdoc}
     */
    public function insert($tablename, array $rows, array $options = []) {
        $sql = $this->buildInsert($tablename, $rows, true, $options);
        $this->query($sql, Db::QUERY_WRITE);
        $id = $this->pdo()->lastInsertId();
        if (is_numeric($id)) {
            return (int)$id;
        } else {
            return $id;
        }
    }

    /**
     * Build an insert statement.
     *
     * @param string $tablename The name of the table to insert to.
     * @param array $row The row to insert.
     * @param bool $quotevals Whether or not to quote the values.
     * @param array $options An array of options for the insert. See {@link Db::insert} for the options.
     * @return string Returns the the sql string of the insert statement.
     */
    protected function buildInsert($tablename, array $row, $quotevals = true, $options = []) {
        if (val(Db::OPTION_UPSERT, $options)) {
            return $this->buildUpsert($tablename, $row, $quotevals, $options);
        } elseif (val(Db::OPTION_IGNORE, $options)) {
            $sql = 'insert ignore ';
        } elseif (val(Db::OPTION_REPLACE, $options)) {
            $sql = 'replace ';
        } else {
            $sql = 'insert ';
        }
        $sql .= $this->backtick($this->px.$tablename);

        // Add the list of values.
        $sql .=
            "\n".$this->bracketList(array_keys($row), '`').
            "\nvalues".$this->bracketList($row, $quotevals ? "'" : '');

        return $sql;
    }

    /**
     * Build an upsert statement.
     *
     * An upsert statement is an insert on duplicate key statement in MySQL.
     *
     * @param string $tablename The name of the table to update.
     * @param array $row The row to insert or update.
     * @param bool $quotevals Whether or not to quote the values in the row.
     * @param array $options An array of additional query options.
     * @return string Returns the upsert statement as a string.
     */
    protected function buildUpsert($tablename, array $row, $quotevals = true, $options = []) {
        // Build the initial insert statement first.
        unset($options[Db::OPTION_UPSERT]);
        $sql = $this->buildInsert($tablename, $row, $quotevals, $options);

        // Add the duplicate key stuff.
        $updates = [];
        foreach ($row as $key => $value) {
            $updates[] = $this->backtick($key).' = values('.$this->backtick($key).')';
        }
        $sql .= "\non duplicate key update ".implode(', ', $updates);

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function load($tablename, $rows, array $options = []) {
        $count = 0;
        $first = true;
        $spec = [];
        $stmt = null;

        // Loop over the rows and insert them with the statement.
        foreach ($rows as $row) {
            if ($first) {
                // Build the insert statement from the first row.
                foreach ($row as $key => $value) {
                    $spec[$key] = $this->paramName($key);
                }

                $sql = $this->buildInsert($tablename, $spec, false, $options);
                $stmt = $this->pdo()->prepare($sql);
                $first = false;
            }

            $params = array_translate($row, $spec);
            $stmt->execute($params);
            $count += $stmt->rowCount();
        }

        return $count;
    }

    /**
     * Make a valid pdo parameter name from a string.
     *
     * This method replaces invalid placeholder characters with underscores.
     *
     * @param string $name The name to replace.
     * @return string
     */
    protected function paramName($name) {
        $result = ':'.preg_replace('`[^a-zA-Z0-9_]`', '_', $name);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function update($tablename, array $set, array $where, array $options = []) {
        $sql = $this->buildUpdate($tablename, $set, $where, true, $options);
        $result = $this->query($sql, Db::QUERY_WRITE);

        if ($result instanceof \PDOStatement) {
            /* @var \PDOStatement $result */
            return $result->rowCount();
        }
        return $result;
    }

    /**
     * Build a sql update statement.
     *
     * @param string $tablename The name of the table to update.
     * @param array $set An array of columns to set.
     * @param array $where The where filter.
     * @param bool $quotevals Whether or not to quote the values.
     * @param array $options Additional options for the query.
     * @return string Returns the update statement as a string.
     */
    protected function buildUpdate($tablename, array $set, array $where, $quotevals = true, array $options = []) {
        $sql = 'update '.
            (val(Db::OPTION_IGNORE, $options) ? 'ignore ' : '').
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
     * {@inheritdoc}
     */
    public function delete($tablename, array $where, array $options = []) {
        if (val(Db::OPTION_TRUNCATE, $options)) {
            if (!empty($where)) {
                throw new \InvalidArgumentException("You cannot truncate $tablename with a where filter.", 500);
            }
            $sql = 'truncate table '.$this->backtick($this->px.$tablename);
        } else {
            $sql = 'delete from '.$this->backtick($this->px.$tablename);

            if (!empty($where)) {
                $sql .= "\nwhere ".$this->buildWhere($where);
            }
        }
        return $this->query($sql, Db::QUERY_WRITE);
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
     * Construct a column definition string from an array defintion.
     *
     * @param string $name The name of the column.
     * @param array $def The column definition.
     * @return string Returns a string representing the column definition.
     */
    protected function columnDefString($name, array $def) {
        $result = $this->backtick($name).' '.$this->columnTypeString($def['type']);

        if (val('required', $def)) {
            $result .= ' not null';
        }

        if (isset($def['default'])) {
            $result .= ' default '.$this->quoteVal($def['default']);
        }

        if (val('autoincrement', $def)) {
            $result .= ' auto_increment';
        }

        return $result;
    }

    /**
     * Return the SDL string that defines an index.
     *
     * @param string $tablename The name of the table that the index is on.
     * @param array $def The index defintion. This definition should have the following keys.
     *
     * columns
     * : An array of columns in the index.
     * type
     * : One of "index", "unique", or "primary".
     * @return null|string Returns the index string or null if the index is not correct.
     */
    protected function indexDefString($tablename, array $def) {
        $indexName = $this->backtick($this->buildIndexName($tablename, $def));
        switch (val('type', $def, Db::INDEX_IX)) {
            case Db::INDEX_IX:
                return "index $indexName ".$this->bracketList($def['columns'], '`');
            case Db::INDEX_UNIQUE:
                return "unique $indexName ".$this->bracketList($def['columns'], '`');
            case Db::INDEX_PK:
                return "primary key ".$this->bracketList($def['columns'], '`');
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function alterTable($tablename, array $alterdef, array $options = []) {
        $columnOrders = array_flip(array_keys($alterdef['def']['columns']));

        $parts = [];

        // Add the columns and indexes.
        foreach ($alterdef['add']['columns'] as $cname => $cdef) {
            // Figure out the order of the column.
            $ord = $columnOrders[$cname];
            if ($ord == 0) {
                $pos = ' first';
            } elseif ($pos = array_search($ord - 1, $columnOrders)) {
                $pos = ' after '.$pos;
            }

            $parts[] = 'add '.$this->columnDefString($cname, $cdef).$pos;
        }
        foreach ($alterdef['add']['indexes'] as $ixdef) {
            $parts[] = 'add '.$this->indexDefString($tablename, $ixdef);
        }

        // Alter the columns.
        foreach ($alterdef['alter']['columns'] as $cname => $cdef) {
            $parts[] = 'modify '.$this->columnDefString($cname, $cdef);
        }

        // Drop the columns and indexes.
        foreach ($alterdef['drop']['columns'] as $cname => $_) {
            $parts[] = 'drop '.$this->backtick($cname);
        }
        foreach ($alterdef['drop']['indexes'] as $ixdef) {
            $parts[] = 'drop index '.$this->backtick($ixdef['name']);
        }

        if (empty($parts)) {
            return false;
        }

        $sql = 'alter '.
            (val(Db::OPTION_IGNORE, $options) ? 'ignore ' : '').
            'table '.$this->backtick($this->px.$tablename)."\n  ".
            implode(",\n  ", $parts);

        $result = $this->query($sql, Db::QUERY_DEFINE);
        return $result;
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
}
