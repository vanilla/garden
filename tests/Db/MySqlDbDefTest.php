<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Tests\Db;

use Garden\Db\MySqlDb;

class MySqlDbDefTest extends DbDefTest {
    /**
     * @return \Garden\Db\MySqlDb Returns the new database connection.
     */
    protected static function createDb() {
        $db = new MySqlDb([
            'host' => '127.0.0.1',
            'username' => 'root',
            'dbname' => 'phpunit_garden',
        ]);

        $db->setPx('gdndef_');

        return $db;
    }
}
