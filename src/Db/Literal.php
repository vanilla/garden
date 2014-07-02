<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Db;

/**
 * Represents an string that won't be escaped in queries.
 */
class Literal {
    /// Properties ///

    /**
     * @var array An array that maps driver names to literals.
     */
    protected $driverValues = [];

    /// Methods ///

    /**
     * Initialize an instance of the {@link Literal} class.
     *
     * @param string|array $value Either a string default value or an array in the form
     * `['driver' => 'literal']` to specify different literals for different database drivers.
     */
    public function __construct($value) {
        if (is_string($value)) {
            $this->driverValues['default'] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $key => $value) {
                $this->driverValues[$this->normalizeKey($key)] = $value;
            }
        }
    }

    /**
     * Get the literal value.
     *
     * @param string $driver The name of the database driver to fetch the literal for.
     * @return string Returns the value for the specific driver, the default literal, or "null" if there is no default.
     */
    public function getValue($driver = 'default') {
        $driver = $this->normalizeKey($driver);

        if (isset($this->driverValues[$driver])) {
            return $this->driverValues[$driver];
        } elseif (isset($this->driverValues['default'])) {
            return $this->driverValues['default'];
        } else {
            return 'null';
        }
    }

    /**
     * Set the literal value.
     *
     * @param string $value The new value to set.
     * @param string $driver The name of the database driver to set the value for.
     * @return Literal Returns $this for fluent calls.
     */
    public function setValue($value, $driver = 'default') {
        $driver = $this->normalizeKey($driver);
        $this->driverValues[$driver] = $value;
        return $this;
    }

    /**
     * Normalize the driver name for the drivers array.
     *
     * @param string $key The name of the driver.
     * @return string Returns the driver name normalized.
     */
    protected function normalizeKey($key) {
        return rtrim_substr(strtolower(basename($key)), 'db');
    }

    /**
     * Create and return a {@link Literal} object.
     *
     * @param string|array $value The literal value(s) as passed to {@link Literal::__construct()}.
     * @return Literal Thew new literal value.
     */
    public static function value($value) {
        $literal = new Literal($value);
        return $literal;
    }

    /**
     * Creat and return a {@link Literal} object that will query the current unix timesatmp.
     *
     * @return Literal Returns the timestamp expression.
     */
    public static function timestamp() {
        $literal = new Literal([
            'mysql' => 'unix_timestamp()',
            'sqlite' => "date('now', 'unixepoch')",
            'posgresql' => 'extract(epoch from now())',
            'default' => time()
        ]);
        return $literal;
    }
}
