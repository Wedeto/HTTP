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

/**
 * A representation of a HTTP cookie, to be set in the response
 */
class Cookie
{
    /** The name of the cookie */
    private $name;

    /** The value of the cookie */
    private $value;

    /** When the cookie expires */
    private $expires;

    /** The domain to which the cookie is sent. It will also be sent to
     * subdomains of this domain */
    private $domain;

    /** The cookie path, where the browser should sent the cookie. Paths with a
     * different prefix will not receive the cookie. */
    private $path;

    /** Secure cookies are transferred using HTTPS only */
    private $secure;

    /** The HTTPOnly flag determines if the cookie is sent using HTTP only or
     * is also available to javascript */
    private $httponly;

    /**
     * Construct the object given the name and value
     * @param string $name The name of the cookie
     * @param string $value The value of the cookie
     */
    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
        $this->httponly = true;
    }

    /**
     * Set the name of the cookie
     * @param string $name The name of the cookie
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setName(string $name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string The name of the cookie
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set the value for the cookie
     * @param string $value The value
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setValue(string $value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return string The value for the cookie
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Set the HTTPOnly flag: whether to sent this cookie only on HTTP requests
     * or also expose it to scripts.
     * @param bool $httponly Value for HTTPOnly
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setHTTPOnly(bool $httponly)
    {
        $this->httponly = $httponly;
        return $this;
    }

    /**
     * @return bool The HTTPOnly flag: whether to sent this cookie only on HTTP
     * requests or also expose it to scripts.
     */
    public function getHTTPOnly()
    {
        return $this->httponly;
    }

    /**
     * Set the secure flag: whether to transfer the cookie over HTTPS only
     * @param bool $secure The secure flag
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setSecure(bool $secure)
    {
        $this->secure = $secure;
    }

    /**
     * @return bool If secure flag is enabled or not
     */
    public function getSecure()
    {
        return $this->secure;
    }

    /**
     * Set the cookie domain: hostnames where the cookie should be sent by the
     * client. It will also be sent to subdomains.
     * @param string $domain The cookie domain
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */ 
    public function setDomain(string $domain)
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     *  @return string The cookie domain: hostnames where the cookie should be
     *  sent by the client.
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set the domain and path using a URL
     * @param Wedeto\HTTP\URL The URI to use as the cookie domain
     */
    public function setURL(URL $url)
    {
        $this->domain = $url->host;
        $this->path = $url->path;
        $this->secure = $url->secure;
        return $this;
    }

    public function getURL()
    {
        return new URL($this->domain . $this->path);
    }

    /**
     * Set the Cookie path: the path at or under which the client should sent
     * this cookie. Paths with a different prefix will not receive the cookie. 
     * @param string $path The cookie path
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setPath(string $path)
    {
        $this->path = '/' . trim($path, '/');
        return $this;
    }

    /**
     * @return string The Cookie path: the path at or under which the client
     *                should sent this cookie. Paths with a different prefix
     *                will not receive the cookie. 
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set expiry date by a DateInterval, relative to the current time
     * @param DateInterval $interval After how much time the cookie should expire
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setExpiresIn(DateInterval $interval)
    {
        $this->expires = new DateTime();
        $this->expires->add($interval);
        return $this;
    }

    /**
     * Set the expiry date by a DateTime, the moment when the cookie will expire
     * @param DateTime The date to use as expiry date
     * @return Wedeto\HTTP\Cookie Provides fluent interface
     */
    public function setExpires(DateTime $date)
    {
        $this->expires = $date;
        return $this;
    }

    /**
     * Set the expiry date so that the cookie will expire immediately
     */
    public function setExpiresNow()
    {
        $this->expires = new DateTime('@0');
        return $this;
    }

    public function getExpires()
    {
        return $this->expires->getTimestamp();
    }
    
}
