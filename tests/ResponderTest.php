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

use Wedeto\Log\Logger;
use Wedeto\Log\MemLogger;
use Wedeto\HTTP\Response\Response;

use Throwable;
use RuntimeException;

/**
 * @covers Wedeto\HTTP\Responder
 */
final class ResponderTest extends TestCase
{
    private $rb;
    private $config;
    private $request;

    public function setUp()
    {
        $this->config = System::config();
        $this->config->set('site', 'dev', true);
        $this->config->set('site', 'tidy-output', true);

        $this->request = System::request();
        $this->rb = new Responder($this->request);
    }

    public function tearDown()
    {
        $logger = Logger::getLogger(Responder::class);
        $logger->removeLogHandlers();
    }

    public function testResponder()
    {
        $cookie = new Cookie('foo', 'bar');
        $this->rb->addCookie($cookie);

        $lst = $this->rb->getCookies();
        $this->assertEquals(['foo' => $cookie], $lst);

        $this->rb->setHeader('Content-Type', 'foo/bar');
        $this->rb->setHeader('Content-type', 'bar/baz');

        $headers = $this->rb->getHeaders();
        $this->assertEquals(['Content-Type' => 'bar/baz'], $headers);

        $this->rb->setResponseCode(100);
        $this->assertEquals(100, $this->rb->getResponseCode());

        $this->rb->setResponseCode(500);
        $this->assertEquals(500, $this->rb->getResponseCode());
    }

    public function testEndOutputBuffers()
    {
        $logger = Logger::getLogger(Responder::class);
        $devlogger = new DevLogger("debug");
        $logger->addLogHandler($devlogger);

        $start_lvl = ob_get_level();

        ob_start();
        printf('bar');
        ob_start();
        printf('foo');

        $this->rb->endAllOutputBuffers($start_lvl);

        $log = $devlogger->getLog();
        
        $expected = [
            '     DEBUG: Script output: 1/1: foo',
            '     DEBUG: Script output: 2/1: bar'
        ];
        $this->assertEquals($expected, $log);
    }

    public function testInvalidResponseCode()
    {
        $logger = Logger::getLogger(Responder::class);
        $devlogger = new DevLogger("debug");
        $logger->addLogHandler($devlogger);

        $this->rb->setResponseCode(900);

        $log = $devlogger->getLog();
        $expected = 'CRITICAL: Invalid status 900';
        $this->assertTrue(strpos($log[0], $expected) !== false);

        $this->assertEquals(500, $this->rb->getResponseCode());
    }

    public function testGetAssetManager()
    {
        $mgr = $this->rb->GetAssetManager();
        $this->assertInstanceOf(AssetManager::class, $mgr);

        $this->assertTrue($mgr->getTidy());
        $this->assertFalse($mgr->getMinified());
    }

    public function testSetThrowable()
    {
        $thr = new \InvalidArgumentException('foobar');
        $this->rb->setThrowable($thr);

        $resp = $this->rb->getResponse();
        $this->assertInstanceOf(Error::class, $resp);
        $prev = $resp->getPrevious();
        $this->assertEquals($thr, $prev);
    }

    public function testSetResponse()
    {
        $resp = new StringResponse('foobar', 'text/plain');

        $this->rb->setThrowable($resp);

        $actual = $this->rb->getResponse();
        $this->assertInstanceOf(StringResponse::class, $resp);
        $this->assertEquals($resp, $actual);
    }

    public function testRespond()
    {
        $this->request->accept = array('application/json' => 1);
        $this->rb = new MockResponder($this->request);

        $thr = new \InvalidArgumentException('foobar');
        $this->rb->setThrowable($thr);

        try
        {
            $this->rb->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('application/json', $response->mime);

            $resp = $response->getPrevious();
            $this->assertInstanceOf(DataResponse::class, $resp);
            $dict = $resp->getDictionary();

            $found = false;
            foreach ($dict->getSection('exception') as $line)
            {
                if (strpos($line, 'Exception: InvalidArgumentException [0] foobar') !== false)
                {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found);
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testRespondNoResponse()
    {
        $this->request->accept = array('application/json' => 1);
        $this->rb = new MockResponder($this->request);

        try
        {
            $this->rb->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('application/json', $response->mime);

            $resp = $response->getPrevious();
            $this->assertInstanceOf(DataResponse::class, $resp);
            $dict = $resp->getDictionary();

            $this->assertEquals('No output produced', $dict['message']);
        }
        ob_start(); // It will have closed PHPUnits output buffer ;-)
    }

    public function testRespondEmptyResponse()
    {
        $this->request->accept = array('application/json' => 1);
        $this->rb = new MockResponder($this->request);
        $resp = new MockResponseResponse(array(), new RuntimeException('foo'));
        $this->rb->setThrowable($resp);

        try
        {
            $this->rb->respond();
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
        $this->rb = new MockResponder($this->request);
        $resp = new MockResponseResponse(array('application/json' => true), new RuntimeException('foo'));
        $resp->fail_transform = true;
        $this->rb->setThrowable($resp);

        try
        {
            $this->rb->respond();
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
        $this->rb = new MockResponder($this->request);
        $resp = new MockResponseResponse(array('application/json' => true), new RuntimeException('foo'));
        $this->rb->setThrowable($resp);

        try
        {
            $this->rb->respond();
        }
        catch (MockResponseResponse $response)
        {
            $this->assertEquals('application/json', $response->mime);
            $prev = $response->getPrevious();
            $this->assertInstanceOf(MockResponseResponse::class, $prev);
        }

        $headers = $this->rb->getHeaders();
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
