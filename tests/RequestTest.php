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
use Wedeto\Util\Date;
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
    private $files;
    private $config;

    private $url;

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

        $this->files = [];

        $this->config = new Dictionary;
    }

    /**
     * @covers Wedeto\HTTP\Request::__construct
     */
    public function testRequestVariables()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->files);

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
     * @covers Wedeto\HTTP\Request::cli
     */
    public function testCLI()
    {
        $this->assertTrue(Request::cli());
    }

    public function testGetStartTime()
    {
        $request = new Request($this->get, $this->post, $this->cookie, $this->server, $this->files);
        $this->assertEquals($_SERVER['REQUEST_TIME_FLOAT'], Date::dateToFloat($request->getStartTime()));
    }

    public function testNoScheme()
    {
        unset($this->server['REQUEST_SCHEME']);
        unset($this->server['SERVER_NAME']);
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->files);
        
        $expected = new URL('/foo');
        $this->assertEquals($expected, $req->url);

        $expected = new URL('/');
        $this->assertEquals($expected, $req->webroot);
    }

    public function testStartSession()
    {
        $req = new Request($this->get, $this->post, $this->cookie, $this->server, $this->files);
        $req->startSession($this->url, $this->config);

        $sess_object = $req->session;

        $this->assertEquals($_SESSION, $req->session->getAll());
        $_SESSION['foobar'] = rand();
        $this->assertEquals($_SESSION, $req->session->getAll());

        $req->startSession($this->url, $this->config);
        $this->assertEquals($sess_object, $req->session);

    }

    public function testCreateFromGlobals()
    {
        $req = Request::createFromGlobals();
        $this->assertInstanceOf(Request::class, $req);

        $vals = &$req->get->get();
        $this->assertEquals($_GET, $vals);

        $_GET['test'] = rand();
        $this->assertEquals($_GET, $vals);

        $vals = &$req->post->get();
        $this->assertEquals($_POST, $vals);

        $_GET['test'] = rand();
        $this->assertEquals($_POST, $vals);

        $vals = &$req->server->get();
        $this->assertEquals($_SERVER, $vals);

        $_GET['test'] = rand();
        $this->assertEquals($_SERVER, $vals);

        $vals = &$req->cookie->get();
        $this->assertEquals($_COOKIE, $vals);

        $_GET['test'] = rand();
        $this->assertEquals($_COOKIE, $vals);
    }

    public function testGetSession()
    {
        $req = Request::createFromGlobals();
        $ses = null;

        try
        {
            $sess = $req->getSession();
            $this->assertNull($sess);
            $req->startSession($this->url, $this->config);

            $sess = $req->getSession();
            $this->assertInstanceOf(Session::class, $sess);
        }
        finally
        {
            if ($sess instanceof Session)
                $sess->destroy();
        }
    }

    public function testSetAccept()
    {
        $req = Request::createFromGlobals();

        $this->assertInstanceOf(Accept::class, $req->accept);
        $this->assertTrue($req->accepts('application/json') == true);
        $this->assertTrue($req->accepts('text/html') == true);

        $ac = new Accept('text/html');
        $this->assertEquals($req, $req->setAccept($ac));
        $this->assertEquals($ac, $req->accept);

        $this->assertFalse($req->accepts('application/json') == true);
        $this->assertTrue($req->accepts('text/html') == true);

        $ac2 = new Accept('application/json');
        $this->assertEquals($req, $req->setAccept($ac2));
        $this->assertEquals($ac2, $req->accept);
        $this->assertNotEquals($ac, $req->accept);

        $this->assertTrue($req->accepts('application/json') == true);
        $this->assertFalse($req->accepts('text/html') == true);
    }

    public function testGetInvalidProperty()
    {
        $req = Request::createFromGlobals();
        
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Property does not exist: foo");
        $foo = $req->foo;
    }
}
