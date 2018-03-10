<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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

namespace Wedeto\HTTP\Forms;

use PHPUnit\Framework\TestCase;

use Wedeto\Util\Type;
use Wedeto\Util\Validator;

/**
 * @covers Wedeto\HTTP\Forms\FormField
 */
final class FormFieldTest extends TestCase
{
    public function testConstruct()
    {
        $field = new FormField('foo', Type::STRING, 'text', 'bar');

        $this->assertEquals('foo', $field->getName());
        $this->assertEquals('text', $field->getControlType());
        $this->assertEquals('bar', $field->getValue());
        $this->assertFalse($field->isArray());
        $this->assertFalse($field->isFile());

        $vals = $field->getValidators();
        $this->assertEquals(1, count($vals));
        $this->assertEquals(Type::STRING, $vals[0]->getType());
    }

    public function testConstructOneDimensionalArrayType()
    {
        $field = new FormField('foo[]', Type::STRING, 'text', 'bar');

        $this->assertEquals('foo[]', $field->getName());
        $this->assertEquals('text', $field->getControlType());
        $this->assertEquals('bar', $field->getValue());
        $this->assertTrue($field->isArray());
        $this->assertFalse($field->isFile());

        $vals = $field->getValidators();
        $this->assertEquals(1, count($vals));
        $this->assertEquals(Type::STRING, $vals[0]->getType());
    }

    public function testConstructMultiDimensionalArrayType()
    {
        $field = new FormField('foo[bar][]', Type::STRING, 'text', 'bar');

        $this->assertEquals('foo[bar][]', $field->getName());
        $this->assertEquals('text', $field->getControlType());
        $this->assertEquals('bar', $field->getValue());
        $this->assertTrue($field->isArray());
        $this->assertFalse($field->isFile());

        $vals = $field->getValidators();
        $this->assertEquals(1, count($vals));
        $this->assertEquals(Type::STRING, $vals[0]->getType());
    }

    public function testConstructMultiDimensionalArrayWithEmptyFirstDimensionThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid name: foo[][]");
        $field = new FormField('foo[][]', Type::STRING, 'text', 'bar');
    }

    public function testConstructWithFileType()
    {
        $field = new FormField('foofile', FormField::TYPE_FILE, 'file', '');
        $this->assertEquals('foofile', $field->getName());
        $this->assertEquals('', $field->getValue());
        $this->assertTrue($field->isFile());
        $this->assertFalse($field->isArray());
    }

    public function testAddAndRemoveValidator()
    {
        $field = new FormField('foo', Type::STRING, 'text', 'bar');
        
        $validators = $field->getValidators();
        $this->assertEquals(1, count($validators));

        $v2 = new Validator(Validator::ISSET);
        $field->addValidator($v2);

        $validators = $field->getValidators();
        $this->assertEquals(2, count($validators));
        $this->assertSame($v2, $validators[1]);

        $this->assertSame($field, $field->removeValidator(1));
        
        $validators = $field->getValidators();
        $this->assertEquals(1, count($validators));
        $this->assertEquals(Type::STRING, $validators[0]->getType());

    }
}
