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

/**
 * @covers WASP\Http\ResponseTypes
 */
final class ResponseTypesTest extends TestCase
{
    public function testExtensions()
    {
        $types = ResponseTypes::$TYPES;
        $mime_seen = array();
        foreach ($types as $type => $mime)
        {
            $fn = "foo/bar." . $type;
            $data = ResponseTypes::extractFromPath($fn);
            $this->assertEquals($mime, $data[0]);
            $this->assertEquals('.' . $type, $data[1]);

            $actual = ResponseTypes::getFromFile($fn);
            $this->assertEquals($mime, $actual);

            if (!isset($mime_seen[$mime]))
            {
                // Multiple extension may map to the same mime-type, and
                // the first is always returned by getExtension
                $actual = ResponseTypes::getExtension($mime);
                $this->assertEquals($type, $actual);
                $mime_seen[$mime] = true;
            }
        }

        $fn = 'foo/bar';
        $data = ResponseTypes::extractFromPath($fn);
        $this->assertNull($data[0]);
        $this->assertNull($data[1]);

        $ext = '.foobar';
        $actual = ResponseTypes::getMimeFromExtension($ext);
        $this->assertNull($actual);

        $ptt = array('text/plain', 'text/html', 'text/css', 'text/csv', 'application/json', 'application/xml', 'text/plain; charset=utf-8');
        foreach ($ptt as $pt)
            $this->assertTrue(ResponseTypes::isPlainText($pt));

        $this->assertFalse(ResponseTypes::isPlainText($ext));
        $this->assertFalse(ResponseTypes::isPlainText($ext . '; charset=utf-8'));
    }
}
