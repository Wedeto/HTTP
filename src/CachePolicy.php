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

class CachePolicy
{
    const CACHE_DISABLE = "no-cache";
    const CACHE_PUBLIC = "public";
    const CACHE_PRIVATE = "private";

    protected $expire_time = -1;
    protected $cache_policy = self::CACHE_DISABLE;

    public function setExpireDate(DateTimeInterface $dt)
    {
        $this->expire_time = (int)$dt->format('U') - time();
        return $this;
    }

    public function setExpiresInSeconds(int $seconds)
    {
        $this->expire_time = $seconds;
        return $this;
    }

    public function getExpiresInSeconds()
    {
        return $this->expire_time;
    }

    public function setCachePolicy(string $policy)
    {
        if ($policy !== self::CACHE_DISABLE && $policy !== self::CACHE_PUBLIC && $policy !== self::CACHE_PRIVATE)
            throw new \InvalidArgumentException("Invalid cache policy: " . $policy);

        $this->cache_policy = $policy;
        return $this;
    }

    public function getCachePolicy()
    {
        return $this->cache_policy;
    }
    
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
        //$h['Cache-Control'] = $this->cache_policy . ', max-age=' . $expires;
        $h['Cache-Control'] = 'max-age=' . $expires;
        $h['Pragma'] = $h['Cache-Control'];
        $h['Expires'] = $expire_date;

        return $h;
    }
}
