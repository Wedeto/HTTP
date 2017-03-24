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

use WASP\Log\DevLogger;

/**
 * Log all output of the current script to memory, and attach a log to the end
 * of the response
 */
class DevLoggerHook implements ResponseHookInterface
{
    protected $devlogger = null;

    public function __construct()
    {
        $this->devlogger = DevLogger::getInstance();
        if ($this->devlogger === null)
            $this->devlogger = new DevLogger(Psr\Log\LogLevel::INFO);
    }

    /**
     * Attach to the Response to add the log to the end of each HTML and
     * JSON/XML request Do note that this should only be used for development
     * as the log may expose sensitive information to the client.
     * 
     * @param Request $request The request being answerd
     * @param Response $response The response so far
     */
    public function executeHook(Request $request, Response $response, string $mime)
    {
        $now = microtime(true);
        $start = $request->getStartTime();
        $duration = round($now - $start, 3);

        if ($response instanceof DataResponse)
        {
            $response->getDictionary()->set('devlog', $this->log);
        }
        elseif ($response instanceof StringResponse)
        {
            $mime = $response->getMimeTypes();
            $buf = fopen('php://memory', 'rw');

            if (in_array('text/html', $mime))
            {
                fprintf($buf, "\n<!-- DEVELOPMENT LOG-->\n");
                fprintf($buf, "<!-- Executed in: %s s -->\n", $duration);
                fprintf($buf, "<!-- Route resolved: %s -->\n", $request->route);
                fprintf($buf, "<!-- App executed: %s -->\n", $request->app);
                $width = strlen((string)count($this->log)) + 1;
                foreach ($this->log as $no => $line)
                    fprintf($buf, "<!-- %0" . $width . "d: %s -->\n", $no, htmlentities($line));

                fseek($buf, 0);
                $output = fread($buf, 100 * 1024);
                $response->append($output, 'text/html');
            }

            if (in_array('text/plain', $mime))
            {
                fprintf($buf, "\n// DEVELOPMENT LOG\n");
                fprintf($buf, "// Executed in: %s\n", Logger::str($duration));
                fprintf($buf, "// Route resolved: %s\n", $request->route);
                fprintf($buf, "// App executed: %s>\n", $request->app);
                foreach ($this->log as $no => $line)
                    fprintf($buf, "// %05d: %s\n", $no, $line);

                fseek($buf, 0);
                $output = fread($buf, 100 * 1024);
                $response->append($output, 'text/plain');
            }
        }
    }

