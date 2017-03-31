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

use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Hook;
use Wedeto\IO\MimeTypes;

use Wedeto\HTTP\Response\Response;
use Wedeto\HTTP\Response\Error;

/**
 * Create and output a response
 */
class Responder
{
    use LoggerAwareStaticTrait;

    /** The headers to send to the client */
    protected $headers = array();

    /** The HTTP Response Code */
    protected $response_code;

    /** The cookies to send to the client */
    protected $cookies = array();

    /** The request this is a response to */
    protected $request;

    /** The response to send to the client */
    protected $response = null;

    /** The caching policy */
    protected $cache_policy = null;

    /**
     * Create the response to a Request
     * @param Request $request The request this is the response to
     */
    public function __construct(Request $request)
    {
        self::getLogger();
        $this->request = $request;
    }

    /**
     * @return Wedeto\HTTP\Request The HTTP Request instance
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the request instance
     * @param Request $request The HTTP Request instance
     * @return Wedeto\HTTP\Responder Provides fluent interface
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Set the response object
     *
     * @param Response $response The final response
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        $cp = $response->getCachePolicy();
        if ($cp !== null)
            $this->setCachePolicy($cp);

        return $this;
    }

    /** 
     * Set the Cache Policy object
     *
     * @param CachePolicy $policy The policy
     * @return WAPS\HTTP\Responder Provides fluent interface
     */
    public function setCachePolicy(CachePolicy $policy)
    {
        $this->cache_policy = $policy;
        return $this;
    }

    /**
     * @return Cache Policy The active cache policy object. Null if none was set
     */
    public function getCachePolicy()
    {
        return $this->cache_policy;
    }

    /**
     * @return Wedeto\HTTP\Response The current response object
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Add a cookie that should be sent to the client
     * @param Cookie $cookie The cookie to send
     */
    public function addCookie(Cookie $cookie)
    {
        $this->cookies[$cookie->getName()] = $cookie;
        return $this;
    }

    /**
     * @return array The cookies that should be sent to the client
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /** 
     * Set a header
     * @param string $name The name of the header
     * @param string $value The value
     */
    public function setHeader(string $name, string $value)
    {
        // Make sure the word has no spaces but dashes instead, and is
        // Camel-Cased. The dashes are first replaced with spaces to let
        // ucwords function properly, and afterwards all spaces are converted
        // to dashes.
        $name = ucwords(strtolower(str_replace('-', ' ', $name)));
        $name = str_replace(' ', '-', $name);

        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * @return array all configured headers
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Set the HTTP Response code
     *
     * @param int $code The HTTP response code
     * @return Wedeto\HTTP\Responder Provides fluent interface
     */
    public function setResponseCode(int $code)
    {
        if ($code < 100 || $code > 599)
        {
            $err = new \InvalidArgumentException("Attempting to set status code to $code");
            self::$logger->critical("Invalid status {0}: {1}", [$code, $err]);
            $this->response_code = 500;
        }
        else
        {
            $this->response_code = $code;
        }
        return $this;
    }

    /**
     * @return int The response code
     */
    public function getResponseCode()
    {
        return $this->response_code;
    }

    /**
     * Close all active output buffers and log their contents
     */
    public function endAllOutputBuffers($lvl = 0)
    {
        $ob_cnt = 0;
        while (ob_get_level() > $lvl)
        {
            ++$ob_cnt;
            $contents = ob_get_contents();
            ob_end_clean();
        
            if (self::$logger instanceof \Psr\Log\NullLogger)
                continue;

            $lines = explode("\n", $contents);
            foreach ($lines as $n => $line)
            {
                if (!empty($line))
                    self::$logger->debug("Script output: {0}/{1}: {2}", [$ob_cnt, $n + 1, $line]);
            }
        }
    }
    
    /** 
     * Prepare the output before sending it, run hooks, collect headers and
     * finally call doRespond which produces the output.
     */
    public function respond()
    {
        // Make sure there always is a response
        if (null === $this->response)
            $this->response = new Error(500, "No output produced");
        
        // Close and log all script output that hasn't been cleaned yet
        $this->endAllOutputBuffers();

        // Add Content-Type mime header
        $mime = $this->response->getMimeTypes();
        if (empty($mime))
        {
            $mime = Request::cli() ? "text/plain" : "text/html";
        }
        elseif (is_array($mime))
        {
            $types = $mime;
            $mime = null;
            foreach ($types as $type)
            {
                if ($this->request->isAccepted($type))
                {
                    $mime = $type;
                    break;
                }
            }
        }

        // If mime is not accepted, return a 406 response
        if (empty($mime) || !$this->request->isAccepted($mime))
        {
            $mime = 'text/html';
            $this->response = new Error(406, "Not Acceptable", td('No acceptable response can be offered', 'Wedeto.HTTP'));
        }

        // Execute hooks
        $hook_params = [
            'responder' => $this,
            'request' => $this->request,
            'mime' => $mime,
        ];

        $hook_params = Hook::execute('Wedeto.HTTP.Responder.Respond', $hook_params);

        // Check if mime type was updated
        if (is_string($hook_params['mime']))
            $mime_charset = $hook_params['mime'];

        $mime_charset = $mime;
        if (MimeTypes::isPlainText($mime))
            $mime_charset .= '; charset=utf-8';

        // Set mime type
        $this->setHeader('Content-Type', $mime_charset);

        // Add headers from response to the final response
        foreach ($this->response->getHeaders() as $key => $value)
            $this->setHeader($key, $value);

        if ($this->cache_policy !== null)
        {
            foreach ($this->cache_policy->getHeaders() as $key => $value)
                $this->setHeader($key, $value);
        }
        
        // Store the response code
        $this->setResponseCode($this->response->getStatusCode());

        // All preparational work is done, time to send stuff to the client
        $this->doOutput($mime);
    }

    /**
     * This method sends data to the client after all preparational work has
     * been done.
     *
     * @param string $mime The mime-type of the response that has been selected
     *
     * @codeCoverageIgnore This method is full of 'side-effects': output,
     *                     sending HTTP headers, sending cookies, setting status
     *                     code.
     */
    protected function doOutput(string $mime)
    {
        // Set HTTP response code
        http_response_code($this->response_code);

        // Set headers
        if (!headers_sent())
        {
            foreach ($this->headers as $name => $value)
                header($name . ': ' . $value);
        
            // Set cookies
            foreach ($this->cookies as $cookie)
            {
                setcookie(
                    $cookie->getName(),
                    $cookie->getValue(),
                    $cookie->getExpires(),
                    $cookie->getPath(),
                    $cookie->getDomain(),
                    $cookie->getSecure(),
                    $cookie->getHTTPOnly()
                );
            }
        }
        else
            self::$logger->critical('Headers were already sent when Responder wants to send them');

        // Perform output
        $this->response->output($mime);

        // We're done
        self::$logger->debug("** Finished processing request to {0}", [$this->request->url]);
        die();
    }
}
