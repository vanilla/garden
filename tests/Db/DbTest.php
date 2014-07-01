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

    /**
     * Test a create table.
     */
    public function testCreateTable() {
        $tableDef = [
            'columns' => [
                'userID' => ['type' => 'int', 'primary' => true, 'autoincrement' => true],
                'name' => ['type' => 'varchar(50)', 'required' => true],
                'email' => ['type' => 'varchar(255)', 'required' => true],
                'fullName' => ['type' => 'varchar(50)'],
                'banned' => ['type' => 'tinyint', 'required' => true, 'default' => 0],
                'insertTime' => ['type' => 'int', 'required' => true]
            ],
            'indexes' => [
                ['columns' => ['name'], 'type' => Db::INDEX_UNIQUE],
                ['columns' => ['insertTime']]
            ]
        ];

        self::$db->setTable('user', $tableDef);
    }

    /**
     * Test {@link Db::insert()}.
     *
     * @depends testCreateTable
     */
    public function testInsert() {
        $db = self::$db;

        $user = $this->provideUser('Test Insert');
        $userID = $db->insert('user', $user);

        $dbUser = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user, array_intersect_key($dbUser, $user));
    }

    /**
     * Test {@link Db::insert()} with the ignore option.
     *
     * @depends testCreateTable
     */
    public function testInsertIgnore() {
        $db = self::$db;

        $user = $this->provideUser('Insert Ignore');

        $userID = $db->insert('user', $user);
        $dbUser = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user, array_intersect_key($dbUser, $user));

        $user2 = $this->provideUser('Insert Ignore2');
        $this->assertNotEquals($user, $user2);

        $user2['userID'] = $userID;
        $id = $db->insert('user', $user2, [Db::OPTION_IGNORE => true]);

        $dbUser2 = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user, array_intersect_key($dbUser2, $user));
    }

    /**
     * Test {@link Db::insert()} with the replace option.
     *
     * @depends testCreateTable
     */
    public function testInsertReplace() {
        $db = self::$db;

        $user = $this->provideUser('Insert Replace');

        $userID = $db->insert('user', $user);
        $dbUser = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user, array_intersect_key($dbUser, $user));

        $user2 = $this->provideUser('Insert Replace2');
        $this->assertNotEquals($user, $user2);

        $user2['userID'] = $userID;
        $id = $db->insert('user', $user2, [Db::OPTION_REPLACE => true]);

        $dbUser2 = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user2, array_intersect_key($dbUser2, $user2));
    }

    /**
     * Test {@link Db::insert()} with the upsert option.
     *
     * @depends testCreateTable
     */
    public function testInsertUpsert() {
        $db = self::$db;

        $user = $this->provideUser('Insert Upsert');

        $userID = $db->insert('user', $user);
        $dbUser = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user, array_intersect_key($dbUser, $user));

        $user2 = $this->provideUser();
        $this->assertNotEquals($user2, $user);
        unset($user2['fullName']);
        $user2['userID'] = $userID;

        $db->insert('user', $user2, [Db::OPTION_UPSERT => true]);

        $dbUser2 = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($user['fullName'], $dbUser2['fullName']);

        $this->assertEquals($user2, array_intersect_key($dbUser2, $user2));

    }

    /**
     * Test {@link Db::load()}.
     *
     * @depends testCreateTable
     */
    public function testLoad() {
        $db = self::$db;

        $db->load('user', $this->provideUsers(100), [Db::OPTION_IGNORE => true]);
    }

    /**
     * Test {@link Db::load()} with an array of data.
     *
     * @depends testCreateTable
     */
    public function testLoadArray() {
        $db = self::$db;

        $users = iterator_to_array($this->provideUsers(10));

        $db->load('user', $users, [Db::OPTION_REPLACE => true]);
    }

    /**
     * Test {@link Db::testGetAllTables()}.
     */
    public function testGetAllTables() {
        $db = self::$db;

        $tablenames = $db->getAllTables();
        $tabledefs = $db->getAllTables(true);
    }

    /**
     * Provide some random user rows.
     *
     * @param int $count The number of users to provide.
     * @return \Generator Returns a {@link \Generator} of users.
     */
    public function provideUsers($count = 10) {
        for ($i = 0; $i < $count; $i++) {
            $name = \Faker\Name::name();

            $user = [
                'name' => \Faker\Internet::userName($name),
                'email' => \Faker\Internet::email($name),
                'fullName' => $name,
                'insertTime' => time()
            ];

            yield $user;
        }
    }

    public function provideUser($name = '') {
        if (!$name) {
            $name = \Faker\Name::name();
        }

        $user = [
            'name' => \Faker\Internet::userName($name),
            'email' => \Faker\Internet::email($name),
            'fullName' => $name,
            'insertTime' => time()
        ];

        return $user;
    }
}
