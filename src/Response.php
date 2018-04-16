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
 * A HTTP response. Contains the headers, the cookies and the content.
 */
class Response
{
    /** The cookies that will be sent to the client */
    private $cookies = [];

    /** The headers that will be sent to the client */
    private $headers = [];

    /** The body */
    private $content;

    /**
     * Set a header on the response
     * @param string $header The name of the header. Will be normalized
     * @param scalar $value The value for the header.
     * @return $this Provides fluent interface
     */
    public function setHeader(string $header, $value)
    {
        if (!is_scalar($value))
            throw new \InvalidArgumentException("Value should be a scalar");

        $header = static::normalizeHeader($name);
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
            $ch = substr($i, $i, 1);
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
        return isset($this->headers[static::normalizeHeader[$header]]);
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
     * Add a cookie to the Response
     * @param Wedeto\HTTP\Cookie $cookie The cookie to add
     * @return $this Provides fluent interface
     */
    public function addCookie(Cookie $cookie)
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    /**
     * Delete a cookie from the response
     * @param string $name The name of the cookie
     * @return $this Provides fluent interface
     */
    public function deleteCookie(string $name)
    {
        unset($this->cookies[$name]);
        return $this;
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
     * Set the content of the Response
     *
     * @param Wedeto\HTTP\Response\Response $response The response to set
     * @return $this Provides fluent interface
     */
    public function setContent(Response\Response $response)
    {
        $this->content = $response;
        return $this;
    }

    /**
     * @return Wedeto\HTTP\Response\Response The content of the response
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return array The list of name -> value header mappings
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return array The list of name -> value header mappings, including headers from the content.
     */
    public function getAllHeaders()
    {
        $headers = $this->headers;
        if (!empty($this->content))
        {
            foreach ($this->content->getHeaders() as $key => $value)
            {
                $headers[$key] = $value;
            }
        }
        return $headers;
    }
}
