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
use Wedeto\Util\Dictionary;
use Wedeto\FileFormats\AbstractWriter;
use Wedeto\FileFormats\WriterFactory;

/**
 * @covers Wedeto\HTTP\Response\DataResponse
 */
final class DataResponseTest extends TestCase
{
    public function testCreateWithArray()
    {
        $a = new DataResponse(array('a' => 'b'));

        $dict = $a->getData();
        $this->assertEquals("b", $dict['a']);
    }

    public function testCreateWithDictionary()
    {
        $dict = new Dictionary(['a' => 'b']);
        $a = new DataResponse($dict);

        $dict2 = $a->getData();
        $this->assertEquals($dict2, $dict);
    }

    public function testGetMimeTypes()
    {
        $a = new DataResponse(array(1, 2, 3));
        $actual = $a->getMimeTypes();

        $this->assertContains('application/json', $actual);
        $this->assertContains('application/xml', $actual);
    }

    public function testPrettyPrinting()
    {
        $a = new DataResponse(array(1, 2, 3));
        $this->assertEquals($a, $a->setPrettyPrint(true));
    }

    public function testOutput()
    {
        $a = new DataResponse(array(1, 2, 3));
        
        ob_start();
        $a->output('application/json');
        $contents = ob_get_contents();
        ob_end_clean();

        $actual = json_decode($contents);
        $expected = [1, 2, 3];
        $this->assertEquals($expected, $actual);
    }

    public function testOutputInvalidType()
    {
        $a = new DataResponse(array(1, 2, 3));
        
        ob_start();
        $a->output('application/foobar');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = "[1, 2, 3]";
        $this->assertEquals($expected, $actual);
    }

    public function testCustomOutputWriter()
    {
        $a = new DataResponse(array(1, 2, 3));
        $a->addFileFormat('application/foobar', new TestDataResponseFooWriter);

        ob_start();
        $a->output('application/foobar');
        $actual = ob_get_contents();
        ob_end_clean();

        $expected = "FOO";
        $this->assertEquals($expected, $actual);
    }

    public function testSetFileFormats()
    {
        $a = new DataResponse(['foo' => 'bar']);

        $mock = $this->getMockForAbstractClass(AbstractWriter::class);

        $writers = WriterFactory::getAvailableWriters();
        $this->assertGreaterThan(0, count($writers));

        $keys = array_keys($writers);
        $this->assertEquals($keys, $a->getMimeTypes());

        $this->assertEquals($a, $a->addFileFormat('application/foo', $mock));
        $keys[] = 'application/foo';
        $this->assertEquals($keys, $a->getMimeTypes());

        $this->assertEquals($a, $a->addFileFormat('application/foo3', TestDataResponseFooWriter::class));
        $keys[] = 'application/foo3';
        $this->assertEquals($keys, $a->getMimeTypes());

        $this->assertEquals($a, $a->removeFileFormat('application/foo3'));
        array_pop($keys);
        $this->assertEquals($keys, $a->getMimeTypes());

        $thrown = false;
        try
        {
            $a->addFileFormat('application/foo2', 'foo');
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertContains("Class foo does not exist or does not implement", $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown, "Exception was not thrown");

        $thrown = false;
        try
        {
            $a->addFileFormat('application/foo2', null);
        }
        catch (\InvalidArgumentException $e)
        {
            $this->assertContains("Invalid writer specified: NULL", $e->getMessage());
            $thrown = true;
        }
        $this->assertTrue($thrown, "Exception was not thrown");

        $a->setFileFormats($writers);
        $keys = array_keys($writers);
        $this->assertEquals($keys, $a->getMimeTypes());
    }

    public function testForceFileFormat()
    {
        $a = new DataResponse(['foo' => 'bar']);
        $a->forceMimeType('application/json');

        $mimes = $a->GetMimeTypes();
        $this->assertEquals(1, count($mimes));
        reset($mimes);

        $this->assertEquals('application/json', $mimes[0]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot force output without a writer');

        $a->forceMimeType('application/xml');
    }
}

class TestDataResponseFooWriter extends \Wedeto\FileFormats\AbstractWriter
{
    public function format($data, $file_handle)
    {
        fwrite($file_handle, "FOO");
    }
}
