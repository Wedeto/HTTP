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

/**
 * A StringResponse is text or other plain text data generated during the
 * script. This can be text/plain or text/html, for example.
 */
class StringResponse extends Response
{
    /** The output string */
    protected $output = array();

    /**
     * Create using a string
     * @param string $str The output
     */
    public function __construct($output, string $mime = "text/html")
    {
        $this->setOutput($output, $mime);
        $this->code = 200;
    }

    /** 
     * Set or replace the output for a content type
     * @param mixed $str The text to add. This can be a string, a callback
     *                   function returning a string or an object with a
     *                   __toString method.
     * @param string $mime The mime-type for the content
     * @return StringResponse Provides fluent interface
     */
    public function setOutput($output, string $mime = "text/html")
    {
        if (
            !is_string($output) && !is_callable($output) 
            && !(is_object($output) && method_exists($output, '__toString'))
        )
        {
            throw new \InvalidArgumentException(
                "Output must be text, string-castable object or valid callback"
            );
        }
        $this->addMimeType($mime);
        $this->output[$mime] = $output;
        return $this;
    }

    /**
     * Append a string to the current output
     * @param string $str The string to add
     * @return StringResponse Provides fluent interface
     */
    public function append(string $str, string $mime = "text/html")
    {
        // To append, we need to make sure we have a string first
        if (empty($this->output[$mime]) || !is_string($this->output[$mime]))
            $this->output[$mime] = $this->getOutput($mime);

        $this->output[$mime] .= $str;
        return $this;
    }

    /**
     * @return string The output
     */
    public function getOutput(string $mime = 'text/html')
    {
        // Unknown mime type
        if (empty($this->output[$mime]))
            return null;

        if (is_callable($this->output[$mime]))
            return (string)($this->output[$mime]());

        if (is_object($this->output[$mime]))
            return $this->output[$mime]->__toString();

        return $this->output[$mime];
    }

    /**
     * Write the string to the script output
     * @return StringResponse Provides fluent interface
     */
    public function output(string $mime)
    {
        $output = $this->getOutput($mime);
        if (empty($output))
            $output = "Unknown mime type requested";

        echo $output;
        return $this;
    }
}
