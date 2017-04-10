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

use Wedeto\Util\Dictionary;
use Wedeto\Log\Logger;
use Wedeto\Log\Writer\MemLogWriter;

use Wedeto\HTTP\Response\Response;
use Wedeto\HTTP\Response\StringResponse;
use Wedeto\HTTP\Response\Error;

use Throwable;
use RuntimeException;

/**
 * @covers Wedeto\HTTP\Responder
 */
final class ResponderTest extends TestCase
{
    private $responder;
    private $config;
    private $request;

    public function setUp()
    {
        $this->config = new Dictionary;
        $this->config->set('site', 'dev', true);
        $this->config->set('site', 'tidy-output', true);

        $this->request = Request::createFromGlobals();
        $this->responder = new Responder($this->request);
        $this->responder->resetLogger();
    }

    public function tearDown()
    {
        $logger = Logger::getLogger(Responder::class);
        $logger->removeLogWriters();
    }

    public function testResponder()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->responder->addCookie($cookie);

        $lst = $this->responder->getCookies();
        $this->assertEquals(['foo' => $cookie], $lst);

        $this->responder->setHeader('Content-Type', 'foo/bar');
        $this->responder->setHeader('Content-type', 'bar/baz');

        $headers = $this->responder->getHeaders();
        $this->assertEquals(['Content-Type' => 'bar/baz'], $headers);

        $this->responder->setResponseCode(100);
        $this->assertEquals(100, $this->responder->getResponseCode());

        $this->responder->setResponseCode(500);
        $this->assertEquals(500, $this->responder->getResponseCode());
    }

    public function testEndOutputBuffers()
    {
        $logger = Logger::getLogger(Responder::class);
        Responder::setLogger($logger);
        $memlogger = new MemLogWriter("debug");
        $logger->addLogWriter($memlogger);

        $start_lvl = ob_get_level();

        ob_start();
        printf('bar');
        ob_start();
        printf('foo');

        $this->responder->endAllOutputBuffers($start_lvl);

        $log = $memlogger->getLog();
        
        $expected = [
            '     DEBUG: Script output: 1/1: foo',
            '     DEBUG: Script output: 2/1: bar'
        ];
        $this->assertEquals($expected, $log);
    }

    public function testInvalidResponseCode()
    {
        $logger = Logger::getLogger(Responder::class);
        $memlogger = new MemLogWriter("debug");
        Responder::setLogger($logger);

        $logger->addLogWriter($memlogger);
        $this->responder->setResponseCode(900);

        $log = $memlogger->getLog();
        $expected = 'CRITICAL: Invalid status 900';
        $this->assertTrue(strpos($log[0], $expected) !== false);

        $this->assertEquals(500, $this->responder->getResponseCode());
    }

    public function testSetResponse()
    {
        $resp = new StringResponse('foobar', 'text/plain');

        $this->responder->setResponse($resp);

        $actual = $this->responder->getResponse();
        $this->assertInstanceOf(StringResponse::class, $resp);
        $this->assertEquals($resp, $actual);
    }

    public function testRespond()
    {
        $this->request->accept = array('application/json' => 1);
        $this->responder = new MockResponder($this->request);

        $thr = new \InvalidArgumentException('foobar');
        $response = new StringResponse(json_encode(['foo' => 'bar']), 'application/json');
        $this->responder->setResponse($response);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $mock_response)
        {
            $this->assertEquals('application/json', $mock_response->mime);
            $actual = $mock_response->getPrevious();
            $this->assertEquals(spl_object_hash($response), spl_object_hash($actual));
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testRespondNoResponse()
    {
        $this->request->accept = array('text/plain' => 1);
        $this->responder = new MockResponder($this->request);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('text/plain', $response->mime);

            $resp = $response->getPrevious();
            $this->assertInstanceOf(Error::class, $resp);
            $this->assertContains("No output produced", $resp->getMessage());
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testRespondUnacceptableResponse()
    {
        $this->request->accept = array('application/javascript' => 1);
        $this->responder = new MockResponder($this->request);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('text/html', $response->mime);

            $resp = $response->getPrevious();
            $this->assertInstanceOf(Error::class, $resp);
            $this->assertContains("Not Acceptable", $resp->getMessage());
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testRespondEmptyResponse()
    {
        $this->request->accept = array('application/json' => 1);
        $this->responder = new MockResponder($this->request);
        $resp = new MockResponseResponse(array(), new RuntimeException('foo'));
        $this->responder->setResponse($resp);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('text/html', $response->mime);
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testTransformResponseFails()
    {
        $this->request->accept = array('application/json' => 1);
        $this->responder = new MockResponder($this->request);
        $resp = new MockResponseResponse(array('application/json' => true), new RuntimeException('foo'));
        $resp->fail_transform = true;
        $this->responder->setResponse($resp);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('application/json', $response->mime);
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testResponseSetCustomHeaders()
    {
        $this->request->accept = array('application/json' => 1);
        $this->responder = new MockResponder($this->request);
        $resp = new MockResponseResponse(array('application/json' => true), new RuntimeException('foo'));
        $this->responder->setResponse($resp);

        try
        {
            $this->responder->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('application/json', $response->mime);
            $prev = $response->getPrevious();
            $this->assertInstanceOf(MockResponseResponse::class, $prev);
        }

        $headers = $this->responder->getHeaders();
        $found = false;

        foreach ($headers as $k => $v)
            if ($k === "Foo" && $v === "bar")
                $found = true;

        $this->assertTrue($found);

        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }
}

class MockResponseResponse extends Response
{
    public $mime;
    public $fail_transform = false;
    public $headers;

    public function __construct($mime, Throwable $thr)
    {
        parent::__construct("", 0, $thr);
        $this->mime = $mime;
        $this->headers['Foo'] = 'bar';
    }
    
    public function getHeaders()
    {
        return $this->headers;
    }

    public function transformResponse(string $mime)
    {
        if ($this->fail_transform)
            throw new RuntimeException("Failed");
        return null;
    }
    
    public function output(string $mime)
    {}
}

class MockResponder extends Responder
{
    protected function doOutput(string $mime)
    {
        throw new MockResponseResponse($mime, $this->response);
    }
}
