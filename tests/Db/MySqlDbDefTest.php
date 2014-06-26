<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license Proprietary
 */

namespace Garden\Tests\Db;

use Garden\Driver\MySqlDb;

class MySqlDbDefTest extends DbDefTest {
    protected static function getDb() {
        $db = new MySqlDb([
            'host' => '127.0.0.1',
            'username' => 'root',
            'dbname' => 'phpunit_garden',
        ]);

        return $db;
    }
}
