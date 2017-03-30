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

use Wedeto\Util\Functions as WF;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\DefVal;

/**
 * Output a file, given its filename. The handler may decide to output the
 * file using X-SendFile header, or by opening the file and passing the
 * contents.
 */
class FileResponse extends Response
{
    use LoggerAwareStaticTrait;

    /** The filename of the file to send */
    protected $filename;

    /** The filename for the file that is sent to the client */
    protected $output_filename;

    /** Whether to sent as download or embedded */
    protected $download;

    /** The size of the file in bytes */
    protected $length;

    /** Using X-Sendfile or not? Determined in getHeaders() */
    protected $xsendfile = false;

    /**
     * Create the response using the file name
     * @param string $filename The file to load / send
     * @param string $output_filename The filename to use in the output
     */
    public function __construct(string $filename, string $output_filename = "", bool $download = false)
    {
        $this->filename = $filename;
        $this->length = filesize($filename);
        if (empty($output_filename))
            $output_filename = basename($this->filename);
        $this->output_filename = $output_filename;
        $this->code = 200;
        $this->download = $download;
    }

    /**
     * @return string The path of the file to sent
     */
    public function getFileName()
    {
        return $this->filename;
    }

    /** 
     * @return string The filename to sent to the client
     */
    public function getOutputFileName()
    {
        return $this->output_filename;
    }

    /**
     * @return bool True if the file should be presented as download, false if
     *              the browser may render it directly
     */
    public function getDownload()
    {
        return $this->download;
    }

    /**
     * Enable or disable use of X-Sendfile header. When this is enabled,
     * the file will be pointed to by X-Sendfile, from which it is assumed
     * the webserver will handle the file transfer. When this is disabled,
     * the file will be output by this script.
     * 
     * @param bool $use True to use X-Sendfile, false to send directly
     * @return Wedeto\HTTP\Response\FileResponse Provides fluent interface
     */
    public function setUseXSendFile(bool $use)
    {
        $this->xsendfile = $use;
        return $this;
    }

    /**
     * @return bool True if X-Sendfile is used, false if not
     */
    public function getUseXSendFile()
    {
        return $this->xsendfile;
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

        if ($this->xsendfile)
            $h['X-Sendfile'] = $this->filename;

        return $h;
    }

    /**
     * Serve the file to the user
     */
    public function output(string $mime)
    {
        // Nothing to output, the webserver will handle it
        if ($this->xsendfile)
            return;

        $bytes = readfile($this->filename);
        if (!empty($this->length) && $bytes != $this->length)
        {
            self::getLogger()->warning(
                "FileResponse promised to send {0} bytes but {1} were actually transfered of file {2}", 
                [$this->length, $bytes, $this->output_filename]
            );
        }
    }
}
