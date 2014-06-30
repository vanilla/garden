<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;


use Garden\Db\Db;

/**
 * Exectute the {@link DbTableTest} tests against MySQL.
 */
class MySqlDbTableTest extends DbTableTest {

    /**
     * Get the database connection for the test.
     *
     * @return Db Returns the db object.
     */
    protected static function createDb() {
        $db = Db::create([
            'driver' => 'MySqlDb',
            'host' => '127.0.0.1',
            'username' => 'root',
            'dbname' => 'phpunit_garden',
        ]);

        return $db;
    }
}
