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

namespace WASP\HTTP\Response;

use Throwable;

use WASP\Util\Functions as WF;

class Error extends Response
{
    /** The user message may be shown to end-users, even if dev-mode is disabled */
    protected $user_message;

    /** The formatted response */
    protected $response = null;

    /**
     * Create the HTTP Error exception.
     * @param int $code The HTTP Response Code
     * @param string $error The error message
     * @param string $user_message The message for the user
     * @param Throwable $previous The previous exception
     */
    public function __construct(int $code, string $error, $user_message = null, $previous = null)
    {
        parent::__construct($error, $code, $previous);
        $this->user_message = $user_message;
    }

    /** 
     * @return string The user message accompanying this error
     */
    public function getUserMessage()
    {
        return $this->user_message;
    }

    /**
     * Get the formatted response, representing this error
     */
    public function getResponse()
    {
        if (empty($this->response))
            $this->response = new StringResponse(WF::str($this->getPrevious()), "text/plain");

        return $this->response;
    }

    /**
     * Get the available mime types. Delegated to the formatted response.
     */
    public function getMimeTypes()
    {
        return $this->getResponse()->getMimeTypes();
    }

    /**
     * Set the formatted response, representing this error
     * @param Response $response The formatted response
     */
    public function setResponse(Response $response)
    {
        if ($response instanceof Error)
            throw new \InvalidArgumentException("Cannot chain error responses"); //: " . WF::str($response));

        $this->response = $response;
    }

    /** 
     * Output the error in the specified mime type
     * @param string $mime The mime type for the response
     */
    public function output(string $mime)
    {
        $this->getResponse()->output($mime);
    }
}
