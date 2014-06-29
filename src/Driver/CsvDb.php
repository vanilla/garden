<?php

namespace Garden\Driver;

use Garden\Db;

class CsvDb extends Db {
    /// Constants ///

    const DELIM = ',';
    const ESCAPE = '\\';
    const NEWLINE = "\n";
    const NULL = '\N';
    const QUOTE = '"';

    /**
     * @var int The maximum number of files that can be open at any time.
     */
    public $maxOpenFiles = 5;

    /// Protected Properties ///

    /**
     * Whether or not the mb_* functions are supported.
     *
     * @var bool
     */
    protected static $mb = false;

    /**
     * The directory that the csv files are in.
     *
     * @var string
     */
    protected $dir;

    /**
     * @var array
     */
    protected $structure = [];

    protected $fps = [];

    /// Methods ///

    /**
     * Initialize an instance of the {@link CsvDb} class.
     *
     * @param array $dir The base directory of the csv's.
     */
    public function __construct($dir) {
        $dir = rtrim($dir, '/');

        // Create the directory.
        touchdir($dir);

        $this->dir = $dir;
        $this->loadStructure();

        self::$mb = function_exists('mb_detect_encoding');
    }

    /**
     * Clean up the file open pointers in the database.
     */
    public function __destruct() {
        foreach ($this->fps as $fp) {
            fclose($fp);
        }
    }

    /**
     * Return the file pointer for a given table.
     *
     * @param string $tablename The name of the table.
     * @param string $mode The file mode for new files. You should only use r+b and a+b.
     * @return resource Returns the file pointer.
     */
    protected function fp($tablename, $mode = 'a+b') {
        if (isset($this->fps[$tablename])) {
            $fp = $this->fps[$tablename];
        } else {
            $fp = fopen($this->tablePath($tablename), $mode);
            $this->fps[$tablename] = $fp;

            if (count($this->fps) > $this->maxOpenFiles) {
                $fp = array_shift($this->fps);
                fclose($fp);
            }
        }
        return $fp;
    }

    /**
     * Load the structure file that describes the csvs.
     */
    protected function loadStructure() {
        $path = $this->dir.'/structure.json';
        if (file_exists($path)) {
            $this->structure = json_decode(file_get_contents($path), true);
            if ($this->structure === null) {
                throw new \Exception("Could not decode the structure file.", 500);
            }
        } else {
            $this->structure = [];
        }
    }

    public function defineIndex($table, $column, $type, $suffix = null) {
        $def = parent::defineIndex($table, $column, $type, $suffix);
        $this->structure[$table]['indexes'][$def['name']] = $def;
        $this->saveStructure();
    }

    protected function saveStructure() {
        $path = $this->dir.'/structure.json';
        $result = file_put_contents_safe($path, json_encode($this->structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (!$result) {
            throw new \Exception("Error saving CsvDb structure.");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function dropTable($table) {
        $tables = (array)$table;

        foreach ($tables as $tablename) {
            $path = $this->tablePath($tablename);
            if (file_exists($path)) {
                unlink($path);
            }
            if (isset($this->structure[$tablename])) {
                unset($this->structure[$tablename]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete($table, $where) {
        if (!empty($where)) {
            throw new NotImplementedException("CsvDb->delete() does not implement a delete with a where filter.");
        }
        $this->dropTable($table);
    }

    public function get($table, $where, $order = array(), $limit = false, $options = array()) {
        throw \NotImplementedException(__CLASS__, 'get');
    }

    public function indexDefinitions($table) {
        $tabledef = $this->tableDefinition($table);
        if (!$tabledef)
            return null;
        return val('indexes', $tabledef, array());
    }

    public function insert($table, $rows, $options = array()) {
        if (empty($rows)) {
            return 0;
        } elseif (!isset($rows[0])) {
            $rows = [$rows];
        }

        $tabledef = $this->tableDefinition($table);
        if (!$tabledef) {
            // Try and define the table from the row.
            $columns = array();
            foreach ($rows[0] as $column => $value) {
                $columns[$column] = array('type' => $this->guessType($value));
            }

            $this->defineTable($table, $columns, $options);
            $tabledef = $this->tableDefinition($table);
        }
        $columns = array_keys($tabledef['columns']);

        // Loop through the rows and insert them.
        $fp = $this->fp($table);
        foreach ($rows as $row) {
            fwrite($fp, self::formatRow($row, $columns));
        }
    }

    public function defineTable($tabledef, $options = array()) {
        $table = $tabledef['name'];

        $path = $this->tablePath($table);

        $currentDef = $this->tableDefinition($table);

        if ($currentDef) {
            // There is already a definition which won't work if there is already data in the table.
            if (file_exists($path)) {
                // Make sure the tables have the same columns.
                if (array_keys($tabledef['columns']) !== array_keys($currentDef['columns'])) {
                    throw new Exception("You can't change the definition of the $table.csv table after it has data in it.", 400);
                }
            }

            // We can merge indexes.
            if (isset($currentDef['indexes'])) {
                $tabledef['indexes'] = array_merge($currentDef['indexes'], $tabledef['indexes']);
            }
        }

        $this->structure[$tabledef['name']] = $tabledef;
        $this->saveStructure();
    }

    protected function tablePath($table) {
        return $this->dir."/$table.csv";
    }

    public function tableDefinition($table) {
        return val($table, $this->structure, null);
    }

    protected function writeHeaderRow($fp, $table) {
        if (is_string($table)) {
            $table = $this->tableDefinition($table);
        }

        $columns = array_keys($table['columns']);
        fwrite($fp, implode(self::DELIM, $columns));
        fwrite($fp, self::NEWLINE);
    }

    /**
     * Format a row of data as a csv row.
     *
     * @param array $row The row of data.
     * @param array $columns An array of column names in the table structure.
     * @return string Returns the row formatted as a csv string.
     */
    public static function formatRow(array $row, array $columns) {
        $outrow = array_fill_keys($columns, null);

        foreach ($columns as $column) {
            if (isset($row[$column])) {
                $outrow[$column] = self::formatValue($row[$column]);
            }
        }

        return implode(self::DELIM, $outrow).self::NEWLINE;
    }

    /**
     * Format a value in a way suitable for a csv.
     *
     * @param mixed $value The valut to format.
     * @return string Returns the string suitable to be put in a csv file.
     */
    public static function formatValue($value) {
        // Format the value for writing.
        if (is_null($value)) {
            $value = self::NULL;
        } elseif (is_numeric($value)) {
            // Do nothing, formats as is.
        } elseif (is_string($value)) {
            if (self::$mb && mb_detect_encoding($value) != 'UTF-8') {
                $value = utf8_encode($value);
            }

            $value = str_replace(["\r\n", "\r"], [self::NEWLINE, self::NEWLINE], $value);
            $value = self::QUOTE.
                str_replace(
                    [self::ESCAPE, self::DELIM, self::NEWLINE, self::QUOTE],
                    [self::ESCAPE.self::ESCAPE, self::ESCAPE.self::DELIM, self::ESCAPE.self::NEWLINE, self::ESCAPE.self::QUOTE],
                    $value
                ).self::QUOTE;
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } else {
            // Unknown format.
            $value = self::NULL;
        }
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function tables($withdefs = false) {
        if ($withdefs) {
            return $this->structure;
        } else {
            return array_keys($this->structure);
        }
    }

    public function tableDefinitions($table) {
        if (isset($this->structure[$table])) {
            return $this->structure[$table];
        }
        return null;
    }

    public function update($table, $row, $where, $options = array()) {
        throw new \BadFunctionCallException(__CLASS__.'->update() not implented.');
    }
}