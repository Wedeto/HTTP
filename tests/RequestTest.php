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
use Wedeto\HTTP\Response\RedirectRequest;

/**
 * @covers Wedeto\HTTP\Request
 */
final class RequestTest extends TestCase
{
    private $get;
    private $post;
    private $server;
    private $cookie;
    private $config;

    private $url;

    private $path;
    private $resolve;

    public function setUp()
    {
        $this->get = array(
            'foo' => 'bar'
        );

        $this->post = array(
            'test' => 'value'
        );

        $this->server = array(
            'REQUEST_SCHEME' => 'https',
            'SERVER_NAME' => 'www.example.com',
            'REQUEST_URI' => '/foo',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_TIME_FLOAT' => $_SERVER['REQUEST_TIME_FLOAT'],
            'HTTP_ACCEPT' => 'text/plain;q=1,text/html;q=0.9'
        );

        $this->url = new URL('https://www.example.com/foo');

        $this->cookie = array(
            'session_id' => '1234'
        );
    }

    /**
     * @covers Wedeto\HTTP\Request::__construct
     * @covers Wedeto\Session::start
     */
    public function testRequestVariables()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server);

        $this->assertEquals($req->get->getAll(), $this->get);
        $this->assertEquals($req->post->getAll(), $this->post);
        $this->assertEquals($req->cookie->getAll(), $this->cookie);
        $this->assertEquals($req->server->getAll(), $this->server);

        $this->get['foobarred_get'] = true;
        $this->assertEquals($req->get->getAll(), $this->get);

        $this->post['foobarred_post'] = true;
        $this->assertEquals($req->post->getAll(), $this->post);

        $this->cookie['foobarred_cookie'] = true;
        $this->assertEquals($req->cookie->getAll(), $this->cookie);

        $this->server['foobarred_server'] = true;
        $this->assertEquals($req->server->getAll(), $this->server);
    }

    /**
     * @covers Wedeto\HTTP\Request::parseAccept
     */
    public function testAcceptParser()
    {
        unset($this->server['HTTP_ACCEPT']);
        $req = new Request($this->get, $this->post, $this->cookie, $this->server);
        $this->assertEquals($req->accept, array('text/html' => 1.0));

        $accept = Request::parseAccept("garbage");
        $this->assertEquals($accept, array("garbage" => 1.0));
    }

    /**
     * @covers Wedeto\HTTP\Request::cli
     */
    public function testCLI()
    {
        $this->assertTrue(Request::cli());
    }

    /**
     * @covers Wedeto\HTTP\Request::isAccepted
     * @covers Wedeto\HTTP\Request::getBestResponseType
     */
    public function testAccept()
    {
        $request = new Request($this->get, $this->post, $this->cookie, $this->server);
        $request->accept = array(); 
        $this->assertTrue($request->isAccepted("text/html") == true);
        $this->assertTrue($request->isAccepted("foo/bar") == true);

        $request->accept = array(
            'text/html' => 0.9,
            'text/plain' => 0.8,
            'application/*' => 0.7
        );

        $this->assertTrue($request->isAccepted("text/html") == true);
        $this->assertTrue($request->isAccepted("text/plain") == true);
        $this->assertTrue($request->isAccepted("application/bar") == true);
        $this->assertFalse($request->isAccepted("foo/bar") == true);

        $resp = $request->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/html");

        $resp = $request->getBestResponseType(array('application/bar', 'application/foo'));
        $this->assertEquals($resp, "application/bar");

        $resp = $request->getBestResponseType(array('application/foo', 'application/bar'));
        $this->assertEquals($resp, "application/foo");

        $request->accept = array();
        $resp = $request->getBestResponseType(array('foo/bar', 'application/bar/', 'text/plain', 'text/html'));
        $this->assertEquals($resp, "text/plain");

        $op = array(
            'text/plain' => 'Plain text',
            'text/html' => 'HTML Text'
        );

        ob_start();
        $request->outputBestResponseType($op);
        $c = ob_get_contents();
        ob_end_clean();
    }

    public function testGetStartTime()
    {
        $request = new Request($this->get, $this->post, $this->cookie, $this->server);
        $this->assertEquals($_SERVER['REQUEST_TIME_FLOAT'], $request->getStartTime());
    }

    public function testNoScheme()
    {
        unset($this->server['REQUEST_SCHEME']);
        $req = new Request($this->get, $this->post, $this->cookie, $this->server);
        
        $expected = new URL('/foo');
        $this->assertEquals($expected, $req->url);

        $expected = new URL('/');
        $this->assertEquals($expected, $req->webroot);
    }

    public function testStartSession()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server);
        $req->startSession($this->url);

        $sess_object = $req->session;

        $this->assertEquals($_SESSION, $req->session->getAll());
        $_SESSION['foobar'] = rand();
        $this->assertEquals($_SESSION, $req->session->getAll());

        $req->startSession($this->url);
        $this->assertEquals($sess_object, $req->session);

    }

    public function testWants()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server);

        $req->accept = Request::parseAccept('text/html;q=1,text/plain;q=0.9');
        $this->assertFalse($req->wantJSON());
        $this->assertTrue($req->wantHTML() !== false);
        $this->assertTrue($req->wantText() !== false);
        $this->assertFalse($req->wantXML());

        $req->accept = Request::parseAccept('application/json;q=1,application/*;q=0.9');
        $this->assertTrue($req->wantJSON() !== false);
        $this->assertFalse($req->wantHTML());
        $this->assertFalse($req->wantText());
        $this->assertTrue($req->wantXML() !== false);

        $req->accept = Request::parseAccept('application/json;q=1,text/html;q=0.9,text/plain;q=0.8');
        $type = $req->chooseResponse(array('application/json', 'text/html'));
        $this->assertEquals('application/json', $type);

        $type = $req->chooseResponse(array('text/plain', 'text/html'));
        $this->assertEquals('text/html', $type);
    }
}
