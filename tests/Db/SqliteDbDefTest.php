<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Db;

use Garden\Db\SqliteDb;

/**
 * Run the {@link DbDefTest} against {@link SqliteDb}.
 */
class SqliteDbDefTest extends DbDefTest {
    /**
     * Create the {@link SqliteDb}.
     *
     * @return \Garden\Db\SqliteDb Returns the new database connection.
     */
    protected static function createDb() {
        if (getenv('TRAVIS')) {
            $path = ':memory:';
        } else {
            $path = PATH_CACHE.'/dbdeftest.sqlite';
        }

        $db = new SqliteDb([
            'path' => $path,
        ]);

        $db->setPx('tst_');

        return $db;
    }
}
