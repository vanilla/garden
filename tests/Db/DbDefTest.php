<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db;
use Garden\DbDef;

/**
 * Test various aspects of the {@link DbDef} class and the {@link Db} class as it relates to it.
 */
abstract class DbDefTest extends \PHPUnit_Framework_TestCase {

    /**
     * Set up the db link for the test cases.
     */
    public static function setUpBeforeClass() {
        // Drop all of the tables in the database.
        $db = static::getDb();
        $tables = $db->tables();
        $db->dropTable($tables);
    }

    /**
     * Test calling {@link Db::dropTable()} with no tables.
     */
    public function testDropTableEmpty() {
        static::getDb()->dropTable([]);
    }

    /**
     * Test calling {@link Db::dropTable()} with a non-existant table.
     */
    public function testDropTableDoesntExist() {
        static::getDb()->dropTable('lfdsjfod');
    }

    /**
     * Test a basic call to {@link Db::createTable()}.
     */
    public function testCreateTable() {
        $def = $this->getDbDef();

        $def->table('user')
            ->primaryKey('userID')
            ->column('name', 'varchar(50)')
            ->index(Db::INDEX_IX, 'name')
            ->exec();

        $defArray = $def->jsonSerialize();
        $defArrayDb = $def->db()->tableDefinitions('user');
    }

    /**
     * Get the database connection.
     *
     * @return Db Returns the db.
     */
    protected static function getDb() {
        return null;
    }

    /**
     * Get the database def.
     *
     * @return DbDef Returns the db def.
     */
    protected static function getDbDef() {
        return new DbDef(static::getDb());
    }
}
