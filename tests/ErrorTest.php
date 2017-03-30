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
use WASP\Template;
use WASP\System;

/**
 * @covers WASP\Http\Error
 */
final class HttpErrorTest extends TestCase
{
    public function tearDown()
    {
        System::getInstance()->request = null;
    }

    /**
     * @covers WASP\Http\Error::__construct
     * @covers WASP\Http\Error::getUserMessage
     */
    public function testHttpError()
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

        $this->assertContains('application/json', $actual);
        $this->assertContains('application/xml', $actual);
        $this->assertContains('text/html', $actual);
        $this->assertContains('text/plain', $actual);
    }

    public function testTransformReponse()
    {
        $a = new Error(500, "Error");

        $request = System::request();
        $tpl = new MockErrorTemplate($request);
        $tpl->throw_other = false;
        $request->setTemplate($tpl);

        $response = $a->transformResponse('application/json');
        $this->assertInstanceOf(DataResponse::class, $response);

        $response = $a->transformResponse('application/xml');
        $this->assertInstanceOf(DataResponse::class, $response);

        $response = $a->transformResponse('text/html');
        $this->assertInstanceOf(StringResponse::class, $response);

        $response = $a->transformResponse('text/plain');
        $this->assertInstanceOf(StringResponse::class, $response);
    }

    public function testTransformReponseWithException()
    {
        $request = System::request();
        $tpl = new MockErrorTemplate($request);
        $tpl->throw_other = true;
        $request->setTemplate($tpl);

        $a = new Error(500, "Error");
        $response = $a->transformResponse('text/html');
        $this->assertInstanceOf(StringResponse::class, $response);

        $a = new Error(500, "Error");
        $response = $a->transformResponse('text/plain');
        $this->assertInstanceOf(StringResponse::class, $response);
    }

    public function testFallbackWriter()
    {
        $data = array(
            'a' => false,
            'b' => [4, 5, 6],
            'c' => 3,
            'd' => 4.5,
            'e' => "str"
        );

        ob_start();
        Error::outputPlainText($data, 0, false);
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = <<<EOT
a = 'FALSE'
b = {
    0 = '4'
    1 = '5'
    2 = '6'
}
c = '3'
d = '4.5'
e = 'str'

EOT;

        $this->assertEquals($expected, $actual);

        ob_start();
        Error::outputPlainText(false, 0, false);
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = "FALSE\n";
        $this->assertEquals($expected, $actual);
    }

    public function testOutput()
    {
        $request = System::request();
        $tpl = new MockErrorTemplate($request);
        $request->setTemplate($tpl);

        $a = new Error(500, 'Internal Server Error');
        
        $tpl->setMimeType('text/plain');
        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = 'template render message';
        $this->assertEquals($expected, $actual);

        $tpl->setMimeType('text/html');
        ob_start();
        $a->output('text/html');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = 'template render message';
        $this->assertEquals($expected, $actual);


        $a = new Error(500, 'Internal Foo Error');
        $tpl->setMimeType('application/json');
        ob_start();
        $a->output('application/json');
        $actual = ob_get_contents();
        ob_end_clean();

        $actual = json_decode($actual, true);
        $this->assertEquals('Internal Foo Error', $actual['message']);
        $this->assertEquals(500, $actual['status_code']);
    }
}

class MockErrorTemplate extends Template
{
    public $throw_other = false;

    public function render()
    {
        if ($this->throw_other)
            throw new \RuntimeException($this->template_path);

        throw new StringResponse('template render message', $this->mime);
    }
}
