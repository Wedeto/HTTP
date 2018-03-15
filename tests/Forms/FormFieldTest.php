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

use Wedeto\Util\Dictionary;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;

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
        $this->assertEquals('Foo', $field->getTitle());

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

    public function testValidation()
    {
        $field = new FormField('foo', Type::INT, 'text', 0);

        $params = new Dictionary(['foo' => 3]);
        $files = new Dictionary();
        $valid = $field->validate($params, $files);
        $this->assertEquals([], $field->getErrors());
        $this->assertTrue($valid);

        $params['foo'] = "3";
        $valid = $field->validate($params, $files);
        $this->assertEquals([], $field->getErrors());
        $this->assertTrue($valid);

        $params['foo'] = "bar";
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $msg = FormField::formatErrorMessage($field->getErrors()[0]);
        $this->assertContains('Integral value required', $msg);
    }

    public function testDetermineIsRequired()
    {
        $field = new FormField('foo', Type::INT, 'text', 0);
        $this->assertTrue($field->isRequired());

        $nullableInt = new Validator(Type::INT, ['nullable' => true]);
        $field = new FormField('foo', $nullableInt, 'text', 0);
        $this->assertFalse($field->isRequired());
        
        $this->assertSame($nullableInt, $field->getValidator(0));
    }

    public function testValidateWithArray()
    {
        $field = new FormField('foo[]', Type::INT, 'text', 0);

        $params = new Dictionary(['foo' => [3, 5, 7]]);
        $files = new Dictionary;
        $valid = $field->validate($params, $files);
        $this->assertTrue($valid);

        $params['foo'] = [3, "5", "bar"];
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $errors = $field->getErrors();
        $this->assertTrue(isset($errors[2]));
        $this->assertContains('Integral value required', FormField::formatErrorMessage($errors[2][0]));

        $params['foo'] = "RandomString";
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $errors = $field->getErrors();
        $this->assertContains('Invalid value for foo', FormField::formatErrorMessage($errors[''][0]));

        $params['foo'] = ['foo' => [3, 4, 5]];
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $errors = $field->getErrors();
        $this->assertContains('Field foo[] should not nest', FormField::formatErrorMessage($errors[''][0]));

        $params['foo'] = [];
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $errors = $field->getErrors();
        $this->assertContains('Required field: foo[]', FormField::formatErrorMessage($errors[''][0]));
    }

    public function testValidateWithMultiDimensionalArray()
    {
        $field = new FormField('foo[bar][baz]', Type::INT, 'text', 0);

        $params = new Dictionary(['foo' => ['bar' => ['baz' => 1337]]]);
        $files = new Dictionary;
        $valid = $field->validate($params, $files);
        $this->assertTrue($valid);
        $this->assertEquals(1337, $field->getValue());

        $params = new Dictionary(['foo' => ['bar' => ['baz' => 'text']]]);
        $files = new Dictionary;
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $this->assertEquals('text', $field->getValue());

        $params = new Dictionary(['foo' => []]);
        $files = new Dictionary;
        $valid = $field->validate($params, $files);
        $this->assertFalse($valid);
        $errors = $field->getErrors();
        $this->assertContains('Required field', FormField::formatErrorMessage($errors[0]));
        $this->assertNull($field->getValue());
    }

    public function testDescriptionSettingAndGetting()
    {
        $field = new FormField('foo', Type::STRING, 'text', null);
        
        $this->assertSame($field, $field->setDescription('A nice description'));
        $this->assertEquals('A nice description', $field->getDescription());
    }

    public function testArrayLikeConvertedToDictionary()
    {
        $field = new FormField('foo[]', Type::STRING, 'text', null);

        $std = new \ArrayObject;
        $std[0] = "abc";
        $std[1] = "abc";
        $params = new Dictionary(['foo' => $std]);
        $files = new Dictionary;

        $valid = $field->validate($params, $files);
        $this->assertTrue($valid);
    }

    public function testTransformValue()
    {
        $field = new FormField('foo', Type::STRING, 'text', null);

        $mocker = $this->prophesize(Transformer::class);
        $mocker->serialize('foobar')->willReturn('yes');
        $mocker->deserialize('no')->willReturn('barfoo');

        $tf = $mocker->reveal();
        $this->assertSame($field, $field->setTransformer($tf));

        $this->assertSame($field, $field->setValue('no', true));
        $this->assertEquals('barfoo', $field->getValue(false));
        $this->assertSame($field, $field->setValue('foobar', false));
        $this->assertEquals('yes', $field->getValue(true));
        $this->assertSame($tf, $field->getTransformer());

        $field = new FormField('foo', Type::STRING, 'text', null);
        $this->assertSame($field, $field->setValue('no', true));
        $this->assertEquals('no', $field->getValue(false));
        $this->assertSame($field, $field->setValue('foobar', false));
        $this->assertEquals('foobar', $field->getValue(true));
        $this->assertNull($field->getTransformer());
    }
}
