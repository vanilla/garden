<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

/**
 * Test core array functions.
 */
class ArrayFunctionsTest extends PHPUnit_Framework_TestCase {

    /**
     * Test {@link array_column_php()}.
     */
    public function testArrayColumn() {
        $ds = $this->getDataset();

        $r = array_column_php($ds, 'id');
        $this->assertEquals([123, 456, 777], $r);

        $r2 = array_column_php($ds, 'name');
        $this->assertEquals(['foo', 'Hello world', 'Smacker'], $r2);

        $r3 = array_column_php($ds, 'slug');
        $this->assertEquals(['hello-world', 'smacker'], $r3);

        $r4 = array_column_php($ds, 'na');
        $this->assertEquals([], $r4);
    }

    /**
     * Test {@link array_column_php()} with indexes and values.
     */
    public function testArrayColumnWithIndexes() {
        $ds = $this->getDataset();

        $r = array_column_php($ds, 'name', 'id');
        $this->assertEquals([123 => 'foo', 456 => 'Hello world', 777 => 'Smacker'], $r);

        $r2 = array_column_php($ds, 'slug', 'id');
        $this->assertEquals([456 => 'hello-world', 777 => 'smacker'], $r2);
    }

    /**
     * Test {@link array_column_php()} errors and warnings.
     *
     * @expectedException \Garden\Exception\ErrorException
     */
    public function testArrayColumnError1() {
        $r = array_column_php();
    }

    /**
     * Test array_column() errors and warnings.
     *
     * @expectedException \Garden\Exception\ErrorException
     */
    public function testArrayColumnError2() {
        $r = array_column_php('foo', 'bar');
    }

    /**
     * Test array_column() errors and warnings.
     *
     * @expectedException \Garden\Exception\ErrorException
     */
    public function testArrayColumnError3() {
        $ds = $this->getDataset();

        $r = array_column_php($ds);
    }

    /**
     * Test array_column() errors and warnings.
     *
     * @expectedException \Garden\Exception\ErrorException
     */
    public function testArrayColumnError4() {
        $ds = $this->getDataset();

        $r = array_column_php($ds, []);
    }

    /**
     * Test array_column() errors and warnings.
     *
     * @expectedException \Garden\Exception\ErrorException
     */
    public function testArrayColumnError5() {
        $ds = $this->getDataset();

        $r = array_column_php($ds, 'id', []);
    }


    /**
     * Get a sample dataset that can be used with array_column().
     *
     * @return array Returns the test dataset.
     */
    protected function getDataset() {
        return [
            ['id' => 123, 'name' => 'foo'],
            ['id' => 456, 'name' => 'Hello world', 'slug' => 'hello-world'],
            ['id' => 777, 'name' => 'Smacker', 'slug' => 'smacker']
        ];
    }

    /**
     * Get the path for a temporary file.
     *
     * @param string $ext The file extension.
     * @return string Returns the path of the temporary file.
     */
    protected function tempPath($ext) {
        do {
            $result = sys_get_temp_dir().'/'.sha1(microtime()).'.'.ltrim($ext, '.');
        } while (file_exists($result));
        return $result;
    }

    /**
     * Test {@link array_load()} and {@link array_save()}.
     *
     * @param string $ext The file extension.
     * @dataProvider provideExtensions
     */
    public function testArrayLoadSave($ext) {
        $arr = $this->getDataset();
        $path = $this->tempPath($ext);

        $saved = array_save($arr, $path);
        $this->assertTrue($saved);
        $loaded = array_load($path);
        $this->assertEquals($arr, $loaded);
    }

    /**
     * Test {@link array_load()} against a bad path.
     *
     * @param string $ext The file extension.
     * @dataProvider provideExtensions
     */
    public function testArrayLoadNoPath($ext) {
        $path = $this->tempPath($ext);

        $this->assertFalse(array_load($path));
    }

    /**
     * Test {@link array_touch()}.
     */
    public function testArrayTouch() {
        $arr = ['z', 'a' => 'b'];

        // Test touching already existing values first.
        $org = $arr;
        array_touch(0, $arr, 'c');
        $this->assertEquals($org, $arr);
        array_touch('a', $arr, 'c');
        $this->assertEquals($org, $arr);

        $mod = $arr;
        $mod['c'] = 'd';
        array_touch('c', $arr, 'd');
        $this->assertEquals($mod, $arr);
    }

