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

use DateTime;
use DateInterval;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

use Wedeto\Util\Dictionary;
use Wedeto\Util\Date;
use Wedeto\Util\Cache;

use Wedeto\HTTP\URL;

if (!defined('WEDETO_TEST')) define('WEDETO_TEST', 1);

/**
 * @covers Wedeto\HTTP\Session
 */
final class SessionTest extends TestCase
{
    private $url;
    private $config;

    private $session_id;

    public function setUp()
    {
        // Make sure there is no active session
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();

        $this->url = new URL('http://www.foobar.com');
        $this->config = new Dictionary();
        $this->server_vars = new Dictionary();
        $this->server_vars['HTTP_USER_AGENT'] = 'MockUserAgent';
        $this->server_vars['REMOTE_ADDR'] = '127.0.0.1';

        // Make the cache use a virtual test path
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('cachedir'));
        $this->dir = vfsStream::url('cachedir');
        Cache::setCachePath($this->dir);
    }

    public function tearDown()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();
    }
    
    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::getCookie
     * @covers Wedeto\HTTP\Session::startHTTPSession
     */
    public function testSession()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHTTPSession();
         
        $cookie = $a->getCookie();
        $this->assertEquals('wasp_www_foobar_com', $cookie->getName());

        $expires = new \DateTime("@" . $cookie->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertEquals('www.foobar.com', $cookie->getDomain());
        $this->assertEquals(true, $cookie->getHTTPOnly());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Repeated session initialization");
        $a->startHTTPSession();
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::getCookie
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::resetID
     */
    public function testSessionReset()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHTTPSession();

        $cookie = $a->getCookie();
        $old_session_id = $cookie->getValue();
         
        $a->resetID();
        $cookie = $a->getCookie();
        $new_session_id = $cookie->getValue();

        $this->assertFalse($old_session_id === $new_session_id);
        $this->assertEquals($_SESSION, $a->getAll());
        
        $mgmt = $a['session_mgmt']->getAll();
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::getCookie
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::destroy
     * @covers Wedeto\HTTP\Session::setSessionID
     */
    public function testSessionDestroy()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHTTPSession();
        $sid = $a->getSessionID();
        $a['pi'] = 3.14;
        $this->assertEquals(3.14, $_SESSION['pi']);
        $a->destroy();
        $this->assertFalse(isset($_SESSION['pi']));
        $this->assertEquals(PHP_SESSION_NONE, session_status());

        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionID($sid);
        $a->startHTTPSession();
        $this->assertFalse(isset($_SESSION['pi']));
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::getCookie
     */
    public function testSessionConfigWithLifetime()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '1D');
        $a = new Session($this->url, $cfg, $this->server_vars);
        $a->startHTTPSession();

        $c = $a->getCookie();

        $now = new DateTime();

        $day2 = new DateTime();
        $offs = new DateInterval('P2D');
        $day2->add($offs);

        $expires = new DateTime('@' . $c->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertTrue(Date::isBefore($expires, $day2));
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::getCookie
     */
    public function testSessionConfigWithLifetimeIntValue()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '86400');
        $a = new Session($this->url, $cfg, $this->server_vars);
        $a->startHTTPSession();

        $c = $a->getCookie();

        $now = new DateTime();

        $day2 = new DateTime();
        $offs = new DateInterval('P2D');
        $day2->add($offs);

        $expires = new DateTime('@' . $c->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertTrue(Date::isBefore($expires, $day2));
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::startCLISession
     * @covers Wedeto\HTTP\Session::getCookie
     */
    public function testCLISession()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->StartCLISession();

        $a['test'] = 3.14;
        $_SESSION['test2'] = 6.28;
        $this->assertEquals($a->getAll(), $_SESSION);
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::getSessionName
     * @covers Wedeto\HTTP\Session::getSessionID
     * @covers Wedeto\HTTP\Session::setSessionID
     * @covers Wedeto\HTTP\Session::secureSession
     * @covers Wedeto\HTTP\Session::resetID
     * @covers Wedeto\HTTP\Session::close
     */
    public function testSessionExpires()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $name = $a->getSessionName();

        $sid = 'foobar';

        // Make sure there is no left over session data
        ini_set('session.use_strict_mode', 0);
        session_name($name);
        session_id($sid);
        try
        {
            session_start();
        }
        catch (\ErrorException $e)
        {}
        session_destroy();
        ini_set('session.use_strict_mode', 1);

        // Configure the session ID
        $a->setSessionId($sid);

        $a->startHTTPSession();
        $this->assertEquals($sid, $a->getSessionID());

        $a->set('session_mgmt', 'start_time', 0);
        $this->assertEquals(0, $_SESSION['session_mgmt']['start_time']);
        $a->close();

        $this->assertNull($a->get('session_mgmt', 'start_time'));

        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionId($sid);

        $a->startHTTPSession();
        $this->assertNotEquals($sid, $a->getSessionId());
        $a->close();
    }

    /**
     * @covers Wedeto\HTTP\Session::__construct
     * @covers Wedeto\HTTP\Session::startHTTPSession
     * @covers Wedeto\HTTP\Session::getSessionName
     * @covers Wedeto\HTTP\Session::getSessionID
     * @covers Wedeto\HTTP\Session::setSessionID
     * @covers Wedeto\HTTP\Session::secureSession
     * @covers Wedeto\HTTP\Session::resetID
     * @covers Wedeto\HTTP\Session::close
     */
    public function testSessionDestroyed()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $name = $a->getSessionName();

        $sid = 'foobar2';

        // Make sure there is no left over session data
        ini_set('session.use_strict_mode', 0);
        session_name($name);
        session_id($sid);
        try
        {
            session_start();
        }
        catch (\ErrorException $e)
        {}
        session_destroy();
        ini_set('session.use_strict_mode', 1);

        // Configure the session ID
        $a->setSessionId($sid);
        $a->startHTTPSession();
        $this->assertEquals($sid, $a->getSessionID());

        $a['authentication'] = "LOGGEDIN";
        $this->assertEquals('LOGGEDIN', $_SESSION['authentication']);
        
        $a->resetID();

        $this->assertEquals('LOGGEDIN', $_SESSION['authentication']);
        $new_session_id = $a->getSessionID();
        $a->close();

        // Start a new 'good' session
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionID($sid);
        $a->startHTTPSession();

        // Should be redirected to the new session now, as we're the good client
        $this->assertEquals($new_session_id, $a->getSessionID());
        $this->assertEquals('LOGGEDIN', $_SESSION['authentication']);
        $a->close();

        // Start a new 'evil' session with a different UA
        $orig_ua = $this->server_vars['HTTP_USER_AGENT'];
        $this->assertNotEquals('EvilClient', $orig_ua);
        $this->server_vars['HTTP_USER_AGENT'] = "EvilClient";
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionID($sid);
        $a->startHTTPSession();

        // Should be redirected to a new session, without authentication
        $this->assertNotEquals($new_session_id, $a->getSessionID());
        $this->assertFalse(isset($_SESSION['authentication']));
        $a->close();
    }

    public function testStartSession()
    {
        $session = new Session($this->url, $this->config, $this->server_vars);

        $this->assertFalse($session->active());

        $this->assertEquals($session, $session->start());
        $this->assertTrue($session->active());

        $this->assertEquals($session, $session->start());
        $this->assertTrue($session->active());

        $this->assertEquals($session, $session->close());
        $this->assertFalse($session->active());
    }
}
