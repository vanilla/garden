<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db\Db;
use Garden\Db\DbDef;

/**
 * The base class for database tests.
 */
abstract class BaseDbTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Db The database connection for the tests.
     */
    protected static $db;

    /// Methods ///

    /**
     * Get the database connection for the test.
     *
     * @return Db Returns the db object.
     */
    protected static function createDb() {
        return null;
    }

    /**
     * Get the database def.
     *
     * @return DbDef Returns the db def.
     */
    protected static function createDbDef() {
        return new DbDef(self::$db);
    }

    /**
     * Set up the db link for the test cases.
     */
    public static function setUpBeforeClass() {
        // Drop all of the tables in the database.
        self::$db = static::createDb();

        $tables = self::$db->getAllTables();
        foreach ($tables as $table) {
            self::$db->dropTable($table);
        }
    }

    /**
     * Assert that two table definitions are equal.
     *
     * @param string $tablename The name of the table.
     * @param array $expected The expected table definition.
     * @param array $actual The actual table definition.
     * @param bool $subset Whether or not expected can be a subset of actual.
     */
    public function assertDefEquals($tablename, $expected, $actual, $subset = true) {
        $this->assertEquals($expected['name'], $actual['name'], "Table names are not equal.");


        $colsExpected = $expected['columns'];
        $colsActual = $actual['columns'];

        if ($subset) {
            $colsActual = array_intersect_key($colsActual, $colsExpected);
        }
        $this->assertEquals($colsExpected, $colsActual, "$tablename columns are not the same.");

        $ixExpected = (array)$expected['indexes'];
        $ixActual = (array)$actual['indexes'];

        $isExpected = [];
        foreach ($ixExpected as $ix) {
            $isExpected[] = val('type', $ix, Db::INDEX_IX).'('.implode(', ', $ix['columns']).')';
        }
        asort($isExpected);

        $isActual = [];
        foreach ($ixActual as $ix) {
            $isActual[] = val('type', $ix, Db::INDEX_IX).'('.implode(', ', $ix['columns']).')';
        }
        asort($isActual);

        if ($subset) {
            $isActual = array_intersect($isActual, $isExpected);
        }
        $this->assertEquals($isExpected, $isActual, "$tablename indexes are not the same.");
    }
}
