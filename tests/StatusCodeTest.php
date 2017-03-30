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
 * @covers Wedeto\HTTP\StatusCode
 */
final class StatusCodeTest extends TestCase
{
    public function testExistingCodes()
    {
        $this->assertEquals('OK', StatusCode::description(200));
        $this->assertEquals('Moved Permanently', StatusCode::description(301));
        $this->assertEquals('Found', StatusCode::description(302));
        $this->assertEquals('See Other', StatusCode::description(303));
        $this->assertEquals('Permanent Redirect', StatusCode::description(308));
        $this->assertEquals('Bad Request', StatusCode::description(400));
        $this->assertEquals('Unauthorized', StatusCode::description(401));
        $this->assertEquals('Forbidden', StatusCode::description(403));
        $this->assertEquals('Not Found', StatusCode::description(404));
        $this->assertEquals('I\'m a teapot', StatusCode::description(418));
        $this->assertEquals('Internal Server Error', StatusCode::description(500));
    }

    public function testUnknownCodes()
    {
        $this->assertEquals('Unknown Error', StatusCode::description(299));
        $this->assertEquals('Unknown Error', StatusCode::description(399));
        $this->assertEquals('Unknown Error', StatusCode::description(599));
        $this->assertEquals('Unknown Error', StatusCode::description(1234));
    }
}
