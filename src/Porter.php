<?php namespace Garden;

    /**
     * Format of a porter run.
     *
     *     array('source' => array(
     *        ['source' => array('table', array(filter)),]
     *        'destination' => tablename,
     *        'columns' => array(
     *           'sourcecolumn' => array('destcolumn'
     *              [, 'type' => dbtype]
     *              [, 'sourcecolumn' => 'name']
     *              [, 'filter' => callable]
     *           )
     *        )
     *        [, 'rowfilter' => callable]
     */

/**
 *
 */
class Porter {
    /// Properties ///

    /**
     * The destination database.
     * @var Db
     */
    protected $destination;

    /**
     * @var array
     */
    protected $formats;

    /**
     * The source database.
     * @var Db
     */
    protected $source;

    /// Methods ///

    /**
     *
     * @param Db $source
     * @param Db $destination
     */
    public function __construct($source, $destination) {
        $this->source = $source;
        $this->destination = $destination;
    }

    public function run() {
        // Get all of the tables in the source database.
        if (!isset($this->formats)) {
            $this->formats = $this->getFormatsFromDb($this->source);
        } else {
            $this->fixFormats($this->formats);
        }

        // Port the data in each table.
        foreach ($this->formats as $name => $format) {
            $this->dumpTable($format);
        }
    }

    /**
     * Get the translation format from the database.
     *
     * @param Db $db
     * @return type
     */
    protected function getFormatsFromDb($db) {
        $tables = $db->tables(true);
        $formats = $this->fixFormats($tables);
        return $formats;
    }

    protected function fixFormats($data) {
        $result = array();


        foreach ($data as $table => $tdef) {
            $format = $tdef;

            // Set the source query.
            array_touch('source', $format, array($table, array()));
            array_touch('destination', $format, $format['destination']);

            // Coax the columns into the proper format.
            $columns = array();
            if (isset($tdef['columns'])) {
                foreach ($tdef['columns'] as $sourceColumn => $cdef) {
                    if (is_string($cdef)) {
                        // This is a format in the form sourcecolumns => destcolumn
                        $cformat = array($cdef);
                    } elseif (isset($cdef[0])) {
                        $cformat = array_change_key_case($cdef);
                    } elseif (isset($cdef['column'])) {
                        // This is an old porter format.
                        $cformat = array($cdef['column']) + $cdef;
                        unset($cformat['column']);
                    } else {
                        $cformat = array(val('sourcecolumn', $cdef, $sourceColumn)) + array_change_key_case($cdef);
                    }

                    array_touch('sourcecolumn', $cformat, $sourceColumn);
                    array_touch('type', $cformat, 'varchar(255)');
                    array_touch('default', $cformat, null);

                    $columns[$sourceColumn] = $cformat;
                }
            }
            $format['columns'] = $columns;
//            $format['defaultrow'] = $this->getDefaultRow($format);

            $result[$table] = $columns;
        }

        return $result;
    }

    public function dumpTable($format) {
        $table = $format['destination'];

        timerStart("dumping $table");

        // Define the table.
        timerStart("defining $table");
        $this->destination->defineTable($format);
        timerStop();

        // Loop over the data from the source.
        timerStart("loading $table");
        $rows = $this->source->get($format['source'][0], $format['source'][1], null, null, array(Db::GET_UNBUFFERED => true));

        $this->destination->loadStart($table);
        foreach ($rows as $row) {
            $trow = $this->translateRow($row, $format);
            $this->destination->loadRow($trow);
        }
        $load = $this->destination->loadFinish();
        timerStop(array_translate($load, array('count' => 'rows'))); // loading table

        timerStop(); // dumping table
    }

    /**
     * Translate a row from one row format to another.
     *
     * @param array $row The data to translate.
     * @param array $format The translation format.
     * @return array The translated row.
     */
    protected function translateRow($row, $format) {
        // Apply the row filter.
        if (isset($format['rowfilter'])) {
            call_user_func_array($format['rowfilter'], array(&$row));
        }

        $result = array();
        foreach ($format['columns'] as $key => $cdef) {
            if (array_key_exists($cdef['sourcecolumn'], $row))
                $value = $row[$cdef['sourcecolumn']];
            else
                $value = $cdef['default'];

            if (isset($cdef['filter'])) {
                $value = call_user_func($cdef['filter'], $value, $key, $row);
            }

            $result[$cdef[0]] = $value;
        }
        return $result;
    }
}
