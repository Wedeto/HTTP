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

namespace WASP;

use PHPUnit\Framework\TestCase;
use WASP\Http\URL;

use DateTime;
use DateInterval;

/**
 * @covers WASP\Session
 */
final class SessionTest extends TestCase
{
    private $url;
    private $config;

    private $session_id;

    public function setUp()
    {
        $this->url = new URL('http://www.foobar.com');
        $this->config = new Dictionary();
        $this->server_vars = new Dictionary();
        $this->server_vars['HTTP_USER_AGENT'] = 'MockUserAgent';
        $this->server_vars['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function tearDown()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
            session_commit();
    }
    
    /**
     * @covers WASP\Session::__construct
     * @covers WASP\Session::getCookie
     * @covers WASP\Session::startHttpSession
     */
    public function testSession()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHttpSession();
         
        $cookie = $a->getCookie();
        $this->assertEquals('wasp_www_foobar_com', $cookie->getName());

        $expires = new \DateTime("@" . $cookie->getExpires());
        $this->assertTrue(Date::isFuture($expires));
        $this->assertEquals('www.foobar.com', $cookie->getDomain());
        $this->assertEquals(true, $cookie->GetHttpOnly());
    }

    /**
     * @covers WASP\Session::__construct
     * @covers WASP\Session::getCookie
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::resetID
     */
    public function testSessionReset()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHttpSession();

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
     * @covers WASP\Session::__construct
     * @covers WASP\Session::getCookie
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::destroy
     * @covers WASP\Session::setSessionID
     */
    public function testSessionDestroy()
    {
        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->startHttpSession();
        $sid = $a->getSessionID();
        $a['pi'] = 3.14;
        $this->assertEquals(3.14, $_SESSION['pi']);
        $a->destroy();
        $this->assertFalse(isset($_SESSION['pi']));
        $this->assertEquals(PHP_SESSION_NONE, session_status());

        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionID($sid);
        $a->startHttpSession();
        $this->assertFalse(isset($_SESSION['pi']));
    }

    /**
     * @covers WASP\Session::__construct
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::getCookie
     */
    public function testSessionConfigWithLifetime()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '1D');
        $a = new Session($this->url, $cfg, $this->server_vars);
        $a->startHttpSession();

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
     * @covers WASP\Session::__construct
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::getCookie
     */
    public function testSessionConfigWithLifetimeIntValue()
    {
        $cfg = new Dictionary;
        $cfg['cookie'] = $this->config;
        $cfg->set('cookie', 'lifetime', '86400');
        $a = new Session($this->url, $cfg, $this->server_vars);
        $a->startHttpSession();

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
     * @covers WASP\Session::__construct
     * @covers WASP\Session::startCLISession
     * @covers WASP\Session::getCookie
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
     * @covers WASP\Session::__construct
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::getSessionName
     * @covers WASP\Session::getSessionID
     * @covers WASP\Session::setSessionID
     * @covers WASP\Session::secureSession
     * @covers WASP\Session::resetID
     * @covers WASP\Session::close
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

        $a->startHttpSession();
        $this->assertEquals($sid, $a->getSessionID());

        $a->set('session_mgmt', 'start_time', 0);
        $this->assertEquals(0, $_SESSION['session_mgmt']['start_time']);
        $a->close();

        $this->assertNull($a->get('session_mgmt', 'start_time'));

        $a = new Session($this->url, $this->config, $this->server_vars);
        $a->setSessionId($sid);

        $a->startHttpSession();
        $this->assertNotEquals($sid, $a->getSessionId());
        $a->close();
    }

    /**
     * @covers WASP\Session::__construct
     * @covers WASP\Session::startHttpSession
     * @covers WASP\Session::getSessionName
     * @covers WASP\Session::getSessionID
     * @covers WASP\Session::setSessionID
     * @covers WASP\Session::secureSession
     * @covers WASP\Session::resetID
     * @covers WASP\Session::close
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
        $a->startHttpSession();
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
        $a->startHttpSession();

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
        $a->startHttpSession();

        // Should be redirected to a new session, without authentication
        $this->assertNotEquals($new_session_id, $a->getSessionID());
        $this->assertFalse(isset($_SESSION['authentication']));
        $a->close();
    }
}
