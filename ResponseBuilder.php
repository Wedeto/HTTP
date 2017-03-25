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

namespace WASP\HTTP;

use WASP\AssetManager;
use WASP\Log\Logger;
use WASP\Log\DevLogger;
use WASP\Util\LoggerAwareStaticTrait;

use DateTime;
use DateInterval;
use Throwable;

/**
 * Create and output a response
 */
class ResponseBuilder
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

    /** The hooks to execute before outputting the response */
    protected $hooks = array();

    /** The asset manager manages injection of CSS and JS script inclusion */
    protected $asset_manager = null;

    /**
     * Create the response to a Request
     * @param Request $request The request this is the response to
     */
    public function __construct(Request $request)
    {
        self::getLogger();
        $this->request = $request;

        // Check for a Dev-logger
        $devlogger = DevLogger::getInstance();
        if ($devlogger)
            $this->addHook(new DevLogHook($devlogger));

        $this->asset_manager = new AssetManager($request);
        $this->addHook($this->asset_manager);

        $cfg = $request->config;
        if ($cfg !== null)
        {
            $this->asset_manager->setMinified(!$cfg->dget('site', 'dev', false));
            $this->asset_manager->setTidy($cfg->dget('site', 'tidy-output', false));
        }
    }

    /**
     * Set the response by any throwable object. Any non-Response objects are wrapped
     * in a WASP\HTTP\Error with status code 500.
     *
     * @return WASP\HTTP\ResponseBuilder Provides fluent interface
     */
    public function setThrowable(Throwable $exception)
    {
        if (!($exception instanceof Response))
        {
            self::getLogger()->error("Exception: {exception}", ["exception" => $exception]);

            // Wrap the error in a HTTP Error 500
            $exception = new Error(500, "An error occured", $user_message = "", $exception);
        }

        $this->setResponse($exception);
        return $this;
    }

    /**
     * Set the response object
     *
     * @param Response $response The final response
     */
    protected function setResponse(Response $response)
    {
        $this->response = $response;
        $response->setRequest($this->request);
        return $this;
    }

    /**
     * @return WASP\HTTP\Response The current response object
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return WASP\AssetManager The asset manager for scripts and CSS
     */
    public function getAssetManager()
    {
        return $this->asset_manager;
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
     * @return WASP\HTTP\ResponseBuilder Provides fluent interface
     */
    public function setResponseCode(int $code)
    {
        if ($code < 100 || $code > 599)
        {
            $err = new \InvalidArgumentException("Attempting to set status code to $code");
            self::getLogger()->critical("Invalid status {0}: {1}", [$code, $err]);
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
     * Add a hook to the ResponseHooks - these hooks will be executed just
     * before output begins. This can be used to inject or modify output.
     * @param ResponseHookInterface $hook The hook to add
     */
    public function addHook(ResponseHookInterface $hook)
    {
        $this->hooks[] = $hook;
        return $this;
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
        
            $lines = explode("\n", $contents);
            foreach ($lines as $n => $line)
            {
                if (!empty($line))
                    self::getLogger()->debug("Script output: {0}/{1}: {2}", [$ob_cnt, $n + 1, $line]);
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
            $this->response = new Error(406, "Not Acceptable", td('No acceptable response can be offered', 'core'));
        }

        $mime_charset = $mime;
        if (ResponseTypes::isPlainText($mime))
            $mime_charset .= '; charset=utf-8';

        $this->setHeader('Content-Type', $mime_charset);
            
        // Allow the Response to transform itself into a different response,
        // e.g. the ErrorResponse will want to produce DataOutput or StringOutput
        // depending on the mime type.
        try
        {
            $transformed = $this->response->transformResponse($mime);
            if ($transformed instanceof Response)
                $this->response = $transformed;
        }
        catch (Throwable $e)
        {} // Proceed unmodified

        // Execute hooks
        foreach ($this->hooks as $hook)
        {
            try
            {
                $hook->executeHook($this->request, $this->response, $mime);
            }
            catch (Throwable $e)
            {
                self::getLogger()->alert('Error while running hooks: {0}', [$e]);
                $this->response = new Error(500, "Error while running hooks", $e);
                $this->response = $this->response->transformResponse($mime);
            }
        }

        // Add headers from response to the final response
        foreach ($this->response->getHeaders() as $key => $value)
            $this->setHeader($key, $value);
        
        // Store the response code
        $this->setResponseCode($this->response->getStatusCode());

        // All preparational work is done, time to send stuff to the client
        $this->doOutput($mime);
    // Bug in xdebug < 2.4.1
    // @codeCoverageIgnoreStart
    }
    // @codeCoverageIgnoreEnd

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
            self::getLogger()->critical('Headers were already sent when ResponseBuilder wants to send them');

        // Perform output
        $this->response->output($mime);

        // We're done
        self::getLogger()->debug("** Finished processing request to {0}", [$this->request->url]);
        die();
    }
}
