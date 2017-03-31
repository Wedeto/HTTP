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

use Wedeto\HTTP\Request;

/**
 * @covers Wedeto\HTTP\Error
 */
final class HTTPErrorTest extends TestCase
{
    /**
     * @covers Wedeto\HTTP\Error::__construct
     * @covers Wedeto\HTTP\Error::getUserMessage
     */
    public function testHTTPError()
    {
        $a = new Error(400, "Error");
        $this->assertNull($a->getUserMessage());

        $a = new Error(400, "Error", "User message");
        $this->assertEquals($a->getUserMessage(), "User message");
    }

    public function testGetMimeTypes()
    {
        $a = new Error(500, "Error");
        $actual = $a->getMimeTypes();

        $this->assertContains('text/plain', $actual);
    }

    public function testOutput()
    {
        $request = Request::createFromGlobals();
        $a = new Error(500, 'Internal Server Error');
        
        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected_start = "Wedeto\HTTP\Response\Error: Internal Server Error";
        $this->assertEquals(0, strpos($expected_start, $actual));
    }
}
