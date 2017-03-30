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

namespace Wedeto\HTTP\Response;

use Wedeto\HTTP\Request;
use Wedeto\HTTP\CachePolicy;

abstract class Response extends \Exception
{
    /**
     * Available response mime types
     */
    protected $mime = array();

    /**
     * The cache policy for this response
     */
    protected $cache_policy = null;

    /**
     * @return int The HTTP Status code
     */
    public function getStatusCode()
    {
        return $this->getCode();
    }

    /**
     * Sets the HTTP Response code
     * @parma int $code The HTTP Response code (100-599)
     */
    public function setStatusCode(int $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Return the available mime types
     */
    public function getMimeTypes()
    {
        return array_keys($this->mime);
    }

    /**
     * Set the mime type of the response.
     * @param string $mime The mime type, that will be sent as the content-type
     */
    public function addMimeType(string $mime)
    {
        $this->mime[$mime] = true;
        return $this;
    }

    /**
     * Provide additional headers to be set on the request
     */
    public function getHeaders()
    {
        return array();
    }

    /**
     * Set the cache policy for this response
     * @param CachePolicy $policy The policy to set
     * @return Wedeto\HTTP\Response\Response Provides fluent interface
     */
    public function setCachePolicy(CachePolicy $policy)
    {
        $this->cache_policy = $policy;
        return $this;
    }

    /**
     * @return CachePolicy The configured CachePolicy. Null if not set
     */
    public function getCachePolicy()
    {
        return $this->cache_policy;
    }

    /**
     * Output the data to the client. This will be the very last method called
     * by the ResponseBuilder, and all output buffering will have been
     * disabled. Headers will have been sent, just send the output.
     */
    abstract public function output(string $mime);
}
