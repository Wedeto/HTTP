<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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
use Wedeto\Util\Dictionary;
use Wedeto\Log\Logger;
use Wedeto\Log\Writer\MemLogWriter;

/**
 * @covers Wedeto\HTTP\RequestBody
 */
final class RequestBodyTest extends TestCase
{
    public function testRequestParserWithValidHeaderAndContent()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/json';
        $content = json_encode(['foo' => 'bar']);
        $req['HTTP_CONTENT_LENGTH'] = strlen($content);
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content), $body->getContentLength());
        $this->assertEquals('application/json', $body->getContentType());
        $this->assertEquals($content, $body->getRawContent());
    }

    public function testRequestParserWithMissingHeaders()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/json';

        $body = new RequestBody($req);
        $this->assertFalse($body->isPresent());
    }

    public function testRequestParserWithLengthTooHigh()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/json';
        $content = json_encode(['foo' => 'bar']);
        $req['HTTP_CONTENT_LENGTH'] = strlen($content) * 3;
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $fakelog = new MemLogWriter("WARNING");
        $wlog = Logger::getLogger(RequestBody::class);
        $wlog->addLogWriter($fakelog);

        // Request should parse successfully
        RequestBody::setLogger($wlog);
        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content) * 3, $body->getContentLength());
        $this->assertEquals('application/json', $body->getContentType());
        $this->assertEquals($content, $body->getRawContent());

        // Logger should have a message
        $log = $fakelog->getLog();
        $this->assertEquals(1, count($log));
        $line = reset($log);
        $this->assertContains("Less data was posted than promised", $line);
    }

    public function testRequestParserWithLengthTooLow()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/json';
        $content = json_encode(['foo' => 'bar']);
        $req['HTTP_CONTENT_LENGTH'] = strlen($content) - 1;
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        // Request should parse successfully
        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content) - 1, $body->getContentLength());
        $this->assertEquals('application/json', $body->getContentType());

        // The parsed data should be 1 character short
        $this->assertEquals(substr($content, 0, -1), $body->getRawContent());
    }

    public function testRequestParserGetParsedJSONData()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/json';
        $content = json_encode(['foo' => 'bar']);
        $req['HTTP_CONTENT_LENGTH'] = strlen($content);
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content), $body->getContentLength());
        $this->assertEquals('application/json', $body->getContentType());

        $dict = $body->getParsedContent();
        $this->assertEquals('bar', $dict['foo']);
    }

    public function testRequestParserGetParsedXMLData()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/xml';
        $content = '<?xml version="1.0"?><doc><foo>foobar</foo></doc>';
        $req['HTTP_CONTENT_LENGTH'] = strlen($content);
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content), $body->getContentLength());
        $this->assertEquals('application/xml', $body->getContentType());

        $dict = $body->getParsedContent();
        $this->assertEquals('foobar', $dict['foo']);
    }

    public function testRequestParserGetUnsupportedData()
    {
        $req = new Dictionary;
        $req['HTTP_CONTENT_TYPE'] = 'application/foobar';
        $content = 'foobar';
        $req['HTTP_CONTENT_LENGTH'] = strlen($content);
        
        $tmp = tempnam("/tmp", "wdttest");
        $fh = fopen($tmp, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $body = new RequestBody($req);
        $body->setInputStreamURL($tmp);
        $this->assertTrue($body->isPresent());
        $this->assertEquals(strlen($content), $body->getContentLength());
        $this->assertEquals('application/foobar', $body->getContentType());

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Unknown content type");
        $dict = $body->getParsedContent();
    }
}
