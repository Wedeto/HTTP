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
 * @covers Wedeto\HTTP\Accept
 */
final class AcceptTest extends TestCase
{
    public function testAcceptParser()
    {
        $accept = new Accept('');
        $this->assertEquals($accept->getAccepted(), array('text/html' => 1.0, '*/*' => 0.9));

        $accept = Accept::parseAccept("garbage", false);
        $this->assertEquals($accept, array("garbage" => 1.0));
    }

    public function testGetBestResponseType()
    {
        $accept = new Accept('text/html;q=0.9,text/plain;q=0.9,application/*;q=0.7');

        $this->assertTrue($accept->accepts("text/html") == true);
        $this->assertTrue($accept->accepts("text/plain") == true);
        $this->assertTrue($accept->accepts("application/bar") == true);
        $this->assertFalse($accept->accepts("foo/bar") == true);

        $resp = $accept->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals('text/plain', $resp);

        $resp = $accept->getBestResponseType(array('foo/bar', 'application/bar/', 'text/html', 'text/plain'));
        $this->assertEquals('text/html', $resp);

        $resp = $accept->getBestResponseType(array('application/bar', 'application/foo'));
        $this->assertEquals("application/bar", $resp);

        $resp = $accept->getBestResponseType(array('application/foo', 'application/bar'));
        $this->assertEquals("application/foo", $resp);

        $accept = new Accept('');
        $resp = $accept->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals("text/html", $resp);

        $this->assertEmpty($accept->getBestResponseType(array()));
    }

    public function testAcceptsAndChooseResponse()
    {
        $accept = new Accept('text/html;q=1,text/plain;q=0.9');
        $this->assertEquals(0, $accept->JSON());
        $this->assertEquals(1, $accept->HTML());
        $this->assertEquals(0.9, $accept->Text());
        $this->assertEquals(0, $accept->XML());
        $this->assertEquals('text/html,text/plain;q=0.9', (string)$accept);

        $accept = new Accept('application/json;q=1,application/*;q=0.9');
        $this->assertEquals(1, $accept->JSON());
        $this->assertEquals(0, $accept->HTML());
        $this->assertEquals(0, $accept->Text());
        $this->assertEquals(0.9, $accept->XML());
        $this->assertEquals('application/json,application/*;q=0.9', (string)$accept);

        $accept = new Accept('application/json;q=1,text/html;q=0.9,text/plain;q=0.8');
        $response = $accept->chooseResponse(array('application/json' => 'json', 'text/html' => 'html'));
        $this->assertEquals('json', $response);

        $response = $accept->chooseResponse(array('text/plain' => 'plain', 'text/html' => 'html'));
        $this->assertEquals('html', $response);

        $this->assertNull($accept->chooseResponse(array()));
    }

    public function testEmptyAcceptHeaderAcceptsAll()
    {
        $accept = new Accept('');
        $this->assertEquals(1.0, $accept->HTML());
        $this->assertEquals(0.9, $accept->JSON());
        $this->assertEquals(0.9, $accept->CSS());
        $this->assertEquals(0.9, $accept->XML());
        $this->assertEquals(0.9, $accept->accepts('application/pdf'));
    }

    public function testInvalidType()
    {
        $accept = new Accept('');
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Invalid response type: FOO');
        $accept->foo();
    }

    public function testAcceptLanguages()
    {
        $accept = new Accept('nl,en-US;q=0.7,nl_nl;q=0.6,en;q=0.5');

        $this->assertEquals(1.0, $accept->accepts('nl'));
        $this->assertEquals(0.7, $accept->accepts('en-US'));
        $this->assertEquals(0.6, $accept->accepts('nl_NL'));
        $this->assertEquals(0.5, $accept->accepts('en'));
    }

    public function testAcceptedTypes()
    {
        $cl = new \ReflectionClass(Accept::class);
        
        $constants = $cl->getConstants();
        foreach ($constants as $const => $val)
        {
            $should_throw = substr($const, 0, 7) !== 'ACCEPT_';
            $thrown = false;
            try
            {
                $a = new Accept('foo', $val); 
            }
            catch (\InvalidArgumentException $e)
            {
                $this->assertContains('Invalid accept header', $e->getMessage());
                $thrown = true;
            }
            $this->assertEquals($should_throw, $thrown);
        }

        $this->assertTrue(isset($constants['ACCEPT_MIME']));
        $this->assertTrue(isset($constants['ACCEPT_LANGUAGE']));
    }
}
