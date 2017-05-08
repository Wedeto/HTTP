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

use Wedeto\Util\Type;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @covers Wedeto\HTTP\FileUpload
 */
final class FileUploadTest extends TestCase
{
    public function testInitialize()
    {
        $files = $this->getFileUploadArray();

        $dict = FileUpload::parseFileArray($files);

        $filedict = $dict['_files'];
        $this->assertEquals(4, count($filedict));

        $tp = new Type(Type::OBJECT, ['class' => FileUpload::class]);

        $this->assertTrue($filedict->has('file[0]', $tp));
        $this->assertTrue($filedict->has('file[1]', $tp));
        $this->assertTrue($filedict->has('foo[bar][baz]', $tp));
        $this->assertTrue($filedict->has('foobar', $tp));

        $this->assertEquals($files['file']['name'][0], $filedict['file[0]']->getFileName());
        $this->assertEquals($files['file']['tmp_name'][0], $filedict['file[0]']->getTempFile());
        $this->assertEquals($files['file']['error'][0], $filedict['file[0]']->getError());
        $this->assertEquals($files['file']['size'][0], $filedict['file[0]']->getSize());
        $this->assertEquals(['file', 0], $filedict['file[0]']->getFieldPath());
        $this->assertTrue($filedict['file[0]']->isSuccess());

        $this->assertEquals($files['file']['name'][1], $filedict['file[1]']->getFileName());
        $this->assertEquals($files['file']['tmp_name'][1], $filedict['file[1]']->getTempFile());
        $this->assertEquals($files['file']['error'][1], $filedict['file[1]']->getError());
        $this->assertEquals($files['file']['size'][1], $filedict['file[1]']->getSize());
        $this->assertEquals(['file', 1], $filedict['file[1]']->getFieldPath());
        $this->assertFalse($filedict['file[1]']->isSuccess());

        $this->assertEquals($files['foobar']['name'], $filedict['foobar']->getFileName());
        $this->assertEquals($files['foobar']['tmp_name'], $filedict['foobar']->getTempFile());
        $this->assertEquals($files['foobar']['error'], $filedict['foobar']->getError());
        $this->assertEquals($files['foobar']['size'], $filedict['foobar']->getSize());
        $this->assertEquals(['foobar'], $filedict['foobar']->getFieldPath());
        $this->assertTrue($filedict['foobar']->isSuccess());

        $this->assertEquals($files['foo']['name']['bar']['baz'], $filedict['foo[bar][baz]']->getFileName());
        $this->assertEquals($files['foo']['tmp_name']['bar']['baz'], $filedict['foo[bar][baz]']->getTempFile());
        $this->assertEquals($files['foo']['error']['bar']['baz'], $filedict['foo[bar][baz]']->getError());
        $this->assertEquals($files['foo']['size']['bar']['baz'], $filedict['foo[bar][baz]']->getSize());
        $this->assertEquals(['foo', 'bar', 'baz'], $filedict['foo[bar][baz]']->getFieldPath());
        $this->assertTrue($filedict['foo[bar][baz]']->isSuccess());

        $this->assertEquals($filedict['file[0]'], $dict['file'][0]);
        $this->assertEquals($filedict['file[1]'], $dict['file'][1]);
        $this->assertEquals($filedict['foobar'], $dict['foobar']);
        $this->assertEquals($filedict['foo[bar][baz]'], $dict['foo']['bar']['baz']);
    }

    public function testInvalidFileError()
    {
        $arr = [
            'foo' => [
                'name' => 'foo',
                'type' => 'application/octet-stream',
                'error' => 9999,
                'tmp_name' => '/tmp/phpfile',
                'size' => 0
            ]
        ];

        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage("Invalid error code: 999");
        $files = FileUpload::parseFileArray($arr);
    }

    public function testInvalidFileArray()
    {
        $arr = ['foo' => ['name' => 'foo']];
        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('Invalid uploaded file structure - missing key');
        $files = FileUpload::parseFileArray($arr);
    }

    public function testInvalidFileKey()
    {
        $arr = ['foo' => [
            'name' => ['foo', 'bar'],
            'type' => ['application/octet-stream'],
            'error' => [0, 0],
            'tmp_name' => ['foo'],
            'size' => [0, 0]
        ]];

        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('Missing information for file upload foo.1');
        $files = FileUpload::parseFileArray($arr);
    }

    public function testGetFile()
    {
        $file_arr = $this->getFileUploadArray();

        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('tmpdir'));
        $dir = vfsStream::url('tmpdir');

        $move_dir = $dir . '/target';
        mkdir($move_dir);

        $f = $dir . '/foobar.png';
        file_put_contents($f, "foobar");
        $file_arr['foobar']['tmp_name'] = $f;
        $files = FileUpload::parseFileArray($file_arr);

        $file_object = $files['foobar'];
        $this->assertInstanceOf(FileUpload::class, $file_object);

        $file = $file_object->getFile();
        $this->assertEquals($file_arr['foobar']['name'], $file->getFilename());

        $final_dir = $move_dir . '/'. date('Y') . '/' . date('m');
        $file_object->moveTo($move_dir);
        $file = $file_object->getFile();
        $this->assertEquals($final_dir, $file->getDir());

        $final_path = $file->getPath();
        $this->assertTrue(file_exists($final_path));
        $this->assertEquals('foobar', file_get_contents($final_path));
        $this->assertFalse(file_exists($f));

        var_Dump($file);

        $this->expectException(FileUploadException::class);
        $this->expectExceptionMessage('Upload has already been moved');
        $file_object->moveTo($move_dir);
    }

    public function getFileUploadArray()
    {
        return [
            "file" => [
                "name" => [
                    "file1.jpg",
                    "file2.dat"
                ],
                "type" => [
                    "image/jpeg",
                    "application/octet-stream"
                ],
                "tmp_name" => [
                    "/tmp/php1234",
                    "/tmp/php5678"
                ],
                "error" => [
                    0,
                    UPLOAD_ERR_PARTIAL
                ],
                "size" => [
                    851205,
                    877282
                ]
            ],
            "foobar" => [
                "name" => "foo_file.png",
                "type" => "image/png",
                "tmp_name" => "/tmp/php9876",
                "error" => 0,
                "size" => 131208
            ],
            "foo" => [
                "name" => [
                    "bar" => [
                        'baz' => 'foobarbaz.png'
                    ]
                ],
                "type" => [
                    "bar" => [
                        "baz" => "image/png"
                    ]
                ],
                "tmp_name" => [
                    "bar" => [
                        "baz" => "/tmp/php3456"
                    ]
                ],
                "error" => [
                    "bar" => [
                        "baz" => 0
                    ]
                ],
                "size" => [
                    "bar" => [
                        "baz" => 29078
                    ]
                ]
            ]
        ];
    }
}
?>
