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
use Wedeto\Util\Validation\ValidationException;

use Wedeto\Util\DI\DI;

use Wedeto\HTTP\Request;
use Wedeto\HTTP\Session;
use Wedeto\HTTP\URL;
use Wedeto\HTTP\Forms\Transformers\DateTransformer;

use Wedeto\DB\DB;
use Wedeto\DB\DAO;
use Wedeto\DB\Model;
use Wedeto\DB\Schema\Schema;
use Wedeto\DB\Schema\Column;

use DateTime;

/** Load required mock forms */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'MockBaseForms.php';

/**
 * @covers Wedeto\HTTP\Forms\Binder
 * @covers Wedeto\HTTP\Forms\BaseForm
 */
final class BinderTest extends TestCase
{
    public function setUp()
    {
        DI::startNewContext('test');
        $tf = TransformStore::getInstance();
        $tf->registerTransformer(DateTime::class, new DateTransformer);
    }

    public function tearDown()
    {
        DI::destroyContext('test');
    }

    public function testBaseForm()
    {
        $binder = new Binder();
        $form = $binder->createFormForObject(MockForm::class);

        $data = ['name' => 'foo', 'age' => 30, 'date' => '2018-03-03'];
        $data['_form_name'] = $form->getName();
        $none = [];
        $server = ['HTTP_METHOD' => 'POST'];

        $request = new Request($none, $data, $none, $server, $none);
        $cfg = new Dictionary([]);
        $this->assertSame($form, $form->prepare($request->session, true));
        $this->assertTrue($form->isValid($request));
        $this->assertEquals([], $form->getErrors());

        $instance = $binder->bind($form, MockForm::class);

        $this->assertInstanceOf(MockForm::class, $instance);
        $this->assertEquals('foo', $instance->name);
        $this->assertEquals(30, $instance->age);
        $this->assertInstanceOf(DateTime::class, $instance->date);
        $this->assertEquals('2018-03-03', $instance->date->format('Y-m-d'));
    }

    public function testConstructFromNonBaseFormShouldThrowException()
    {
        $binder = new Binder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Not a valid BaseForm class provided");
        $binder->createFormForObject(\stdClass::class);
    }

