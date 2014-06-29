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

        $getter = 'get'.ucfirst($methodName);
        $setter = 'set'.ucfirst($methodName);
        $value = 'foo';

        $setResult = $request->$setter($value);
        $this->assertInstanceOf('\Garden\Request', $setResult);
        $this->assertEquals($request, $setResult);

        $getResult = (string)$request->$getter();
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

        $r->setUrl($url);
        $this->assertEquals('http', $r->getScheme());
        $this->assertEquals('foo.bar', $r->getHost());
        $this->assertEquals('8080', $r->getPort());
        $this->assertEquals('/this/path', $r->getPath());
        $this->assertEquals('123', $r->getQuery('q'));

        $this->assertEquals($url, $r->getUrl());
    }

    /**
     * Test the full path method.
     */
    public function testFullPath() {
        $r = new Request('/', 'GET');

        $withExt = '/foo/bar.txt';
        $r->setFullPath($withExt);
        $this->assertEquals('/foo/bar', $r->getPath());
        $this->assertEquals('.txt', $r->getExt());

        $withoutExt = '/foo/bar';
        $r->setFullPath($withoutExt);
        $this->assertEquals('/foo/bar', $r->getPath());
        $this->assertEquals('', $r->getExt());

        $doubleDot = '/foo.bar.txt';
        $r->setFullPath($doubleDot);
        $this->assertEquals('/foo.bar', $r->getPath());
        $this->assertEquals('.txt', $r->getExt());

        $r->setExt('');
        $this->assertEquals('/foo.bar', $r->getFullPath());

        $r2 = new Request('http://localhost.com/foo.json?bar=baz');
        $this->assertEquals('.json', $r2->getExt());
        $this->assertEquals('/foo', $r2->getPath());
        $this->assertEquals(['bar' => 'baz'], $r2->getQuery());
    }

    /**
     * Test {@link Request::data()}.
     */
    public function testData() {
        $r = new Request('/', 'GET', ['foo' => 'bar']);
        $this->assertEquals($r->getQuery(), $r->getData());

        $data = ['baz' => 123];
        $r->setData($data);
        $this->assertEquals($data, $r->getQuery());

        $r2 = new Request('/', 'POST', ['foo' => 'bar']);
        $this->assertEquals($r2->getInput(), $r2->getData());
        $r2->setData($data);
        $this->assertEquals($data, $r2->getInput());
    }

    /**
     * Test the (@link Reques::root()} method.
     */
    public function testRoot() {
        $r = new Request('http://foo.bar/moop', 'GET');
        $expected = 'http://foo.bar/vanilla/moop';

        $r->setRoot('vanilla');
        $this->assertEquals($expected, (string)$r);

        $r->setRoot('/vanilla');
        $this->assertEquals($expected, (string)$r);

        $r->setRoot('/vanilla/');
        $this->assertEquals($expected, (string)$r);

        // Test setting a different url with the same root.
        $r->setUrl('http://foo.bar/vanilla/foo');
        $this->assertEquals('/foo', $r->getPath());

        $r->setUrl('http://foo.bar/vanilla');
        $this->assertEquals('/vanilla', $r->getRoot());
        $this->assertEquals('', $r->getPath());

        $r->setUrl('http://foo.bar/vanillamilla');
        $this->assertEquals('', $r->getRoot());
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

        $r->setScheme('http');
        $url = $r->makeUrl('/foo', 'https');
        $this->assertEquals('https://google.com:8080/foo', $url);

        // Start some tests with a different root.
        $r->setRoot('v1');
        $url = $r->makeUrl('/foo', true);
        $this->assertEquals('http://google.com:8080/v1/foo', $url);

        $url = $r->makeUrl('/foo', false);
        $this->assertEquals('/v1/foo', $url);

        $url = $r->makeUrl('/foo', '//');
        $this->assertEquals('//google.com:8080/v1/foo', $url);

        $url = $r->makeUrl('/foo', '/');
        $this->assertEquals('/foo', $url);

        $r->setScheme('https');
        $url = $r->makeUrl('/foo', 'http');
        $this->assertEquals('http://google.com:8080/v1/foo', $url);

        $r->setScheme('http');
        $url = $r->makeUrl('/foo', 'https');
        $this->assertEquals('https://google.com:8080/v1/foo', $url);
    }

    /**
     * Test getting and setting with {@link Request::input()} and {@link Request::query()}.
     */
    public function testInputAndQuery() {
        $r = new Request('http://localhost/foo.txt');

        $input = ['foo' => 'bar'];
        $r->setInput($input);
        $this->assertEquals($input, $r->getInput());
        $this->assertEquals('bar', $r->getInput('foo'));
        $this->assertEquals('baz', $r->getInput('hello', 'baz'));

        $r = new Request('http://localhost/foo.txt');

        $query = ['foo' => 'bar'];
        $r->setInput($query);
        $this->assertEquals($query, $r->getInput());
        $this->assertEquals('bar', $r->getInput('foo'));
        $this->assertEquals('baz', $r->getInput('hello', 'baz'));
    }

    /**
     * Test {@link Request::input()} with a bad argument.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testBadInput() {
        $r = new Request('http://localhost/foo.txt');
        $foo = $r->setInput(true);
    }

    /**
     * Test {@link Request::query()} with a bad argument.
     *
     * @expectedException \InvalidArgumentException
     */
    public function testBadQuery() {
        $r = new Request('http://localhost/foo.txt');
        $foo = $r->setQuery(true);
    }

    /**
     * Test that a request constructed from json serialized data is the same as its json serialize output.
     */
    public function testJsonSerialize() {
        $r = new Request('http://localhost/foo.json?help=1', 'post', ['foo' => 'bar']);
        $json = $r->jsonSerialize();

        $r2 = new Request('http://foo.com');
        $r2->setEnv($json);

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

        $r->setPort(443);
        $this->assertEquals('https', $r->getScheme());

        $r->setPort(80);
        $this->assertEquals('http', $r->getScheme());
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
            $this->assertEquals($method, $r->getMethod());
        } else {
            $this->assertEquals(Request::METHOD_GET, $r->getMethod());
            $this->assertTrue($r->getEnv('X_METHOD_BLOCKED'));
        }
        $this->assertNull($r->getQuery('x-method'));

        // The post method can be made into anything.
        $r2 = new Request('http://localhost.com?x-method='.strtolower($method), 'POST');
        $this->assertEquals($method, $r2->getMethod());
        $this->assertNull($r2->getQuery('x-method'));

        // Check the backup.
        if ($method !== 'POST') {
            $this->assertEquals('POST', $r2->getEnv('REQUEST_METHOD_RAW'));
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