    public function testArrayTranslate() {
        $arr = ['a' => 'b', 'c' => 'd', 'e' => 'f'];

        $a1 = array_translate($arr, ['a', 'b']);
        $this->assertEquals(['a' => 'b', 'b' => null], $a1);

        $a2 = array_translate($arr, ['a' => 'b', 'e' => 'f']);
        $this->assertEquals(['b' => 'b', 'f' => 'f'], $a2);
    }

    /**
     * Test val() with an array.
     */
    public function testValArray() {
        $arr = ['foo' => 'bar'];

        $this->assertEquals('bar', val('foo', $arr));
        $this->assertEquals('default', val('baz', $arr, 'default'));
        $this->assertEquals(null, val('baz', $arr));
    }

    /**
     * Test val() with an object.
     */
    public function testValObject() {
        $obj = new stdClass();
        $obj->foo = 'bar';

        $this->assertEquals('bar', val('foo', $obj));
        $this->assertEquals('default', val('baz', $obj, 'default'));
        $this->assertEquals(null, val('baz', $obj));
    }

    /**
     * Test val() with a string.
     */
    public function testValOther() {
        $arr = 'foo';

        $this->assertEquals('default', val(0, $arr, 'default'));
        $this->assertEquals(null, val(0, $arr));
    }

    /**
     * Test valr() with an array.
     */
    public function testValrArray() {
        $arr = ['foo' => 'bar', 'parent' => ['child' => 'baby']];

        $this->assertEquals('bar', valr('foo', $arr));
        $this->assertEquals('baby', valr('parent.child', $arr));
        $this->assertEquals('bar', valr(['foo'], $arr));
        $this->assertEquals('baby', valr(['parent', 'child'], $arr));

        $this->assertEquals('default', valr('baz', $arr, 'default'));
        $this->assertEquals('default', valr('parent.child.moxy', $arr, 'default'));
        $this->assertEquals(null, valr(['baz'], $arr));
        $this->assertEquals(null, valr(['parent', 'child', 'moxy'], $arr));
    }

    /**
     * Test valr() with an object.
     */
    public function testValrObject() {
        $arr = new stdClass();
        $arr->foo = 'bar';
        $arr->parent = new stdClass();
        $arr->parent->child = 'baby';

        $this->assertEquals('bar', valr('foo', $arr));
        $this->assertEquals('baby', valr('parent.child', $arr));
        $this->assertEquals('bar', valr(['foo'], $arr));
        $this->assertEquals('baby', valr(['parent', 'child'], $arr));

        $this->assertEquals('default', valr('baz', $arr, 'default'));
        $this->assertEquals('default', valr('parent.child.moxy', $arr, 'default'));
        $this->assertEquals(null, valr(['baz'], $arr));
        $this->assertEquals(null, valr(['parent', 'child', 'moxy'], $arr));
    }

    /**
     * Test valr() with an object and array mix.
     */
    public function testValrMixed() {
        $arr = new stdClass();
        $arr->foo = 'bar';
        $arr->parent = ['child' => 'baby'];

        $this->assertEquals('bar', valr('foo', $arr));
        $this->assertEquals('baby', valr('parent.child', $arr));
        $this->assertEquals('bar', valr(['foo'], $arr));
        $this->assertEquals('baby', valr(['parent', 'child'], $arr));

        $this->assertEquals('default', valr('baz', $arr, 'default'));
        $this->assertEquals('default', valr('parent.child.moxy', $arr, 'default'));
        $this->assertEquals(null, valr(['baz'], $arr));
        $this->assertEquals(null, valr(['parent', 'child', 'moxy'], $arr));
    }

    /**
     * Provide an array of file extensions for {@link testArrayLoadSave()}.
     *
     * @return array Returns an array of extensions.
     */
    public function provideExtensions() {
        $result = [
            '.json' => ['.json'],
            '.json.php' => ['.json.php'],
            '.ser' => ['.ser'],
            '.ser.php' => ['.ser.php'],
            '.php' => ['.php'],
        ];

        if (extension_loaded('yaml')) {
            $result['.yml'] = ['.yml'];
            $result['.yml.php'] = ['.yml.php'];
        }

        return $result;
    }
}
