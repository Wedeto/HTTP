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
use DateTime;
use DateInterval;

/**
 * @covers Wedeto\HTTP\CachePolicy
 */
final class CachePolicyTest extends TestCase
{
    public function testCachePolicyExpirySetByExpiresFunctions()
    {
        $cp = new CachePolicy;

        $this->assertEquals($cp, $cp->setExpireDate(new DateTime()));
        $this->assertEquals($cp->getExpiresInSeconds(), 0);
        
        $this->assertEquals($cp, $cp->setExpiresInSeconds(10));
        $this->assertEquals($cp->getExpiresInSeconds(), 10);

        $di = new DateInterval("PT35M");
        $this->assertEquals($cp, $cp->setExpiresIn($di));
        $this->assertEquals($cp->getExpiresInSeconds(), 35 * 60);

        $di = new DateInterval("PT45M");
        $dt = new DateTime;
        $dt->add($di);
        $this->assertEquals($cp, $cp->setExpireDate($dt));
        $this->assertEquals($cp->getExpiresInSeconds(), 45 * 60);
    }

    public function testCachePolicyExpirySetByExpiresInWrapper()
    {
        $cp = new CachePolicy;

        $this->assertEquals($cp, $cp->setExpires(new DateTime()));
        $this->assertEquals($cp->getExpiresInSeconds(), 0);
        
        $this->assertEquals($cp, $cp->setExpires(10));
        $this->assertEquals($cp->getExpiresInSeconds(), 10);

        $di = new DateInterval("PT35M");
        $this->assertEquals($cp, $cp->setExpires($di));
        $this->assertEquals($cp->getExpiresInSeconds(), 35 * 60);

        $di = new DateInterval("PT45M");
        $dt = new DateTime;
        $dt->add($di);
        $this->assertEquals($cp, $cp->setExpires($dt));
        $this->assertEquals($cp->getExpiresInSeconds(), 45 * 60);

        $this->expectException(\InvalidARgumentException::class);
        $this->expectExceptionMessage("A DateTime, DateInterval or int number of seconds");
        $cp->setExpires("foo");
    }

    public function testSetCachePolicy()
    {
        $cp = new CachePolicy;

        $this->assertEquals("no-cache", CachePolicy::CACHE_DISABLE);
        $this->assertEquals("private", CachePolicy::CACHE_PRIVATE);
        $this->assertEquals("public", CachePolicy::CACHE_PUBLIC);

        $this->assertEquals($cp, $cp->setCachePolicy(CachePolicy::CACHE_PUBLIC));
        $this->assertEquals(CachePolicy::CACHE_PUBLIC, $cp->getCachePolicy());

        $this->assertEquals($cp, $cp->setCachePolicy(CachePolicy::CACHE_PRIVATE));
        $this->assertEquals(CachePolicy::CACHE_PRIVATE, $cp->getCachePolicy());

        $this->assertEquals($cp, $cp->setCachePolicy(CachePolicy::CACHE_DISABLE));
        $this->assertEquals(CachePolicy::CACHE_DISABLE, $cp->getCachePolicy());

        $this->expectException(\InvalidARgumentException::class);
        $this->expectExceptionMessage("Invalid cache policy: foo");
        $cp->setCachePolicy("foo");
    }

    public function testCachePolicyGetHeaders()
    {
        $cp = new CachePolicy;

        $epoch = date('r', 0);
        $cp->setCachePolicy(CachePolicy::CACHE_DISABLE);
        $actual = $cp->getHeaders();

        $expected = [
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Expires' => $epoch
        ];
        $this->assertEquals($expected, $actual);

        // Change cache policy, but do not set a expiry date
        $cp->setCachePolicy(CachePolicy::CACHE_PUBLIC);
        $actual = $cp->getHeaders();
        $this->assertEquals($expected, $actual, "Cache is not disabled even though no period was set");

        // Change cache policy, but do not set a expiry date
        $cp->setCachePolicy(CachePolicy::CACHE_PRIVATE);
        $actual = $cp->getHeaders();
        $this->assertEquals($expected, $actual, "Cache is not disabled even though no period was set");

        // Set a period
        $expires = 600;
        $cp->setExpiresInSeconds($expires);
        $dt = new DateTime();
        $dt->add(new DateInterval("PT" . $expires . "S"));

        $expected = [
            'Cache-Control' => 'private, max-age=' . $expires,
            'Pragma' => 'max-age=' . $expires,
            'Expires' => $dt->format('r')
        ];
        $actual = $cp->getHeaders();
        $this->assertEquals($expected, $actual, "Invalid headers for private cacheable");

        $cp->setCachePolicy(CachePolicy::CACHE_PUBLIC);
        $expected = [
            'Cache-Control' => 'public, max-age=' . $expires,
            'Pragma' => 'max-age=' . $expires,
            'Expires' => $dt->format('r')
        ];
        $actual = $cp->getHeaders();
        $this->assertEquals($expected, $actual, "Invalid headers for public cacheable");
    }
}
