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

/**
 * @covers Wedeto\HTTP\Nonce
 */
final class NonceTest extends TestCase
{
    protected $session;

    public function setUp()
    {
        $base = new URL('http://example.com/');
        $cfg = new Dictionary;
        $server = new Dictionary;
        $this->session = new Session($base, $cfg, $server);
        $this->session->startCLISession();

        Nonce::setParameterName('_nonce');
        Nonce::setNonceExpiresInSeconds(300);
    }

    public function testNonce()
    {
        $actual = Nonce::getNonce("foobar", $this->session, []);

        $post = new Dictionary;
        $post->set(Nonce::getParameterName(), $actual);

        $this->assertTrue(Nonce::validateNonce('foobar', $this->session, $post));
    }

    public function testCustomParameterName()
    {
        Nonce::setParameterName('csrf_token');
        $this->assertEquals('csrf_token', Nonce::getParameterName());

        $actual = Nonce::getNonce("foobar", $this->session, []);

        $post = new Dictionary;
        $post->set(Nonce::getParameterName(), $actual);

        $this->assertTrue(Nonce::validateNonce('foobar', $this->session, $post));
    }

    public function testTimeout()
    {
        Nonce::setNonceExpiresInSeconds(-1);

        $this->assertEquals(-1, Nonce::getNonceExpiresInSeconds());

        $actual = Nonce::getNonce("foobar", $this->session, []);
        $post = new Dictionary;
        $post->set(Nonce::getParameterName(), $actual);
        $this->assertFalse(Nonce::validateNonce('foobar', $this->session, $post));
    }

    public function testNoNonceSubmitted()
    {
        $post = new Dictionary;
        $this->assertNull(Nonce::validateNonce('foobar', $this->session, $post));
    }

    public function testContext()
    {
        $actual = Nonce::getNonce("foobar", $this->session, ['my_id' => 3]);

        $post = new Dictionary;
        $post->set(Nonce::getParameterName(), $actual);

        $this->assertFalse(Nonce::validateNonce('foobar', $this->session, $post));
        $this->assertTrue(Nonce::validateNonce('foobar', $this->session, $post, ['my_id' => 3]));
        $this->assertFalse(Nonce::validateNonce('foobar', $this->session, $post, ['my_id']));

        $post->set('my_id', 3);
        $this->assertTrue(Nonce::validateNonce('foobar', $this->session, $post, ['my_id']));
    }

    public function testInvalidNonces()
    {
        $post = new Dictionary;
        $post->set(Nonce::getParameterName(), 'garbage');

        $this->assertFalse(Nonce::validateNonce('foobar', $this->session, $post));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Context variables must be scalar");
        Nonce::getNonce('foobar', $this->session, ['foo' => []]);
    }
}
