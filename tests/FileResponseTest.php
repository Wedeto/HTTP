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
use WASP\IO\Dir;
use WASP\System;

/**
 * @covers WASP\Http\FileResponse
 */
final class FileResponseTest extends TestCase
{
    private $msg = "foobar";
    private $path;
    private $file;
    private $config;

    public function setUp()
    {
        $cpath = System::path();
        $this->path = $cpath->var . '/test';
        Dir::mkdir($this->path);
        $this->file = tempnam($this->path, 'fileresponse');

        $fh = fopen($this->file, 'w');
        fwrite($fh, $this->msg);
        fclose($fh);

        $this->config = System::config();
        $this->config->set('io', 'use_send_file', null);
    }

    public function tearDown()
    {
        Dir::rmtree($this->path);
        $this->config->set('io', 'use_send_file', null);

        $logger = Logger::getLogger(FileResponse::class);
        $logger->removeLogHandlers();
    }

    public function testFileResponse()
    {
        $a = new FileResponse($this->file, 'foobar.txt', false);
        $this->assertEquals($this->file, $a->getFileName());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $this->assertFalse($a->getDownload());
        $expected = ['Content-Length' => strlen($this->msg), 'Content-Disposition' => 'inline; filename=foobar.txt'];
        $this->assertEquals($expected, $actual);

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($this->msg, $actual);
    }

    public function testFileResponseAutoNaming()
    {
        $a = new FileResponse($this->file);
        $this->assertEquals($this->file, $a->getFileName());
        $expected_fn = basename($this->file);
        $this->assertEquals($expected_fn, $a->getOutputFileName());

        $actual = $a->getHeaders();
        $this->assertFalse($a->getDownload());
        $expected = ['Content-Length' => strlen($this->msg), 'Content-Disposition' => 'inline; filename=' . $expected_fn];
        $this->assertEquals($expected, $actual);

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($this->msg, $actual);
    }

    public function testFileDownload()
    {
        $a = new FileResponse($this->file, 'foobar.txt', true);
        $this->assertEquals($this->file, $a->getFileName());
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

    public function testFileDownloadInvalidLength()
    {
        $logger = Logger::getLogger(FileResponse::class);
        $devlogger = new DevLogger("debug");
        $logger->addLogHandler($devlogger);

        $a = new FileResponse($this->file, 'foobar.txt', true);

        $this->assertEquals($this->file, $a->getFileName());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $expected = ['Content-Length' => 6, 'Content-Disposition' => 'download; filename=foobar.txt'];
        $this->assertEquals($expected, $actual);
        $this->assertTrue($a->getDownload());

        file_put_contents($this->file, 'foobarbaz');

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('foobarbaz', $actual);

		// Validate length error message
		$log = $devlogger->getLog();
		$this->assertEquals(['   WARNING: FileResponse promised to send 6 bytes but 9 were actually transfered of file foobar.txt'], $log);
    }

    public function testFileResponseXSendFile()
    {
        $this->config->set('io', 'use_send_file', true);
        $a = new FileResponse($this->file, 'foobar.txt', false);
        $this->assertEquals($this->file, $a->getFileName());
        $this->assertEquals('foobar.txt', $a->getOutputFileName());

        $actual = $a->getHeaders();
        $this->assertFalse($a->getDownload());
        $expected = [
            'Content-Length' => strlen($this->msg), 
            'Content-Disposition' => 'inline; filename=foobar.txt', 
            'X-Sendfile' => $this->file
        ];
        $this->assertEquals($expected, $actual);

        ob_start();
        $a->output('text/plain');
        $actual = ob_get_contents();
        ob_end_clean();

        $this->assertEmpty($actual);
    }
}
