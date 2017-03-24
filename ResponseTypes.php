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

namespace WASP\Http;

class ResponseTypes
{
    public static $TYPES = array(
        // Common HTTP data types
        "txt"  => "text/plain",
        "ini"  => "text/plain",
        "htm"  => "text/html",
        "html" => "text/html",
        "js"   => "application/javascript",
        "css"  => "text/css",

        // Data formats
        "csv"  => "text/csv",
        "json" => "application/json",
        "xml"  => "application/xml",
        "yaml" => "text/yaml",

        // Image formats
        "png"  => "image/png",
        "jpg"  => "image/jpeg",
        "jpeg" => "image/jpeg",
        "gif"  => "image/gif",
        "svg"  => "image/svg+xml",

        // Audio formats
        "wav"  => "audio/wav",
        "webm" => "audio/webm",
        "ogg"  => "audio/ogg",
        "mp3"  => "audio/mpeg",
        "flac" => "audio/flac",
        "aac"  => "audio/aac",
        "m4a"  => "audio/mp4",
        "wma"  => "audio/x-ms-wma",

        // Video formats
        "mp4"  => "video/mp4",
        "ogv"  => "video/ogg",
        "webv" => "video/webm",
        "avi"  => "video/avi",
        "mkv"  => "video/mkv",
        "mov"  => "video/quicktime",
        "wmv"  => "video/x-ms-wmv",

        // Document formats
        "pdf"  => "application/pdf",
        "doc"  => "application/msword",
        "xls"  => "application/vnd.ms-excel",
        "ppt"  => "application/vnd.ms-powerpoint",
        "docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "pptx" => "application/vnd.openxmlformats-officedocument.presentationml.presentation", 
        "odt"  => "application/vnd.oasis.opendocument.text",
        "ods"  => "application/vnd.oasis.opendocument.spreadsheet",
        "odp"  => "application/vnd.oasis.opendocument.presentation",

        // Archive formats
        "gz"   => "application/gzip",
        "xz"   => "application/x-xz",
        "tar"  => "application/x-tar",
        "bz2"  => "application/x-bzip2",
        "tgz"  => "application/gzip",
        "tbz2" => "application/x-bzip2",
        "zip"  => "application/zip",
        "rar"  => "application/x-rar-compressed",

        // Binary formats
        "bin"       => "application/octet-stream",
        "exe"       => "application/octet-stream",
        "multipart" => "multipart/form-data",
    );

    public static function extractFromPath(string $path)
    {
        $pos = strrpos($path, '.');
        $type = null;
        if ($pos === false)
            return array(null, null);

        $ext = substr($path, $pos); 
        return array(self::getMimeFromExtension($ext), $ext);
    }

    public static function getFromFile(string $path)
    {
        $pos = strrpos($path, '.');
        if ($pos !== false)
        {
            $ext = substr($path, $pos + 1);
            $type = self::getMimeFromExtension($ext);
            if ($type)
                return $type;
        }

        // @codeCoverageIgnoreStart
        // We're going to trust PHPs tests on this
        return mime_content_type($path);
        // @codeCoverageIgnoreEnd
    }

    public static function getMimeFromExtension(string $ext)
    {
        $lext = strtolower(ltrim($ext, '.'));
        if (isset(self::$TYPES[$lext]))
            return self::$TYPES[$lext];
        return null;
    }

    public static function getExtension(string $mime)
    {
        $ext = array_search($mime, self::$TYPES, true);
        return $ext === false ? null : $ext;
    }

    public static function isPlainText(string $mime)
    {
        $sc_pos = strpos($mime, ';');
        if ($sc_pos !== false)
            $mime = substr($mime, 0, $sc_pos);
        
        switch ($mime)
        {
            case "text/plain":
            case "text/html":
            case "application/javascript":
            case "text/css":
            case "text/csv":
            case "application/json":
            case "application/xml":
            case "text/yaml":
                return true;
        }

        return false;
    }
}
