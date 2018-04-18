<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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
 * @covers Wedeto\HTTP\Result
 */
final class ResultTest extends TestCase
{
    public function testResultHeaders()
    {
        $result = new Result;

        $this->assertEmpty($result->getHeaders());
        $this->assertSame($result, $result->setHeader("foo-bar", "test"));

        $h = $result->getHeaders();
        $ah = $result->getAllHeaders();
        $this->assertEquals($h, $ah, "No response is available, so headers must be equal");

        $this->assertTrue(isset($h['Foo-Bar']));
        $this->assertTrue($result->hasHeader('foo-bar'));
        $this->assertEquals("test", $result->getHeader('foo-bar'));
        $this->assertEquals("test", $h['Foo-Bar']);

        $this->assertSame($result, $result->unsetHeader('foo-bar'));
        $h = $result->getHeaders();
        $this->assertFalse($result->hasHeader('foo-bar'));
        $this->assertNull($result->getHeader('foo-bar'));
        $this->assertEmpty($h);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value should be a scalar");
        $result->setHeader('foo-bar', []);
    }

    public function testResultCookies()
    {
        $result = new Result;

        $this->assertEmpty($result->getCookies());
        $this->assertNull($result->getCookie('myCookie'));
        $this->assertFalse($result->hasCookie('myCookie'));
        $this->assertFalse($result->hasCookie('mycookie'));

        $cookie = new Cookie('myCookie', 'value');
        $cookie2 = new Cookie('mycookie', 'otherValue');

        $this->assertSame($result, $result->addCookie($cookie));
        $c = $result->getCookies();
        $this->assertEquals(1, count($c));
        $this->assertTrue(isset($c['myCookie']));
        $this->assertSame($cookie, $c['myCookie']);
        $this->assertFalse(isset($c['mycookie']));
        $this->assertTrue($result->hasCookie('myCookie'));
        $this->assertFalse($result->hasCookie('mycookie'));
        $this->assertSame($cookie, $result->getCookie('myCookie'));
        $this->assertNull($result->getCookie('mycookie'));

        $this->assertSame($result, $result->addCookie($cookie2));
        $c = $result->getCookies();
        $this->assertEquals(2, count($c));
        $this->assertTrue(isset($c['myCookie']));
        $this->assertSame($cookie, $c['myCookie']);
        $this->assertSame($cookie2, $c['mycookie']);
        $this->assertTrue($result->hasCookie('myCookie'));
        $this->assertTrue($result->hasCookie('mycookie'));
        $this->assertTrue(isset($c['mycookie']));
        $this->assertSame($cookie, $result->getCookie('myCookie'));
        $this->assertSame($cookie2, $result->getCookie('mycookie'));

        $this->assertSame($result, $result->deleteCookie('myCookie'));
        $c = $result->getCookies();
        $this->assertEquals(1, count($c));
        $this->assertFalse(isset($c['myCookie']));
        $this->assertSame($cookie2, $c['mycookie']);
        $this->assertFalse($result->hasCookie('myCookie'));
        $this->assertTrue($result->hasCookie('mycookie'));
        $this->assertTrue(isset($c['mycookie']));
        $this->assertNull($result->getCookie('myCookie'));
        $this->assertSame($cookie2, $result->getCookie('mycookie'));
    }

    public function testResultWithResponse()
    {
        $response = new Response\StringResponse("foo");
        
        $result = new Result;
        $result->setHeader('foo-bar', 'yes');
        $this->assertSame($result, $result->setResponse($response));
        $this->assertSame($response, $result->getResponse());

        $h1 = $result->getHeaders();
        $this->assertEquals(1, count($h1));
        $h2 = $result->getAllHeaders();
        $this->assertEquals(1, count($h2));

        $fstr = fopen("php://memory", "rw");
        fwrite($fstr, "foo");
        fseek($fstr, 0);

        $response2 = new Response\FileHandleResponse($fstr, "foo.txt", "application/foo", true);
        $this->assertSame($result, $result->setResponse($response2));
        $this->assertSame($response2, $result->getResponse());

        $h1 = $result->getHeaders();
        $this->assertEquals(1, count($h1));
        $h2 = $result->getAllHeaders();
        $this->assertEquals(2, count($h2));

        $h3 = $response->getHeaders();
        foreach ($h3 as $k => $v)
        {
            $this->assertTrue(isset($h2[$k]));
            $this->assertEquals($v, $h2[$k]);
        }
    }

    public function testResultWithCachePolicy()
    {
        $result = new Result;

        $cp = new CachePolicy;
        $cp->setCachePolicy(CachePolicy::CACHE_PUBLIC);

        $this->assertSame($result, $result->setCachePolicy($cp));
        $this->assertSame($cp, $result->getCachePolicY());

        $resp = new Response\StringResponse("foo");
        $this->assertSame($result, $result->setResponse($resp));
        $this->assertSame($cp, $result->getCachePolicy());

        $cp2 = new CachePolicy;
        $cp2->setCachePolicy(CachePolicy::CACHE_PRIVATE);
        $resp->setCachePolicy($cp2);
        $this->assertSame($cp2, $result->getCachePolicy());

        $resp2 = new Response\StringResponse("bar");
        $this->assertSame($result, $result->setResponse($resp2));
        $this->assertSame($cp, $result->getCachePolicy());

        $cph = $cp->getHeaders();
        $rh = $result->getAllHeaders();
        foreach ($cph as $key => $value)
        {
            $this->assertTrue(isset($rh[$key]));
            $this->assertEquals($value, $rh[$key]);
        }
    }
}
