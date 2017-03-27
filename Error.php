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
use WASP\Util\LoggerAwareStaticTrait;
use WASP\Util\Functions as WF;

use WASP\FileFormats\WriterFactory;

use Throwable;

class Error extends Response
{
    use LoggerAwareStaticTrait;

    /** The nesting counter avoids nesting due to errors */
    private static $nesting_counter = 0;
    
    /** The user message may be shown to end-users, even if dev-mode is disabled */
    private $user_message;

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
     * @return array The list of available mime types
     */
    public function getMimeTypes()
    {
        $types = WriterFactory::getAvailableWriters();

        // Remove HTML if present
        unset($types['text/html']);

        // Get the keys and add HTML and plain to the start
        $keys = array_keys($types);
        array_unshift($keys, 'text/plain');
        array_unshift($keys, 'text/html');
        return $keys;
    }

    /** 
     * Output the error in the specified mime type
     * @param string $mime The mime type for the response
     */
    public function output(string $mime)
    {
        if ($mime === "text/html" || $mime === "text/plain")
            $this->toStringResponse($mime)->output($mime);
        else
            $this->toDataResponse($mime)->output($mime);
    }

    /** 
     * Transform the response into a StringResponse or DataResponse,
     * based on the preferred mime-type, returning the new Response.
     *
     * @param string $mime The mime type that will be output
     * @return Response A different response type, based on the mime type
     */
    public function transformResponse(string $mime)
    {
        if ($mime === "text/html" || $mime === "text/plain")
            return $this->toStringResponse($mime);

        return $this->toDataResponse($mime);
    }

    /** 
     * Convert the error to a string response
     * @param string $mime The mime type
     */
    private function toStringResponse(string $mime)
    {
        $exception = $this->getPrevious();
        if ($exception === null)
            $exception = $this;

        $result = null;
        try
        {
            $template = $this->getRequest()->getTemplate();
            $template->setExceptionTemplate($exception);
            $template->assign('exception', $exception);
            $template->render();
        }
        catch (StringResponse $e)
        {
            $result = $e;
        }
        catch (Throwable $e)
        {
            self::getLogger()->emergency("Could not render error template, using fallback writer! Exception: {0}", [$e]);
            $op = array('exception' => $exception);

            // Write the output and return it as a string response
            ob_start();
            self::fallbackWriter($op, $mime);
            $op = ob_get_contents();
            ob_end_clean();

            $result = new StringResponse($op, $mime);
        }
        return $result;
    }
        
    /** 
     * Convert the Error to a data response
     *
     * @param string $mime The mime type that will be output
     */
    private function toDataResponse(string $mime)
    {
        $status = $this->getStatusCode();
        $status_msg = isset(StatusCode::$CODES[$status]) ? StatusCode::$CODES[$status] : "Internal Server Error";
        $exception_str = WF::str($this);
        $exception_list = explode("\n", $exception_str);
        $data = array(
            'message' => $this->getMessage(),
            'status_code' => $status,
            'exception' => $exception_list,
            'status' => $status_msg
        );

        $dict = new Dictionary($data);
        return new DataResponse($dict);
    }

    /** 
     * A falbak writer will write to HTML, directly.
     * Used for very serious error situations when no template is available
     */
    public static function fallbackWriter($output, $mime = "text/plain")
    {
        $html = $mime === 'text/html';
        if ($html)
            echo "<!doctype html><html><head><title>Error</title></head><body>";

        self::outputPlainText($output, 0, $html);

        if ($html)
            echo "</body></html>\n";

    }

    public static function outputPlainText($data, int $indent, bool $html)
    {
        if (!WF::is_array_like($data))
        {
            printf("%s\n", WF::str($data, $html));
            return;
        }

        if ($html)
        {
            $indentstr = str_repeat('&nbsp;', $indent);
            $nl = "<br>\n";
        }
        else 
        {
            $indentstr = str_repeat(' ', $indent);
            $nl = "\n";
        }

        $cntwidth = strlen((string)count($data));
        foreach ($data as $key => $value)
        {
            if (WF::is_array_like($value))
            {
                printf("%s%s = {%s", $indentstr, $key, $nl);
                self::outputPlainText($value, $indent + 4, $html);
                printf("%s}%s", $indentstr, $nl);
            }
            else
            {
                if (is_int($key))
                    $key = sprintf('%0' . $cntwidth . 'd', $key);
                printf("%s%s = '%s'%s", $indentstr, $key, WF::str($value, $html), $nl);
            }
        }
    }
}
