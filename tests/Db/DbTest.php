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
 * Test the basic functionality of the Db* classes.
 */
abstract class DbTest extends BaseDbTest {
    /// Properties ///

    /// Methods ///

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
                ['columns' => ['email']],
                ['columns' => ['insertTime']]
            ]
        ];

        self::$db->setTableDef('user', $tableDef);
    }

    /**
     * Test {@link Db::insert()}.
     *
     * @depends testCreateTable
     */
    public function testInsert() {
        $db = self::$db;

        $user = $this->provideUser('Insert Test');
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

        $user = $this->provideUser('Upsert Test');

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
     * Test {@link Db::insert()} with the upsert option and a primary key composed of multiple columns.
     */
    public function testInsertUpsertMultiKey() {
        $db = self::$db;
        $dbdef = new DbDef($db);

        $db->dropTable('userMeta', [Db::OPTION_IGNORE => true]);
        $dbdef->table('userMeta')
            ->column('userID', 'int')
            ->column('key', 'varchar(50)')
            ->column('value', 'text')
            ->index(['userID', 'key'], Db::INDEX_PK)
            ->exec();

        $db->insert(
            'userMeta',
            ['userID' => 1, 'key' => 'bio', 'value' => 'Just some dude.'],
            [Db::OPTION_UPSERT => true]
        );

        $row = $db->getOne('userMeta', ['userID' => 1, 'key' => 'bio']);
        $this->assertEquals(
            ['userID' => 1, 'key' => 'bio', 'value' => 'Just some dude.'],
            $row
        );

        $db->insert(
            'userMeta',
            ['userID' => 1, 'key' => 'bio', 'value' => 'Master of the universe.'],
            [Db::OPTION_UPSERT => true]
        );

        $rows = $db->get('userMeta', ['userID' => 1, 'key' => 'bio']);
        $this->assertEquals(1, count($rows));
        $firstRow = reset($rows);
        $this->assertEquals(
            ['userID' => 1, 'key' => 'bio', 'value' => 'Master of the universe.'],
            $firstRow
        );
    }

    /**
     * Test {@link Db::update()}.
     */
    public function testUpdate() {
        $db = self::$db;

        $user = $this->provideUser('Update Test');

        $userID = $db->insert('user', $user);

        $email = sha1(microtime()).'@foo.com';
        $updated = $db->update(
            'user',
            ['email' => $email],
            ['userID' => $userID]
        );
        $this->assertEquals(1, $updated, "Db->update() must return the number of rows updated.");

        $dbUser = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals($email, $dbUser['email'], "Update value not in the db.");

        // Update on another column.
        $updated2 = $db->update(
            'user',
            ['name' => 'tupdate'],
            ['email' => $email]
        );
        $this->assertEquals(1, $updated2);

        $dbUser2 = $db->getOne('user', ['userID' => $userID]);
        $this->assertEquals('tupdate', $dbUser2['name'], "Update value not in the db.");
    }

    /**
     * Test {@link Db::update()} with the ignore option.
     */
    public function testUpdateIgnore() {
        $db = self::$db;

        $user1 = $this->provideUser('First Update');
        $userID1 = $db->insert('user', $user1);

        $user2 = $this->provideUser('Second Update');
        $userID2 = $db->insert('user', $user2);

        $updated = $db->update(
            'user',
            ['name' => $user2['name']],
            ['userID' => $userID1],
            [Db::OPTION_IGNORE => true]
        );
        $this->assertEquals(0, $updated);
    }

    /**
     * Test various where operators.
     *
     * @dataProvider provideTupleTests
     */
    public function testWhereOperators($where, $expected) {
        $db = self::$db;

        // Create a table for the test.
        $db->setTableDef(
            'tuple',
            [
                'columns' => [
                    'id' => ['type' => 'int']
                ],
                'indexes' => [
                    ['columns' => ['id']],
                ]
            ]
        );
        $db->delete('tuple', []);

        $data = [['id' => null]];
        for ($i = 1; $i <= 5; $i++) {
            $data[] = ['id' => $i];
        }

        $db->load('tuple', $data);

        // Test some logical gets.
        $dbData = $db->get('tuple', $where, ['order' => ['id']]);
        $values = array_column($dbData, 'id');
        $this->assertEquals($expected, $values);
    }

    /**
     * Provide somet tests for the where clause test.
     *
     * @return array Returns an array of function args.
     */
    public function provideTupleTests() {
        $result = [
            '>' => [['id' => [Db::OP_GT => 3]], [4, 5]],
            '>=' => [['id' => [Db::OP_GTE => 3]], [3, 4, 5]],
            '<' => [['id' => [Db::OP_LT => 3]], [1, 2]],
            '<=' => [['id' => [Db::OP_LTE => 3]], [1, 2, 3]],
            '=' => [['id' => [Db::OP_EQ => 2]], [2]],
            '<>' => [['id' => [Db::OP_NE => 3]], [1, 2, 4, 5]],
            'is null' => [['id' => null], [null]],
            'is not null' => [['id' => [Db::OP_NE => null]], [1, 2, 3, 4, 5]],
            'all' => [[], [null, 1, 2, 3, 4, 5]],
            'in' => [['id' => [Db::OP_IN => [3, 4, 5]]], [3, 4, 5]],
            'in (short)' => [['id' => [3, 4, 5]], [3, 4, 5]],
            '= in' => [['id' => [Db::OP_EQ => [3, 4, 5]]], [3, 4, 5]],
            '<> in' => [['id' => [Db::OP_NE => [3, 4, 5]]], [1, 2]],
            'and' =>[['id' => [
                Db::OP_AND => [
                    Db::OP_GT => 3,
                    Db::OP_LT => 5
                ]
            ]], [4]],
            'or' =>[['id' => [
                Db::OP_OR => [
                    Db::OP_LT => 3,
                    Db::OP_EQ => 5
                ]
            ]], [1, 2, 5]]
        ];

        return $result;
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
        $result = [];

        for ($i = 0; $i < $count; $i++) {
            $name = \Faker\Name::name();

            $user = [
                'name' => \Faker\Internet::userName($name),
                'email' => \Faker\Internet::email($name),
                'fullName' => $name,
                'insertTime' => time()
            ];

            $result[] = $user;
        }

        return new \ArrayIterator($result);
    }

    /**
     * Provide a single random user.
     *
     * @param string $fullname The full name of the user.
     * @return array
     */
    public function provideUser($fullname = '') {
        if (!$fullname) {
            $fullname = \Faker\Name::name();
        }

        $user = [
            'name' => \Faker\Internet::userName($fullname),
            'email' => \Faker\Internet::email($fullname),
            'fullName' => $fullname,
            'insertTime' => time()
        ];

        return $user;
    }
}
