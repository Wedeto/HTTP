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

namespace Wedeto\HTTP;

use Wedeto\IO\File;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Hook;

class FileUpload
{
    use LoggerAwareStaticTrait;
     
    /** The name of the form element */
    protected $name;

    /** The uploaded file name */
    protected $filename;

    /** The location where the file is temporarily stored */
    protected $location;
    
    /** The File object */
    protected $file;

    /** The size of the uploaded file */
    protected $size;
    
    /** If the object has been copied */
    protected $copied = false;

    protected static $error_codes = array(
        UPLOAD_ERR_OK => "Upload successful",
        UPLOAD_ERR_INI_SIZE => "File exceeds upload_max_filesize directive",
        UPLOAD_ERR_FORM_SIZE => "File exceeds MAX_FILE_SIZE directive in form",
        UPLOAD_ERR_PARTIAL => "Uploaded file is incomplete",
        UPLOAD_ERR_NO_FILE => "No file was uploaded",
        UPLOAD_ERR_NO_TMP_DIR => "No tmp directory available",
        UPLOAD_ERR_CANT_WRITE => "Failed to write to disk",
        UPLOAD_ERR_EXTENSION => "Upload blocked by extension"
    );

    /**
     * Construct the FileUpload object
     *
     * @param string $name The name of the form element that uploaded the file
     * @param array $info The metadata for this upload as provided in the $_FILES superglobal
     */
    public function __construct(string $name, array $info)
    {
        // Initialize the logger
        self::getLogger();

        if ($info['error'] !== UPLOAD_ERR_OK)
            throw new FileUploadException(self::$error_codes[$info['error']], $info['error']);

        $this->info = $info;
        $name = $info['name'];

        // Sanitize the filename
        $name = preg_replace("/[^a-zA-Z0-9_.]/", "_", $name);
        $f = new File($name);

        // Lowercase the file extension
        $this->filename = $f->setExt($f->getExt());

        // Store the temporary location
        $this->location = $info['tmp_name'];

        // Create the file object of the target file
        $this->file = new File($this->filename, $this->info['type']);

        // The uploaded file size
        $this->size = $info['size'];
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function moveTo(string $dir)
    {
        if ($this->copied)
            throw new FileUploadException("Upload has already been moved");

        if (!is_writable($dir))
            throw new FileUploadException("Target directory is not writable");

        $year = date("Y");
        $month = date("m");
        $day = date("d");
        $rnd = "";

        if (!file_exists($dir . "/" . $year))
            mkdir($dir . "/" . $year);
        if (!file_exists($dir . "/" . $year . "/" . $month))
            mkdir($dir . "/" . $year . "/" . $month);

        $dir .= "/" . $year . "/" . $month;
        while (true)
        {
            $data = sha1($this->filename . time() . $rnd);
            $prefix = $day . "_" .substr($data, 0, 2);
            $target_path = $dir . "/" . $prefix . "_" . $this->filename;
            if (!file_exists($target_path))
                break;

            $rnd = (string)rand(0, 1000);
        }

        self::$logger->info("Moving uploaded file {0} to {1}", [$this->location, $target_path]);
        rename($this->location, $target_path);
        Hook::execute("Wedeto.IO.FileCreated", ['path' => $target_path]);

        $this->filename = $target_path;
        $this->file = new File($target_path);
        $this->copied = true;
    }
}
