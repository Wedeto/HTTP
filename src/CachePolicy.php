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

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeInterval;
use DateInterval;

/**
 * Maintain cache settings and generate HTTP headers
 */ 
class CachePolicy
{
    /** The user agent should not cache this response */
    const CACHE_DISABLE = "no-cache";

    /** The user agent may cache this and intermediate proxies may also cache this */
    const CACHE_PUBLIC = "public";

    /** The user agent may cache this but intermediate proxies should not cache this */
    const CACHE_PRIVATE = "private";

    /** The amount of seconds until the cache expires */
    protected $expire_time = -1;

    /** The configured cache policy */
    protected $cache_policy = self::CACHE_DISABLE;

    /**
     * Set the exact moment when the cache should expire.
     * @param DateTimeInterface $dt When the cache should expire
     * @return CachePolicy Provides fluent interface 
     */
    public function setExpireDate(DateTimeInterface $dt)
    {
        $this->expire_time = (int)$dt->format('U') - time();
        return $this;
    }

    /**
     * Set the exact moment when the cache should expire.
     * @param int $seconds The amount of seconds this response is valid / should be cached
     * @return CachePolicy Provides fluent interface 
     */
    public function setExpiresInSeconds(int $seconds)
    {
        $this->expire_time = $seconds;
        return $this;
    }

    /**
     * Set the time until the cache should expire.
     * @param DateInterval $interval In what time the cache should expire.
     * @return CachePolicy Provides fluent interface 
     */
    public function setExpiresIn(DateInterval $interval)
    {
        $now = new DateTimeImmutable();
        $expire = $now->add($interval);
        $this->setExpiresInSeconds($expire->getTimestamp() - $now->getTimestamp());
        return $this;
    }

    /**
     * Set the when the cache should expire. Wrapper for setExpiresIn,
     * setExpiresInSeconds and setExpireDate, with type detection.
     *
     * @param mixed $period Can be int (seconds), DateTimeInterface or DateInterval.
     * @return CachePolicy Provides fluent interface 
     */
    public function setExpires($period)
    {
        if (is_int($period))
            return $this->setExpiresInSeconds($period);
        if ($period instanceof DateTimeInterface)
            return $this->setExpireDate($period);
        if ($period instanceof DateInterval)
            return $this->setExpiresIn($period);

        throw new \InvalidArgumentException("A DateTime, DateInterval or int number of seconds is required");
    }
    
    /**
     * @return int The number of seconds until the cache expires
     */
    public function getExpiresInSeconds()
    {
        return $this->expire_time;
    }

    /**
     * Set the cache policy for this response.
     * @param string $policy One of CachePolicy::CACHE_PUBLIC, CACHE_PRIVATE or CACHE_DISABLED.
     * @return CachePolicy Provides fluent interface
     */
    public function setCachePolicy(string $policy)
    {
        if ($policy !== self::CACHE_DISABLE && $policy !== self::CACHE_PUBLIC && $policy !== self::CACHE_PRIVATE)
            throw new \InvalidArgumentException("Invalid cache policy: " . $policy);

        $this->cache_policy = $policy;
        return $this;
    }

    /**
     * @return string The configured cache policy
     */
    public function getCachePolicy()
    {
        return $this->cache_policy;
    }
    
    /**
     * @return array The list of headers representing the cache configuration
     */
    public function getHeaders()
    {
        if ($this->cache_policy === self::CACHE_DISABLE || $this->expire_time <= 0)
        {
            return [
                'Cache-Control' => 'no-cache',
                'Pragma' => 'no-cache',
                'Expires' => date('r', 0)
            ];
        }

        $expires = $this->expire_time;
        $ts = time() + $expires;
        $expire_date = date('r', $ts);

        $h = array();
        $h['Pragma'] = 'max-age=' . $expires;
        $h['Cache-Control'] = $this->cache_policy . ', max-age=' . $expires;
        $h['Expires'] = $expire_date;

        return $h;
    }
}
