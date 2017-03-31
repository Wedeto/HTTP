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

namespace Wedeto\HTTP\Response;

use PHPUnit\Framework\TestCase;

/**
 * @covers Wedeto\HTTP\StringResponse
 */
final class StringResponseTest extends TestCase
{
    public function testDefaultToHtml()
    {
        $a = new StringResponse("foo");

        $this->assertEquals("foo", $a->getOutput('text/html'));
    }

    public function testMultipleResponses()
    {
        $a = new StringResponse("foo");

        $a->setOutput('bar', 'text/plain');
        $a->setOutput('baz', 'application/json');

        $this->assertEquals("foo", $a->getOutput('text/html'));
        $this->assertEquals("bar", $a->getOutput('text/plain'));
        $this->assertEquals("baz", $a->getOutput('application/json'));
    }

    public function testHandleUnknownResponse()
    {
        $a = new StringResponse("foo");

        $this->assertNull($a->getOutput('text/plain'));
    }

    public function testAppendToResponse()
    {
        $a = new StringResponse("foo");
        $a->append('bar');

        $this->assertEquals('foobar', $a->getOutput('text/html'));
        $this->assertNull($a->getOutput('text/plain'));
    }

    public function testClosureAsResponse()
    {
        $a = new StringResponse(function () { return "foo"; });
        $a->append('bar');

        $this->assertEquals('foobar', $a->getOutput('text/html'));
        $this->assertNull($a->getOutput('text/plain'));
    }

    public function testObjectAsResponse()
    {
        $a = new StringResponse(new MockValidStringResponseObject());
        $this->assertEquals('foo', $a->getOutput('text/html'));

        $this->expectException(\InvalidArgumentException::class);
        $a = new StringResponse(new MockInvalidStringResponseObject());
    }

    public function testActualOutput()
    {
        $a = new StringResponse("foobar", 'text/html');

        ob_start();
        $a->output('text/html');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('foobar', $actual);
    }

    public function testActualOutputWithUnknownMime()
    {
        $a = new StringResponse("foobar", 'text/html');

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('Unknown mime type requested', $actual);
    }
}

class MockValidStringResponseObject
{
    public function __toString()
    {
        return "foo";
    }
}

class MockInvalidStringResponseObject
{
    public function toString()
    {
        return "foo";
    }
}
