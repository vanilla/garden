<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db\Db;

/**
 * Test the basic functionality of the Db* classes.
 */
abstract class DbTableTest extends \PHPUnit_Framework_TestCase {
    /// Properties ///

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
     * Set up the db link for the test cases.
     */
    public static function setUpBeforeClass() {
        // Drop all of the tables in the database.
        $db = static::createDb();
        $tables = $db->getAllTables();
        array_map([$db, 'dropTable'], $tables);

        self::$db = $db;
    }

    public function testCreateTable() {
        $tableDef = [
            'columns' => [
                'userID' => ['type' => 'int', 'primary' => true, 'autoincrement' => true],
                'name' => ['type' => 'varchar(50)', 'required' => true],
                'fullName' => ['type' => 'varchar(50)'],
                'banned' => ['type' => 'tinyint', 'required' => true, 'default' => 0],
                'insertTime' => ['type' => 'int', 'required' => true],
                'insertUserID' => ['type' => 'int', 'required' => true]
            ],
            'indexes' => [
                ['columns' => ['name'], 'type' => Db::INDEX_UNIQUE],
                ['columns' => ['insertTime']]
            ]
        ];

        self::$db->setTable('user', $tableDef);
    }

    public function testGetAllTables() {
        $db = self::$db;

        $tablenames = $db->getAllTables();
        $tabledefs = $db->getAllTables(true);
    }
}
