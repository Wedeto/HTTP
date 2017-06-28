<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Wedeto\HTTP;

use PHPUnit\Framework\TestCase;

/**
 * @covers Wedeto\HTTP\URL
 */
final class URLTest extends TestCase
{
    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::parse
     * @covers Wedeto\HTTP\URL::__get
     */
    public function testURL()
    {
        $url = new URL('http://example.com');
        $this->assertEquals('http', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(80, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('http://example.com/');
        $this->assertEquals('http', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(80, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('https://example.com/');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('https://example.com/?foo=bar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals('foo=bar', $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('https://example.com/#foobar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals('foobar', $url->fragment);

        $url = new URL('https://example.com/?foo=bar#foobar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals('foo=bar', $url->query);
        $this->assertEquals('foobar', $url->fragment);

        $url = new URL('https://example.com/index?foo=bar#foobar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/index', $url->path);
        $this->assertEquals('foo=bar', $url->query);
        $this->assertEquals('foobar', $url->fragment);

        $url = new URL('https://example.com/index.php/my/route/?foo=bar#foobar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/index.php/my/route/', $url->path);
        $this->assertEquals('foo=bar', $url->query);
        $this->assertEquals('foobar', $url->fragment);

        $url = new URL('https://example.com:443/index.php/my/route/?foo=bar#foobar');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/index.php/my/route/', $url->path);
        $this->assertEquals('foo=bar', $url->query);
        $this->assertEquals('foobar', $url->fragment);

        $url = new URL('https://example.com:443/');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals(443, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('https://www.example.com:440/');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals(null, $url->username);
        $this->assertEquals(null, $url->password);
        $this->assertEquals('www.example.com', $url->host);
        $this->assertEquals(440, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);

        $url = new URL('https://foo:bar@www.example.com:440/');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals('foo', $url->username);
        $this->assertEquals('bar', $url->password);
        $this->assertEquals('www.example.com', $url->host);
        $this->assertEquals(440, $url->port);
        $this->assertEquals('/', $url->path);
        $this->assertEquals(null, $url->query);
        $this->assertEquals(null, $url->fragment);
    }
    
    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::__set
     * @covers Wedeto\HTTP\URL::__get
     * @covers Wedeto\HTTP\URL::__toString
     * @covers Wedeto\HTTP\URL::toString
     * @covers Wedeto\HTTP\URL::offsetGet
     * @covers Wedeto\HTTP\URL::offsetSet
     * @covers Wedeto\HTTP\URL::offsetExists
     * @covers Wedeto\HTTP\URL::offsetUnset
     */
    public function testConstruct()
    {
        $url = new URL();
        $this->assertNull($url->scheme);
        $this->assertNull($url->username);
        $this->assertNull($url->password);
        $this->assertNull($url->host);
        $this->assertNull($url->path);
        $this->assertNull($url->query);
        $this->assertNull($url->fragment);
        $this->assertEquals('', (string)$url);

        $url->scheme = 'http';
        $url->host = 'example2.co.uk';
        $this->assertEquals('http://example2.co.uk', $url->__toString());

        $url->path = '/test';
        $this->assertEquals('http://example2.co.uk/test', $url->__toString());

        $url->port = 80;
        $this->assertEquals('http://example2.co.uk/test', $url->__toString());

        $url->port = 81;
        $this->assertEquals('http://example2.co.uk:81/test', $url->__toString());

        $url->port = 443;
        $this->assertEquals('http://example2.co.uk:443/test', $url->__toString());

        $url->scheme = 'https';
        $this->assertEquals('https://example2.co.uk/test', $url->__toString());

        $url->scheme = 'ftp';
        $this->assertEquals('ftp://example2.co.uk:443/test', $url->__toString());

        $url->port = 21;
        $this->assertEquals('ftp://example2.co.uk/test', $url->__toString());

        $url->username = 'foo';
        $url->password = 'bar';
        $this->assertEquals('ftp://foo:bar@example2.co.uk/test', $url->__toString());

        $this->assertEquals($url['scheme'], 'ftp');
        $this->assertEquals($url['username'], 'foo');

        $url['scheme'] = 'http';
        unset($url['port']);

        $this->assertEquals(80, $url['port']);

        $this->assertFalse(isset($url['foo']));
        $this->assertTrue(isset($url['host']));

        $url->setHost('example.com:81');
        $this->assertEquals($url->port, 81);
    }
    
    /**
     * @covers Wedeto\HTTP\URL::__get
     */
    public function testGetException()
    {
        $url = new URL();
        $this->expectException(\OutOfRangeException::class);
        $url->foo;
    }

    /**
     * @covers Wedeto\HTTP\URL::__set
     */
    public function testSetException()
    {
        $url = new URL();
        $this->expectException(\OutOfRangeException::class);
        $url->foo = 3;;
    }

    /**
     * @covers Wedeto\HTTP\URL::offsetGet
     */
    public function testOffsetGetException()
    {
        $url = new URL();
        $this->expectException(\OutOfRangeException::class);
        $url['foo'];
    }

    /**
     * @covers Wedeto\HTTP\URL::offsetSet
     */
    public function testOffsetSetException()
    {
        $url = new URL();
        $this->expectException(\OutOfRangeException::class);
        $url['foo'] = 3;
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::__get
     * @covers Wedeto\HTTP\URL::get
     */
    public function testDefaultScheme()
    {
        $url = new URL('example.com/index', 'http');
        $this->assertEquals((string)$url, 'http://example.com/index');

        $url = new URL('example.com/index', 'https');
        $this->assertEquals((string)$url, 'https://example.com/index');
        $this->assertTrue($url->secure, new URL('example.com/index', 'https'));

        $url = new URL('https://example.com/index', 'http');
        $this->assertEquals((string)$url, 'https://example.com/index');

        $url = new URL('http://example.com/index', 'https');
        $this->assertEquals((string)$url, 'http://example.com/index');

        $backup = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : null;
        $_SERVER['REQUEST_SCHEME'] = null;

        $url = new URL('example.com/foo/bar');
        $this->assertEquals('example.com', $url->host);
        $this->assertEquals('/foo/bar', $url->path);
        $this->assertEquals('http', $url->scheme);

        $_SERVER['REQUEST_SCHEME'] = $backup;
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     */
    public function testCopyConstruct()
    {
        $url = new URL('http://a:b@example.com:73/test?query#fragment');

        $url2 = new URL($url);
        $this->assertEquals((string)$url, (string)$url2);
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::parse
     */
    public function testUnsupported()
    {
        $this->expectException(URLException::class);
        $url = new URL('mailto:test@example.com');
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::parse
     */
    public function testUnsupportNonExistingScheme()
    {
        $this->expectException(URLException::class);
        $url = new URL('random-garbage:example^@!foobar');
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::parse
     */
    public function testInvalidUrl()
    {
        $this->expectException(URLException::class);
        $url = new URL('http:///example.com');
    }


    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::parse
     */
    public function testPathCannotBeEmpty()
    {
        $url = new URL('http://example.foo');
        $this->assertEquals('/', $url->path);

        $url->setPath('');
        $this->assertEquals('/', $url->path);
    }

    public function testDefaultFtpPort()
    {
        $url = new URL('ftp://example.foo');
        $this->assertEquals(21, $url->port);
    }

    /**
     * @covers Wedeto\HTTP\URL::__construct
     * @covers Wedeto\HTTP\URL::toString
     */
    public function testIDN()
    {
        $url = new URL('http://crême.fr');

        $str1 = $url->toString(false);
        $str2 = $url->toString(true);
        $this->assertEquals($str1, 'http://crême.fr/');
        $this->assertEquals($str2, 'http://xn--crme-hpa.fr/');

        $url = new URL('http://xn--crme-hpa.fr');
        $str1 = $url->toString(false);
        $str2 = $url->toString(true);
        $this->assertEquals($str1, 'http://crême.fr/');
        $this->assertEquals($str2, 'http://xn--crme-hpa.fr/');
    }

    public function testSameScheme()
    {
        $backup = isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : null;
        $_SERVER['REQUEST_SCHEME'] = 'http';

        $url = (new URL('//example.com/foo/bar'))->toString();
        $this->assertEquals('http://example.com/foo/bar', $url);

        $_SERVER['REQUEST_SCHEME'] = 'https';
        $url = (new URL('//example.com/foo/bar'))->toString();
        $this->assertEquals('https://example.com/foo/bar', $url);
        $_SERVER['REQUEST_SCHEME'] = $backup;
    }

    public function testSuffix()
    {
        $url = new URL('https://example.com/foo/bar.xml');
        $this->assertEquals('.xml', $url->get('suffix'));

        $url = new URL('https://example.com/foo/bar');
        $this->assertNull($url->get('suffix'));

        $url = new URL('bar');
        $this->assertNull($url->get('suffix'));
    }

    public function testQueryParser()
    {
        $url = new URL('https://example.com/foo?bar=5');

        $this->assertEquals('5', $url->getQueryVariable('bar'));
        $this->assertEquals(null, $url->getQueryVariable('foo'));

        $this->assertSame($url, $url->setQueryVariable('foo', 'baz'));
        $this->assertEquals('baz', $url->getQueryVariable('foo'));

        $this->assertSame($url, $url->set('query', '?a=1&b=2&amp;3=4'));
        $this->assertEquals('1', $url->getQueryVariable('a'));
        $this->assertEquals('2', $url->getQueryVariable('b'));
        $this->assertEquals('4', $url->getQueryVariable('3'));

        $this->assertEquals('https://example.com/foo?a=1&b=2&3=4', $url->toString());

        $this->assertSame($url, $url->setQueryVariable('foobar', ''));
        $this->assertEquals('https://example.com/foo?a=1&b=2&3=4', $url->toString());

        $this->assertSame($url, $url->set('query', ['a' => 3, 'b' => 4]));
        $this->assertEquals('3', $url->getQueryVariable('a'));
        $this->assertEquals('4', $url->getQueryVariable('b'));
        $this->assertEquals('https://example.com/foo?a=3&b=4', $url->toString());

        $this->assertSame($url, $url->set('query', ['a' => 3, 'b' => '']));
        $this->assertEquals('3', $url->getQueryVariable('a'));
        $this->assertEquals(null, $url->getQueryVariable('b'));
        $this->assertEquals('https://example.com/foo?a=3', $url->toString());

        $this->assertSame($url, $url->set('query', '?a&b&c'));
        $this->assertEquals('https://example.com/foo?a=1&b=1&c=1', $url->toString());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query must be associative array or string');
        $url->set('query', new \DateTime);
    }

    public function testChangingURLDoesNotChangeContentsWhenNoQuerySet()
    {
        $url = new URL('http://example.com/foo');
        $this->assertEquals(null, $url->query);

        $url2 = new URL($url);
        $this->assertEquals($url, $url2);

        $url2->setPath('foo');
        $this->assertEquals($url, $url2);
    }

    public function testURLAcceptsUpperCasedSchemes()
    {
        $url = new URL('HTTP://FOO.COM');
        $this->assertEquals('http', $url->scheme);
        $this->assertEquals('foo.com', $url->host);

        $url = new URL('HTTPS://FOO.COM');
        $this->assertEquals('https', $url->scheme);
        $this->assertEquals('foo.com', $url->host);
    }
}
