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
use Wedeto\Platform\System;

/**
 * @covers Wedeto\HTTP\RedirectRequest
 */
final class RedirectRequestTest extends TestCase
{
    public function testNormalRedirect()
    {
        $expected = new URL('http://foo.bar');
        $a = new RedirectRequest($expected);

        $this->assertEquals(302, $a->getStatusCode());
        $this->assertEquals($expected, $a->getURL());

        $headers = $a->getHeaders();
        $this->assertTrue(isset($headers['Location']));
        $this->assertFalse(isset($headers['Refresh']));
        $this->assertEquals($expected->toString(), $headers['Location']);
    }

    public function testPermanentRedirect()
    {
        $expected = new URL('http://foo.bar');
        $a = new RedirectRequest($expected, 308);

        $this->assertEquals(308, $a->getStatusCode());
        $this->assertEquals($expected, $a->getURL());

        $headers = $a->getHeaders();
        $this->assertTrue(isset($headers['Location']));
        $this->assertFalse(isset($headers['Refresh']));
        $this->assertEquals($expected->toString(), $headers['Location']);
    }

    public function testRedirectWithTimeout()
    {
        $expected = new URL('http://foo.bar');
        $a = new RedirectRequest($expected, 302, 5);

        $this->assertEquals(302, $a->getStatusCode());
        $this->assertEquals($expected, $a->getURL());

        $headers = $a->getHeaders();
        $this->assertFalse(isset($headers['Location']));
        $this->assertTrue(isset($headers['Refresh']));
        $expected = '5; url=' . $expected->toString();
        $this->assertEquals($expected, $headers['Refresh']);
    }

    public function testInvalidRedirectionCode()
    {
        $expected = new URL('http://foo.bar');
        $this->expectException(\InvalidArgumentException::class);
        $a = new RedirectRequest($expected, 200, 5);
    }

    public function testOutputShouldBeEmpty()
    {
        $expected = new URL('http://foo.bar');
        $a = new RedirectRequest($expected);
        
        ob_start();
        $a->output('text/html');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEmpty($actual);
    }

    public function testOutputCanBeNonEmptyWithChainedResponse()
    {
        $a = new StringResponse('foobar', 'text/html');
        $expected = new URL('http://foo.bar');
        $a = new RedirectRequest($expected, 302, 0, $a);
        
        ob_start();
        $a->output('text/html');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('foobar', $actual);
    }
}
