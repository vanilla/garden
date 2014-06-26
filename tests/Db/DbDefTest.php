<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db;
use Garden\DbDef;

abstract class DbDefTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        // Drop all of the tables in the database.
        $db = static::getDb();
        $tables = $db->tables();
        $db->dropTable($tables);
    }

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