    public function testBaseFormWithValidator()
    {
        $binder = new Binder();
        $form = $binder->createFormForObject(MockForm2::class);

        $data = ['name' => 'foo', 'age' => 30, 'date' => '2018-03-03', 'zipcode' => 'foo'];
        $data['_form_name'] = $form->getName();
        $none = [];
        $server = ['HTTP_METHOD' => 'POST'];

        $request = new Request($none, $data, $none, $server, $none);
        $cfg = new Dictionary([]);
        $this->assertSame($form, $form->prepare($request->session, true));
        $this->assertFalse($form->isValid($request));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['name']), "Name was too short");
        $this->assertTrue(isset($errors['zipcode']), "Zipcode was invalid");
        $n_error = FormField::formatErrorMessage($errors['name'][0]);
        $z_error = FormField::formatErrorMessage($errors['zipcode'][0]);
        $n_expected = 'At least 8 characters required';
        $z_expected = 'Match with pattern /[0-9]{4}[A-Z]{2}/ required';
        $this->assertEquals($n_expected, $n_error);
        $this->assertEquals($z_expected, $z_error);

        $data['name'] = 'foobarbaz';
        $data['zipcode'] = '1000AB';
        $this->assertTrue($form->isValid($request));

        $instance = $binder->bind($form, MockForm2::class);

        $this->assertInstanceOf(MockForm2::class, $instance);
        $this->assertEquals('foobarbaz', $instance->name);
        $this->assertEquals(30, $instance->age);
        $this->assertInstanceOf(DateTime::class, $instance->date);
        $this->assertEquals('2018-03-03', $instance->date->format('Y-m-d'));
    }

    public function testBaseFormWithInvalidValidator()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Invalid syntax - expected '\"'");
        $form = $binder->createFormForObject(MockForm3::class);
    }

    public function testBaseFormWithValidatorWithMultipleArguments()
    {
        $binder = new Binder();
        $form = $binder->createFormForObject(MockForm4::class);

        $data = ['word' => 'foo', 'age' => 60];
        $data['_form_name'] = $form->getName();
        $none = [];
        $server = ['HTTP_METHOD' => 'POST'];

        $request = new Request($none, $data, $none, $server, $none);
        $this->assertSame($form, $form->prepare($request->session, true));
        $this->assertFalse($form->isValid($request));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['word']), "Pattern was too short");
        $this->assertTrue(isset($errors['age']), "Age was invalid");

        $n_error = FormField::formatErrorMessage($errors['word'][0]);
        $z_error = FormField::formatErrorMessage($errors['age'][0]);
        $n_expected = 'The word needs to contain a quote';
        $z_expected = 'Integral value between 10 and 50 is required';
        $this->assertEquals($n_expected, $n_error);
        $this->assertEquals($z_expected, $z_error);

        $data['word'] = 'foobar"tst';
        $data['age'] = '15';
        $this->assertTrue($form->isValid($request));

        $instance = $binder->bind($form, MockForm4::class);

        $this->assertInstanceOf(MockForm4::class, $instance);
        $this->assertEquals('foobar"tst', $instance->word);
        $this->assertEquals(15, $instance->age);
    }

    public function testBaseFormWithValidatorsInFunction()
    {
        $binder = new Binder();
        $form = $binder->createFormForObject(MockForm5::class);
        
        $this->assertInstanceOf(Form::class, $form);

        $this->assertFalse(isset($form['ignored']));
        $this->assertFalse(isset($form['ignored2']));
        $this->assertTrue(isset($form['word']));

        $data = ['word' => 'foo'];
        $data['_form_name'] = $form->getName();
        $none = [];
        $server = ['HTTP_METHOD' => 'POST'];

        $request = new Request($none, $data, $none, $server, $none);
        $this->assertFalse($form->isValid($request));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['']), "Form validation should have failed");
        $this->assertTrue(isset($errors['word']), "Field validation should have failed");

        $this->assertEquals(2, count($errors['']), "Two form errors should be returned");
        $this->assertEquals(1, count($errors['word']), "One field error should be returned");

        $this->assertContains('Match with pattern', $errors['word'][0]['msg']);
        $this->assertContains('missing h', $errors[''][0]['msg']);
        $this->assertContains('Invalid foo', $errors[''][1]['msg']);

        $data['word'] = 'fgh';
        $this->assertFalse($form->isValid($request));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['']), "Form validation should have failed");
        $this->assertFalse(isset($errors['word']), "Field validation should have passed");

        $this->assertEquals(1, count($errors['']), "One form error should be returned");

        $this->assertContains('Invalid foo', $errors[''][0]['msg']);
    }

    public function testBaseFormWithInvalidClassValidatorAnnotation()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Invalid validator");
        $binder->createformForObject(MockForm6::class);
    }

    public function testBaseFormWithPropertyWithoutVarAnnotation()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("No type");
        $binder->createformForObject(MockForm7::class);
    }

    public function testBaseFormWithValidatorAnnotationWithDoubleQuotes()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Unexpected quote");
        $binder->createformForObject(MockForm8::class);
    }

    public function testBaseFormWithValidatorThatContainsIrrelevantEscapeCharacter()
    {
        $binder = new Binder();

        $form = $binder->createformForObject(MockForm9::class);
        $this->assertInstanceOf(Form::class, $form);

        $data = ['word' => 'foo', 'email' => 'na'];
        $none = [];
        $request = new Request($none, $data, $none, $none, $none);
        $this->assertFalse($form->isValid($request));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['word']));
        $this->assertTrue(isset($errors['email']));

        $data['word'] = 'a Valid String is ok';
        $data['email'] = 'foo@bar.com';
        $this->assertTrue($form->isValid($request));
    }

    public function testBaseFormWithNonExistingValidator()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Validator class does not exist");
        $binder->createFormForObject(MockForm10::class);
    }

    public function testBaseFormWithValidatorThatDoesNotSubclassValidator()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Invalid validator class");
        $binder->createFormForObject(MockForm11::class);
    }

    public function testBaseFormWithValidatorWithTooManyArguments()
    {
        $binder = new Binder();

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Invalid amount of arguments for validator constructor: 3 given but 1 required");
        $binder->createFormForObject(MockForm12::class);
    }

    public function testBindWithInvalidClassThrowsException()
    {
        $binder = new Binder();
        $form = new Form("name");

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Provide a classname or a reflection class");
        $binder->bind($form, NonExistingClass::class);
    }

    public function testBindWithReflectionClassWorks()
    {
        $binder = new Binder();

        $form = $binder->createFormForObject(MockForm9::class);

        $data = ['word' => 'a Valid String is', 'email' => 'foo@bar.com'];
        $none = [];
        $request = new Request($none, $data, $none, $none, $none);
        $this->assertTrue($form->isValid($request));

        $refl = new \ReflectionClass(MockForm9::class);
        $instance = $binder->bind($form, $refl);
        $this->assertInstanceOf(MockForm9::class, $instance);
    }

    public function testBindWithInvalidClassFails()
    {
        $binder = new Binder();
        $form = new Form('foo');

        $this->expectException(BinderException::class);
        $this->expectExceptionMessage("Can only bind subclasses of BaseForm and Model");
        $instance = $binder->bind($form, \stdClass::class);
    }

    public function testBaseFormWithSetterShouldBeUsed()
    {
        $binder = new Binder();
        $form = $binder->createFormForObject(MockForm13::class);

        $data = ['bogus' => 'bogustext'];
        $none = [];
        $request = new Request($none, $data, $none, $none, $none);
        $this->assertTrue($form->isValid($request));

        $instance = $binder->bind($form, MockForm13::class);
        $this->assertInstanceOf(MockForm13::class, $instance);
        $this->assertEquals('--bogustext--', $instance->bogus);
    }

    public function testModelForm()
    {
        $binder = new Binder();

        $dao_mock = $this->prophesize(DAO::class);
        $dao = $dao_mock->reveal();

        $dao_mock->getColumns()->willReturn([
            'id' => new Column\Serial('id'),
            'name' => new Column\Varchar('name', 64),
            'age' => new Column\Integer('age')
        ]);
        $form = $binder->createFormForModel(MockModelForm::class, $dao);

        $this->assertFalse(isset($form['id']));
        $this->assertTrue(isset($form['name']));
        $this->assertTrue(isset($form['age']));

        $data = ['name' => 'foobar', 'age' => 33];
        $none = [];
        $request = new Request($none, $data, $none, $none, $none);
        
        $this->assertTrue($form->isValid($request));

        $data['age'] = 'bar';
        $this->assertFalse($form->isValid($request));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['age']));
        $this->assertEquals('Integral value required', FormField::formatErrorMessage($errors['age'][0]));
    }

    public function testModelFormWithAnnotations()
    {
        $binder = new Binder();

        $dao_mock = $this->prophesize(DAO::class);
        $dao = $dao_mock->reveal();

        $dao_mock->getColumns()->willReturn([
            'id' => new Column\Serial('id'),
            'name' => new Column\Varchar('name', 64),
            'age' => new Column\Integer('age')
        ]);
        $form = $binder->createFormForModel(MockModelForm2::class, $dao);

        $this->assertFalse(isset($form['id']));
        $this->assertTrue(isset($form['name']));
        $this->assertTrue(isset($form['age']));

        $data = ['name' => 'foobar', 'age' => 33];
        $none = [];

        $request = new Request($none, $data, $none, $none, $none);
        $this->assertFalse($form->isValid($request));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['age']));
        $msg = FormField::formatErrorMessage($errors['age'][0]);
        $this->assertEquals('Integral value between 10 and 30 is required', $msg);

        $data['age'] = 'bar';
        $this->assertFalse($form->isValid($request));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['age']));
        $this->assertEquals('Integral value required', FormField::formatErrorMessage($errors['age'][0]));

        $data['age'] = '25';

        $db_mock = $this->prophesize(DB::class);
        $db = $db_mock->reveal();
        $db_mock->getDAO(MockModelForm2::class)->willReturn($dao);
        DI::getInjector()->setInstance(DB::class, $db);

        $dao_mock->getPrimaryKey()->willReturn([
            'id' => new Column\Serial('id')
        ]);

        $this->assertTrue($form->isValid($request));
        $inst = $binder->bind($form, MockModelForm2::class);
        $this->assertInstanceOf(Model::class, $inst);
        $this->assertInstanceOf(MockModelForm2::class, $inst);
        $this->assertEquals(25, $inst->age);
        $this->assertEquals('foobar', $inst->name);
    }

    public function testModelFormWithInvalidClassThrowsException()
    {
        $binder = new Binder();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Not a valid Model class");

        $dao_mock = $this->prophesize(DAO::class);
        $dao = $dao_mock->reveal();
        $binder->createFormForModel(\Stdclass::class, $dao);
    }
}

class MockModelForm extends Model
{
    public static $_table = "MockTable";
}

class MockModelForm2 extends Model
{
    public static $_table = "MockTable";

    /**
     * @var int age of person
     * @validator Wedeto\HTTP\Forms\Validation\Between(10, 30)
     */
    public $age;
}
