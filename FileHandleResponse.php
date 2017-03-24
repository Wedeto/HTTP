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

use WASP\Log\LoggerAwareStaticTrait;

/**
 * Output a file, given an opened file handle. 
 */
class FileHandleResponse extends Response
{
    use LoggerAwareStaticTrait;

    /** The file handle from which to read the data */
    protected $filehandle;

    /** The filename for the file that is sent to the client */
    protected $output_filename;

    /** Whether to sent as download or embedded */
    protected $download;

    /** The length in bytes of the content being served */
    protected $length = null;


    /**
     * Create the response using the file name
     * @param string $filehandle The open file handle to pass through to the client
     * @param string $output_filename The filename to use in the output
     * @param string $mime The mime-type to use for the transfer
     */
    public function __construct($filehandle, string $output_filename, string $mime, bool $download = false)
    {
        $this->filehandle = $filehandle;
        $this->output_filename = $output_filename;
        $this->mime[$mime] = true;
        $this->download = $download;
        $this->code = 200;
    }

    /**
     * Set the length in bytes of the response
     * @param int $bytes The number of bytes of the download
     * @return FileHandleResponse Provides fluent interface
     */
    public function setLength(int $bytes)
    {
        $this->length = $bytes;
    }

    /**
     * @return resource The opened file handle that should be passed to the client
     */
    public function getFileHandle()
    {
        return $this->filehandle;
    }

    /** 
     * @return string The filename to sent to the client
     */
    public function getOutputFileName()
    {
        return $this->output_filename;
    }

    /**
     * @return array The relevant headers
     */
    public function getHeaders()
    {
        $h = array();

        $disposition = $this->download ? "download" : "inline";
        $h['Content-Disposition'] = $disposition . '; filename=' . $this->output_filename;

        if ($this->length)
            $h['Content-Length'] = $this->length;
        return $h;
    }

    /**
     * @return bool True if the file should be presented as download, false if
     *              the browser may render it directly
     */
    public function getDownload()
    {
        return $this->download;
    }

    public function output(string $mime)
    {
        $bytes = fpassthru($this->filehandle);
        if (!empty($this->length) && $bytes != $this->length)
        {
            self::$logger->warning(
                "FileHandleResponse was specified to send {0} bytes but {1} were actually transfered of file {2}", 
                [$this->length, $bytes, $this->output_filename]
            );
        }
    }
}

// @codeCoverageIgnoreStart
FileHandleResponse::setLogger();
// @codeCoverageIgnoreEnd
