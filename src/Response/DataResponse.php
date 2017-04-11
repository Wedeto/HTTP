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

use Throwable;
use InvalidArgumentException;

use Wedeto\Util\Dictionary;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Functions as WF;
use Wedeto\FileFormats\AbstractWriter;
use Wedeto\FileFormats\WriterFactory;

/**
 * DataResponse represents structured data, such as JSON or XML. The
 * ResponseBuilder will decide what format to format the data in.
 */
class DataResponse extends Response
{
    use LoggerAwareStaticTrait;

    /** The response data */
    protected $data;

    /** The available mime-types => writers */
    protected $file_formats = array();

    /** The pretty-printing flag for the response writer*/
    protected $pretty_printing = false;

    /**
     * Create the data response by setting a array-like parameter.
     * By default, the available writers are obtained from
     * WriterFactory::getAvailableWriters.
     * 
     * @param mixed $data The response data
     * @param int $status_code The response status code
     */
    public function __construct($data, int $status_code = 200)
    {
        parent::__construct("DataResponse", $status_code);
        if ($data instanceof Dictionary)
            $this->data = $data;
        else
            $this->data = new Dictionary(WF::to_array($data));

        $this->file_formats = WriterFactory::getAvailableWriters();
    }

    /**
     * Set the available file formats - replacing the previous list
     */
    public function setFileFormats(array $formats)
    {
        $this->file_formats = array();
        foreach ($formats as $mime => $writer)
            $this->addFileFormat($mime, $writer);

        return $this;
    }

    /**
     * Add a file format that this writer can output. This can be used to
     * add a custom mime-type -> writer mapping. If the format is already
     * registered, it is overwritten.
     *
     * @param string $mime_type The mime type to register
     * @param string $writer_class The writer class. Should subclass AbstractWriter
     * @return Wedeto\HTTP\DataResponse Provides fluent interface
     */
    public function addFileFormat(string $mime_type, $writer_class)
    {
        if (is_string($writer_class))
        {
            if (!class_exists($writer_class) || !(is_subclass_of($writer_class, AbstractWriter::class, $writer_class)))
            {
                throw new InvalidArgumentException(
                    "Class {$writer_class} does not exist or does not implement " . AbstractWriter::class
                );
            }
            $this->file_formats[$mime_type] = $writer_class;    
        }
        elseif ($writer_class instanceof AbstractWriter)
        {
            $this->file_formats[$mime_type] = $writer_class;    
        }
        else
        {
            throw new InvalidArgumentException(
                "Invalid writer specified: " . WF::str($writer_class)
            );
        }

        $this->file_formats[$mime_type] = $writer_class;    
        return $this;
    }

    /**
     * Remove a file format from the list.
     * 
     * @param string $mime_type The mime type to remove
     * @return Wedeto\HTTP\DataResponse Provies fluent interface
     */
    public function removeFileFormat(string $mime_type)
    {
        unset($this->file_formats[$mime_type]);
        return $this;
    }

    /**
     * @return Wedeto\Util\Dictionary The response data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return array The list of mime types that can be written
     */
    public function getMimeTypes()
    {
        return array_keys($this->file_formats);
    }

    /** 
     * Set the pretty printing option on the writer
     * @param bool $pprint True to enable pretty printing, false to disable it
     * @return Wedeto\HTTP\DataResponse Provides fluent interface
     */
    public function setPrettyPrint(bool $pprint)
    {
        $this->pretty_printing = $pprint;
        return $this;
    }

    /**
     * Output the response data in the selected format
     *
     * @param string $mime The mime type to output
     */
    public function output(string $mime)
    {
        $pprint = $this->pretty_printing;
        $classname = $this->file_formats[$mime] ?? "NullWriter";
        
        $output = "";
        try 
        {
            if ($classname instanceof AbstractWriter)
                $writer = $classname;
            elseif (class_exists($classname))
                $writer = new $classname($pprint);
            else
                throw new InvalidArgumentException("Response writer $classname does not exist");

            $writer->setPrettyPrint($pprint);
            $op = fopen("php://output", "w");
            $writer->write($this->data, $op);
            fclose($op);
        }
        catch (Throwable $e)
        {
            // Bad. Attempt to override response type if still possible
            self::getLogger()->critical('Could not output data, exception occured while writing: {0}', [$e]);
            echo WF::str($this->data->getAll());
        }
    }
}
