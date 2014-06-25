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

        $r2 = new Request('http://localhost.com/foo.json?bar=baz');
        $this->assertEquals('.json', $r2->ext());
        $this->assertEquals('/foo', $r2->path());
        $this->assertEquals(['bar' => 'baz'], $r2->query());
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
     * Test {@link Request::makeUrl()}.
     */
    public function testMakeUrl() {
        $r = new Request('https://google.com:8080/v1/something');

        $url = $r->makeUrl('/foo', true);
        $this->assertEquals('https://google.com:8080/foo', $url);

        $url = $r->makeUrl('/foo', false);
        $this->assertEquals('/foo', $url);

        $url = $r->makeUrl('/foo', '//');
        $this->assertEquals('//google.com:8080/foo', $url);

        $url = $r->makeUrl('/foo', '/');
        $this->assertEquals('/foo', $url);

        $url = $r->makeUrl('/foo', 'http');
        $this->assertEquals('http://google.com:8080/foo', $url);

        $r->scheme('http');
        $url = $r->makeUrl('/foo', 'https');
        $this->assertEquals('https://google.com:8080/foo', $url);

        // Start some tests with a different root.
        $r->root('v1');
        $url = $r->makeUrl('/foo', true);
        $this->assertEquals('http://google.com:8080/v1/foo', $url);

        $url = $r->makeUrl('/foo', false);
        $this->assertEquals('/v1/foo', $url);

        $url = $r->makeUrl('/foo', '//');
        $this->assertEquals('//google.com:8080/v1/foo', $url);

        $url = $r->makeUrl('/foo', '/');
        $this->assertEquals('/foo', $url);

        $r->scheme('https');
        $url = $r->makeUrl('/foo', 'http');
        $this->assertEquals('http://google.com:8080/v1/foo', $url);

        $r->scheme('http');
        $url = $r->makeUrl('/foo', 'https');
        $this->assertEquals('https://google.com:8080/v1/foo', $url);
    }

    /**
     * Test getting and setting with {@link Request::input()} and {@link Request::query()}.
     */
    public function testInputAndQuery() {
        $r = new Request('http://localhost/foo.txt');

        $input = ['foo' => 'bar'];
        $r->input($input);
        $this->assertEquals($input, $r->input());
        $this->assertEquals('bar', $r->input('foo'));
        $this->assertEquals('baz', $r->input('hello', 'baz'));

        $r = new Request('http://localhost/foo.txt');

        $query = ['foo' => 'bar'];
        $r->input($query);
        $this->assertEquals($query, $r->input());
        $this->assertEquals('bar', $r->input('foo'));
        $this->assertEquals('baz', $r->input('hello', 'baz'));
    }

    /**
     * Test {@link Request::input()} with a bad argument.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testBadInput() {
        $r = new Request('http://localhost/foo.txt');
        $foo = $r->input(true);
    }

    /**
     * Test {@link Request::query()} with a bad argument.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testBadQuery() {
        $r = new Request('http://localhost/foo.txt');
        $foo = $r->query(true);
    }

    public function testJsonSerialize() {
        $r = new Request('http://localhost/foo.json?help=1', 'post', ['foo' => 'bar']);
        $json = $r->jsonSerialize();

        $r2 = new Request('http://foo.com');
        $r2->env($json);

        $this->assertEquals($json, $r2->jsonSerialize());
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
     * Test setting a standard port to flip the scheme.
     */
    public function testPorts() {
        $r = new Request('http://localhost/foo.txt');

        $r->port(443);
        $this->assertEquals('https', $r->scheme());

        $r->port(80);
        $this->assertEquals('http', $r->scheme());
    }

    /**
     * Test that the http can be overridden with the x-method qs paramter.
     *
     * @param string $method The http method to test.
     * @dataProvider provideHttpMethods
     */
    public function testMethodOverriding($method) {
        // Test on a get request.
        $r = new Request('http://localhost.com?x-method='.strtolower($method), 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            $this->assertEquals($method, $r->method());
        } else {
            $this->assertEquals(Request::METHOD_GET, $r->method());
            $this->assertTrue($r->env('X_METHOD_BLOCKED'));
        }
        $this->assertNull($r->query('x-method'));

        // The post method can be made into anything.
        $r2 = new Request('http://localhost.com?x-method='.strtolower($method), 'POST');
        $this->assertEquals($method, $r2->method());
        $this->assertNull($r2->query('x-method'));

        // Check the backup.
        if ($method !== 'POST') {
            $this->assertEquals('POST', $r2->env('REQUEST_METHOD_RAW'));
        }
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
