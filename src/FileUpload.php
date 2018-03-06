<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017-2018, Egbert van der Wal

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
use Wedeto\Util\Dictionary;

class FileUpload
{
    use LoggerAwareStaticTrait;
     
    /** The path to the form element - mainly useful in arrays */
    protected $field_path;

    /** The name of the form element */
    protected $field_name;

    /** The uploaded file name */
    protected $file_name;

    /** The error code */
    protected $error;

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
     * @param array $field_path The path to the form element that uploaded the file. Simply ['field_name'] in the basic case.
     * @param string $name The name of the file
     * @param string $type The content type / mime type of the uploaded file
     * @param string $tmp_name The temporary location of the file
     * @param int $error The error code
     * @param int $size The size in bytes
     */
    public function __construct(array $field_path, string $name, string $type, string $tmp_name, int $error, int $size)
    {
        // Initialize the logger
        self::getLogger();

        if (!isset(self::$error_codes[$error]))
            throw new FileUploadException("Invalid error code: " . $error, $error);

        $this->error = $error;

        // Sanitize the filename
        $name = preg_replace("/[^a-zA-Z0-9_.]/", "_", $name);
        $f = new File($name);

        $this->field_path = $field_path;

        $this->field_name = "";
        foreach ($field_path as $el)
        {
            if (empty($this->field_name))
                $this->field_name = $el;
            else
                $this->field_name .= '[' . $el . ']';
        }

        // Lowercase the file extension
        $this->file_name = $f->withExtension($f->getExtension());

        // Store the temporary location
        $this->location = $tmp_name;

        // Create the file object of the target file
        $this->file = new File($this->file_name, $type);

        // The uploaded file size
        $this->size = $size;
    }

    public function isSuccess()
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    public function getFieldName()
    {
        return $this->field_name;
    }

    public function getFieldPath()
    {
        return $this->field_path;
    }

    public function getFileName()
    {
        return $this->file_name;
    }

    public function getTempFile()
    {
        return $this->location;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function moveTo(string $dir)
    {
        if ($this->copied)
            throw new FileUploadException("Upload has already been moved");

        if (!is_writable($dir))
            throw new FileUploadException("Target directory is not writable");

        $exists = file_exists($this->location);
        $is_uploaded = is_uploaded_file($this->location) || (defined('WEDETO_TEST') && WEDETO_TEST === 1);
        if (!$exists || !$is_uploaded)
            throw new FileUploadException("Not an uploaded file");

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
            $data = sha1($this->file_name . time() . $rnd);
            $prefix = $day . "_" .substr($data, 0, 2);
            $target_path = $dir . "/" . $prefix . "_" . $this->file_name;
            if (!file_exists($target_path))
                break;

            $rnd = (string)rand(0, 1000);
        }

        self::$logger->info("Moving uploaded file {0} to {1}", [$this->location, $target_path]);
        rename($this->location, $target_path);
        Hook::execute("Wedeto.IO.FileCreated", ['path' => $target_path]);

        $this->file_name = $target_path;
        $this->file = new File($target_path);
        $this->copied = true;
    }

    /**
     * Parse an array with the structure as provided by the $_FILES super global.
     * @param array $files An array containing keys of the root form elements
     *                     used to upload files, each containing name, type,
     *                     tmp_name, error and size members.
     * @return Dictionary A list of uploaded files
     */
    public static function parseFileArray(array $files)
    {
        $dict = new Dictionary;
        $req_keys = ['name', 'type', 'tmp_name', 'error', 'size'];

        foreach ($files as $root_name => $file)
        {
            foreach ($req_keys as $rk)
            {
                if (!isset($file[$rk]))
                    throw new FileUploadException("Invalid uploaded file structure - missing key: " . $rk);
            }

            self::traverse(
                $file['name'], 
                $file['type'],
                $file['tmp_name'],
                $file['error'],
                $file['size'], 
                $dict,
                [$root_name]
            ); 
        }

        return $dict;
    }

    /**
     * Helper function to recursively traverse the uploaded files array.
     * @param array|string $name The name
     * @param array|string $type The content-type
     * @param array|string $tmp_name The temporary file name
     * @param array|int $size The file size
     * @param Dictionary $dict The dictionary where to store the files
     * @param array $path The path in the traversal
     */
    private static function traverse($name, $type, $tmp_name, $error, $size, Dictionary $dict, $path)
    {
        if (is_array($name))
        {
            $keys = array_keys($name);
            foreach ($keys as $key)
            {
                $new_path = $path;
                $new_path[] = $key;
                if (
                    !array_key_exists($key, $name) ||
                    !array_key_exists($key, $type) ||
                    !array_key_exists($key, $error) ||
                    !array_key_exists($key, $tmp_name) ||
                    !array_key_exists($key, $size)
                )
                {
                    throw new FileUploadException("Missing information for file upload " . implode(".", $new_path));
                }
                self::traverse($name[$key], $type[$key], $tmp_name[$key], $error[$key], $size[$key], $dict, $new_path);
            }
        }
        else
        {

            $file = new FileUpload($path, $name, $type, $tmp_name, $error, $size);
            $path[] = $file;
            $dict->set($path, null);
            $dict->set('_files', $file->getFieldName(), $file);
        }
    }
}
