<?php
/**
 * @author Todd Burry <todd@vanillaforums.com>
 * @copyright 2009-2014 Vanilla Forums Inc.
 * @license MIT
 */

namespace Garden\Tests\Requests;

use Garden\Request;

/**
 * Test {@link Requet} when specifically constructed.
 */
class ConstructedRequestTest extends \PHPUnit_Framework_TestCase {

    /**
     * Test to make sure some of the accessor methods on the request work.
     *
     * @param string $methodName The name of the method on the request object.
     * @dataProvider provideBasicMethods
     */
    public function testBasicMethods($methodName) {
        $request = new Request();

        $value = 'foo';

        $setResult = $request->$methodName($value);
        $this->assertInstanceOf('\Garden\Request', $setResult);
        $this->assertEquals($request, $setResult);

        $getResult = $request->$methodName();
        $this->assertRegExp("`$value`i", $getResult);
    }

    /**
     * Test the various Request->is*() methods.
     *
     * @param string $method The http method to test.
     * @dataProvider provideHttpMethods
     */
    public function testIsMethods($method) {
        $isName = 'is'.ucfirst(strtolower($method));

        $request = new Request('/', strtoupper($method));
        $result = call_user_func([$request, $isName]);
        $this->assertTrue($result);

        $request2 = new Request('/', strtolower($method));
        $result2 = call_user_func([$request2, $isName]);
        $this->assertTrue($result2);
    }

    /**
     * Test {@link Request::url()}.
     */
    public function testUrl() {
        $url = 'http://foo.bar:8080/this/path?q=123';

        $r = new Request('/', 'GET');

        $r->url($url);
        $this->assertEquals('http', $r->scheme());
        $this->assertEquals('foo.bar', $r->host());
        $this->assertEquals('8080', $r->port());
        $this->assertEquals('/this/path', $r->path());
        $this->assertEquals('123', $r->get('q'));

        $this->assertEquals($url, $r->url());
    }

    /**
     * Test the full path method.
     */
    public function testFullPath() {
        $r = new Request('/', 'GET');

        $withExt = '/foo/bar.txt';
        $r->fullPath($withExt);
        $this->assertEquals('/foo/bar', $r->path());
        $this->assertEquals('.txt', $r->ext());

        $withoutExt = '/foo/bar';
        $r->fullPath($withoutExt);
        $this->assertEquals('/foo/bar', $r->path());
        $this->assertEquals('', $r->ext());

        $doubleDot = '/foo.bar.txt';
        $r->fullPath($doubleDot);
        $this->assertEquals('/foo.bar', $r->path());
        $this->assertEquals('.txt', $r->ext());

        $r->ext('');
        $this->assertEquals('/foo.bar', $r->fullPath());
    }

    /**
     * Test {@link Request::data()}.
     */
    public function testData() {
        $r = new Request('/', 'GET', ['foo' => 'bar']);
        $this->assertEquals($r->get(), $r->data());

        $data = ['baz' => 123];
        $r->data($data);
        $this->assertEquals($data, $r->get());

        $r2 = new Request('/', 'POST', ['foo' => 'bar']);
        $this->assertEquals($r2->input(), $r2->data());
        $r2->data($data);
        $this->assertEquals($data, $r2->input());
    }

    /**
     * Test the (@link Reques::root()} method.
     */
    public function testRoot() {
        $r = new Request('http://foo.bar/moop', 'GET');
        $expected = 'http://foo.bar/vanilla/moop';

        $r->root('vanilla');
        $this->assertEquals($expected, (string)$r);

        $r->root('/vanilla');
        $this->assertEquals($expected, (string)$r);

        $r->root('/vanilla/');
        $this->assertEquals($expected, (string)$r);

        // Test setting a different url with the same root.
        $r->url('http://foo.bar/vanilla/foo');
        $this->assertEquals('/foo', $r->path());

        $r->url('http://foo.bar/vanilla');
        $this->assertEquals('/vanilla', $r->root());
        $this->assertEquals('', $r->path());

        $r->url('http://foo.bar/vanillamilla');
        $this->assertEquals('', $r->root());
    }

    /**
     * Provide some of the basic methods of the {@link Request} object.
     *
     * @return array Returns an array of method names.
     */
    public function provideBasicMethods() {
        $result = [
            'method',
            'host',
            'port',
            'ip',
            'path',
            'fullPath',
            'root',
            'ext',
            'scheme'
        ];

        return array_combine($result, array_map(function ($v) {
            return [$v];
        }, $result));
    }

    /**
     * Provide all of the http methods suitable for passing to a unit test.
     *
     * @return array Returns an array of http method names.
     */
    public function provideHttpMethods() {
        $result = [
            Request::METHOD_GET,
            Request::METHOD_HEAD,
            Request::METHOD_DELETE,
            Request::METHOD_OPTIONS,
            Request::METHOD_PATCH,
            Request::METHOD_POST,
            Request::METHOD_PUT
        ];

        return array_combine($result, array_map(function ($v) {
            return [$v];
        }, $result));
    }
}
