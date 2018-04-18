<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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

/**
 * A HTTP result. Contains the headers, the cookies and the response.
 */
class Result
{
    /** The cookies that will be sent to the client */
    private $cookies = [];

    /** The headers that will be sent to the client */
    private $headers = [];

    /** The cache policy */
    private $cache_policy = null;

    /** The response */
    private $response;

    /**
     * Set a header on the result
     * @param string $header The name of the header. Will be normalized
     * @param scalar $value The value for the header.
     * @return $this Provides fluent interface
     */
    public function setHeader(string $header, $value)
    {
        if (!is_scalar($value))
            throw new \InvalidArgumentException("Value should be a scalar");

        $header = static::normalizeHeader($header);
        $this->headers[$header] = $value;
        return $this;
    }

    /**
     * Remove a header from the list of headers to send to the client
     * 
     * @param string $header The header to unset.
     * @return $this Provides fluent interface
     */
    public function unsetHeader(string $header)
    {
        $header = static::normalizeHeader($header);
        unset($this->headers[$header]);
        return $this;
    }
    
    /**
     * Normalize the header: upper case at start and after each dash,
     * lower case for all other letters
     * @param string $name The name of the header
     * @return string The normalized header
     */
    public static function normalizeHeader(string $name)
    {
        $op = "";
        $uc = true;
        for ($i = 0; $i < strlen($name); ++$i)
        {
            // Add character
            $ch = substr($name, $i, 1);
            $op .= $uc ? strtoupper($ch) : strtolower($ch);

            // Upper case follows every dash
            $uc = $ch === "-";
        }
        return $op;
    }

    /**
     * Check if a header is present
     * @param string $header The name of the header
     * @return boolean True if the header is present, false if not
     */
    public function hasHeader($header)
    {
        return isset($this->headers[static::normalizeHeader($header)]);
    }

    /**
     * Get a header
     * @param string $header The name of the header
     * @return string The value for the header, null if not present
     */
    public function getHeader($header)
    {
        $header = static::normalizeHeader($header);
        return $this->headers[$header] ?? null;
    }

    /**
     * Add a cookie to the Result
     * @param Wedeto\HTTP\Cookie $cookie The cookie to add
     * @return $this Provides fluent interface
     */
    public function addCookie(Cookie $cookie)
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    /**
     * Delete a cookie from the result
     * @param string $name The name of the cookie
     * @return $this Provides fluent interface
     */
    public function deleteCookie(string $name)
    {
        unset($this->cookies[$name]);
        return $this;
    }

    /**
     * Check if a cookie with the same has been set.
     *
     * @param string $name The name of the cookie
     * @return bool True if the cookie is present, false if not
     */
    public function hasCookie(string $name)
    {
        return isset($this->cookies[$name]);
    }

    /**
     * Get a cookie
     * @param string $name The name of the cookie
     * @return Wedeto\HTTP\Cookie The cookie, or null if not present
     */
    public function getCookie(string $name)
    {
        return $this->cookies[$name] ?? null;
    }

    /**
     * @return array all the cookies set for the result
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * Set the response of the Result
     *
     * @param Wedeto\HTTP\Response\Response $response The response to set
     * @return $this Provides fluent interface
     */
    public function setResponse(Response\Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * @return Wedeto\HTTP\Response\Response The response of the result
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array The list of name -> value header mappings
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the cache policy for this result. This will be used if the response does
     * not set a cache policy as that will always override the cache policy on the
     * result.
     *
     * @return CachePolicy The cache policy
     */
    public function setCachePolicy(CachePolicy $policy)
    {
        $this->cache_policy = $policy;
        return $this;
    }

    /**
     * Returns the cache policy for this result. If the response has a cache policy,
     * it is used. Otherwise, any cache policy set on the result itself is used.
     *
     * @return CachePolicy The cache policy
     */
    public function getCachePolicy()
    {
        if (!empty($this->response))
        {
            $policy = $this->response->getCachePolicy();
            if (!empty($policy))
                return $policy;
        }
        return $this->cache_policy;
    }

    /**
     * @return array The list of name -> value header mappings, including headers from the response,
     * and headers from the cache policy.
     */
    public function getAllHeaders()
    {
        $headers = $this->headers;
        if (!empty($this->response))
        {
            foreach ($this->response->getHeaders() as $key => $value)
            {
                $headers[$key] = $value;
            }
        }

        $cp = $this->getCachePolicy();
        if (!empty($cp))
        {
            foreach ($cp->getHeaders() as $key => $value)
                $headers[$key] = $value;
        }

        return $headers;
    }
}
