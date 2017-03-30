<?php
/*
This is part of WASP, the Web Application Software Platform.
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

namespace WASP\Http;

use PHPUnit\Framework\TestCase;
use DateTime;
use DateInterval;

/**
 * @covers WASP\Http\Cookie
 */
final class CookieTest extends TestCase
{
    public function testCookieGettersAndSetters()
    {
        $a = new Cookie('foo', 'bar');

        $this->assertEquals('foo', $a->getName());
        $this->assertEquals('bar', $a->getValue());

        $a->setName('bar');
        $a->setValue('baz');

        $this->assertEquals('bar', $a->getName());
        $this->assertEquals('baz', $a->getValue());

        $a->setHttpOnly(true);
        $this->assertTrue($a->getHttpOnly());

        $a->setHttpOnly(false);
        $this->assertFalse($a->getHttpOnly());

        $a->setSecure(false);
        $this->assertFalse($a->getSecure());

        $a->setSecure(true);
        $this->assertTrue($a->getSecure());

        $a->setDomain('example.com');
        $this->assertEquals('example.com', $a->getDomain());

        $a->setPath('/bar');
        $this->assertEquals('/bar', $a->getPath());
        
        $expected = new URL('http://example.com/bar');
        $actual = $a->getURL();
        $this->assertEquals($expected->host, $actual->host);
        $this->assertEquals($expected->path, $actual->path);
        
        $url = new URL('http://foo.bar/baz');
        $a->setURL($url);
        $this->assertEquals('/baz', $a->getPath());
        $this->assertEquals('foo.bar', $a->getDomain());
        $this->assertFalse($a->getSecure());

        $url = new URL('https://example.de/foobar');
        $a->setURL($url);
        $this->assertEquals('/foobar', $a->getPath());
        $this->assertEquals('example.de', $a->getDomain());
        $this->assertTrue($a->getSecure());

        $di = new DateInterval('P5D');
        $now = new DateTime;
        $now->add($di);
        $expected = $now->getTimestamp();
        $a->setExpiresIn($di);
        $actual = $a->getExpires();

        $this->assertEquals($expected, $actual, 1.0);

        $di = new DateInterval('P10D');
        $now = new DateTime;
        $now->add($di);
        $a->setExpires($now);
            
        $expected = $now->getTimestamp();
        $actual = $a->getExpires();
        $this->assertEquals($expected, $actual);

        $a->setExpiresNow();
        $expected = time();
        $this->assertLessThan($expected, $a->getExpires());

    }
}
