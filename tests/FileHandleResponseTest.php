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
use WASP\Debug\Logger;
use WASP\Debug\DevLogger;

/**
 * @covers WASP\Http\FileHandleResponse
 */
final class FileHandleResponseTest extends TestCase
{
    private $msg = "foobar";
    private $fh;

    public function setUp()
    {
        $this->fh = fopen('php://memory', 'rw');
        fwrite($this->fh, $this->msg);
        fseek($this->fh, 0);
    }

    public function tearDown()
    {
        fclose($this->fh);

        $logger = Logger::getLogger(FileHandleResponse::class);
        $logger->removeLogHandlers();
    }

    public function testFileHandle()
    {
        $a = new FileHandleResponse($this->fh, 'foobar.txt', 'text/plain', false);
        $a->setLength(strlen($this->msg));

        $this->assertEquals($this->fh, $a->getFileHandle());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $expected = ['Content-Length' => strlen($this->msg), 'Content-Disposition' => 'inline; filename=foobar.txt'];
        $this->assertEquals($expected, $actual);
        $this->assertFalse($a->getDownload());

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($this->msg, $actual);
    }

    public function testFileHandleDownload()
    {
        $a = new FileHandleResponse($this->fh, 'foobar.txt', 'text/plain', true);
        $a->setLength(strlen($this->msg));

        $this->assertEquals($this->fh, $a->getFileHandle());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $expected = ['Content-Length' => strlen($this->msg), 'Content-Disposition' => 'download; filename=foobar.txt'];
        $this->assertEquals($expected, $actual);
        $this->assertTrue($a->getDownload());

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($this->msg, $actual);
    }

    public function testFileHandleDownloadInvalidLength()
    {
        $logger = Logger::getLogger(FileHandleResponse::class);
        $devlogger = new DevLogger("debug");
        $logger->addLogHandler($devlogger);

        $a = new FileHandleResponse($this->fh, 'foobar.txt', 'text/plain', true);
        $a->setLength(60);

        $this->assertEquals($this->fh, $a->getFileHandle());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $expected = ['Content-Length' => 60, 'Content-Disposition' => 'download; filename=foobar.txt'];
        $this->assertEquals($expected, $actual);
        $this->assertTrue($a->getDownload());

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($this->msg, $actual);

		// Validate length error message
		$log = $devlogger->getLog();
		$this->assertEquals(['   WARNING: FileHandleResponse was specified to send 60 bytes but 6 were actually transfered of file foobar.txt'], $log);
    }
}
