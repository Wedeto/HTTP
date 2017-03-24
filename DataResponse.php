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

use WASP\Util\Dictionary;
use WASP\Log\Logger;
use WASP\Log\LoggerAwareStaticTrait;
use WASP\DefVal;
use WASP\Util\Functions as WF;
use Throwable;
use InvalidArgumentException;

/**
 * DataResponse represents structured data, such as JSON or XML. The
 * ResponseBuilder will decide what format to format the data in.
 */
class DataResponse extends Response
{
    use LoggerAwareStaticTrait;

    private $dictionary;

    public static $representation_types = array(
        'application/json' => "JSON",
        'application/xml' => "XML",
        'text/html' => "HTML",
        'text/plain' => "PLAIN"
    );

    public function __construct($dict, $status_code = 200)
    {
        parent::__construct("DataResponse", $status_code);
        if ($dict instanceof Dictionary)
            $this->dictionary = $dict;
        else
            $this->dictionary = new Dictionary(WF::to_array($dict));
    }

    public function getDictionary()
    {
        return $this->dictionary;
    }

    public function getMimeTypes()
    {
        return array_keys(self::$representation_types);
    }

    public function output(string $mime)
    {
        $type = isset(self::$representation_types[$mime]) ? self::$representation_types[$mime] : "Null";
        $classname = "WASP\\IO\\DataWriter\\" . $type . "Writer";

        $config = $this->getRequest()->config;
        $pprint = $config->getBool('site', 'dev', new DefVal(false));
        
        $output = "";
        try 
        {
            if (class_exists($classname))
            {
                $writer = new $classname($pprint);
                $op = fopen("php://output", "w");
                $writer->write($this->dictionary, $op);
                fclose($op);
            }
            else
                throw new InvalidArgumentException("Response writer $classname does not exist");
        }
        catch (Throwable $e)
        {
            // Bad. Attempt to override response type if still possible
            self::$logger->critical('Could not output data, exception occured while writing: {0}', [$e]);
            Error::fallbackWriter($this->dictionary, $mime);
        }
    }
}

// @codeCoverageIgnoreStart
DataResponse::setLogger();
// @codeCoverageIgnoreEnd
