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
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

use Wedeto\Log\LoggerFactory;
use Wedeto\Log\Logger;
use Wedeto\Log\Writer\MemLogWriter;
use Wedeto\IO\Dir;

/**
 * @covers Wedeto\HTTP\Response\FileResponse
 */
final class FileResponseTest extends TestCase
{
    private $msg = "foobar";
    private $path;
    private $file;
    private $config;
    
    private $logger;
    private $memlog;

    public function setUp()
    {
        $this->logger = Logger::getLogger(FileResponse::class);
        FileResponse::setLogger($this->logger);
        $this->memlog = new MemLogWriter("debug");
        $this->logger->addLogWriter($this->memlog);

        // Make the cache use a virtual test path
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('test'));

        $this->path = vfsStream::url('test');
        $this->file = $this->path . "/testfile.dat";

        $fh = fopen($this->file, 'w');
        fwrite($fh, $this->msg);
        fclose($fh);
    }

    public function tearDown()
    {
        $this->logger->removeLogWriters();
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
		$log = $this->memlog->getLog();
		$this->assertEquals(['   WARNING: FileResponse promised to send 6 bytes but 9 were actually transfered of file foobar.txt'], $log);
    }

    public function testFileResponseXSendFile()
    {
        $a = new FileResponse($this->file, 'foobar.txt', false);
        $a->setUseXSendFile(true);
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
