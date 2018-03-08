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

use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Dictionary;
use Wedeto\Util\DI\DI;
use Wedeto\FileFormats\ReaderFactory;
use Wedeto\IO\FileType;

/**
 * Request body encapsulates a HTTP request body. It extracts
 * the content type and length from a request, and provides
 * access the posted data.
 */
class RequestBody
{
    use LoggerAwareStaticTrait;

    /** The url to the input stream. Mainly useful for testing */
    protected $stream_url = "php://input";

    /** The MIME type of the request body */
    protected $content_type;
    
    /** The length of the request body */
    protected $content_length;

    /** The content stream */
    protected $content_stream;

    /** The buffered content */
    protected $content;

    /**
     * Create the RequestBody
     * 
     * @param Dictionary $server The server vars of the request
     */
    public function __construct(Dictionary $server)
    {
        try
        {
            $this->content_type = strtolower($server['HTTP_CONTENT_TYPE']);
            $this->content_length = $server->getInt('HTTP_CONTENT_LENGTH');
        }
        catch (\OutOfRangeException $e)
        {
            $this->content_type = $this->content_length = null;
        }
    }

    /**
     * Change the input stream URL
     *
     * @param  string $URL The URL to use as input stream. Default: php://input
     * @return $this Provides fluent interface.
     */
    public function setInputStreamURL(string $url)
    {
        $this->stream_url = $url;
        return $this;
    }

    /**
     * Whether any body was present in the request. Body data is only considered
     * if the body a content length and a content type header are present.
     */
    public function isPresent()
    {
        return !empty($this->content_type) && !empty($this->content_length);
    }

    /**
     * @return string the content type type as set by the client
     */
    public function getContentType()
    {
        return $this->content_type;
    }

    /**
     * @return int The content length as set by the client
     */
    public function getContentLength()
    {
        return $this->content_length;
    }

    /**
     * @return resource A stream seeked to the beginning of the submitted data
     */
    public function getContentStream()
    {
        if ($this->content_stream === null)
        {
            $this->content_stream = fopen($this->stream_url, "r");
        }
        fseek($this->content_stream, 0);
        return $this->content_stream;
    }

    /**
     * Get the raw content as a string
     * 
     * @param int $max_length The maximum amount of bytes to read.
     * @return string The read data
     */
    public function getRawContent(int $max_length = -1)
    {
        // Set maximum length based on parameter or provided content_length
        $max_length = $max_length < 0 ? $this->content_length : $max_length;
        // Limit to a maximum of 4MiB
        $max_length = min(4096 * 1024, $max_length, $this->content_length);

        $str = $this->getContentStream();
        $data = stream_get_contents($str, $this->content_length);
        $size = strlen($data);
        if ($size < $max_length)
        {
            self::getLogger()->warning(
                "Less data was posted than promised. Content-length was {} but only {} bytes were received",
                [$this->content_length, $size]
            );
        }
        return $data;
    }

    /**
     * Return the parsed content. The FileFormats readers will be used to
     * parse the data and store it in a dictionary.
     * 
     * @return Dictionary The parsed data
     */
    public function getParsedContent()
    {
        $filetype = FileType::getExtension($this->content_type);

        if (empty($filetype))
            throw new ParseException("Unknown content type");

        $filename = "content." . $filetype;
        $reader = ReaderFactory::factory($filename);
        $parsed = $reader->readFileHandle($this->getContentStream());
        return new Dictionary($parsed);
    }
}
