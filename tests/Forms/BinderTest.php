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

use DateTime;

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
        $this->expectExceptionMessage("You must provide a subclass of BaseForm");
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
}

class MockForm extends BaseForm
{
    /**
     * @var string Person name
     */
    public $name;

    /**
     * @var int Age
     */
    public $age;

    /**
     * @var DateTime date of birth
     */
    public $date;
}

class MockForm2 extends BaseForm
{
    /**
     * @var string Person name
     * @validator Wedeto\HTTP\Forms\Validation\MinLength(8)
     */
    public $name;

    /**
     * @var int Age
     */
    public $age;

    /**
     * @var DateTime date of birth
     */
    public $date;

    /**
     * @var string Zip code
     * @validator Wedeto\HTTP\Forms\Validation\Pattern("/[0-9]{4}[A-Z]{2}/")
     */
    public $zipcode;
}

class MockForm3 extends BaseForm
{
    /**
     * @var string Person name
     * @validator Wedeto\HTTP\Forms\Validation\MinLength("8)
     */
    public $name;

    /**
     * @var int Age
     */
    public $age;

    /**
     * @var DateTime date of birth
     */
    public $date;
}

class MockForm4 extends BaseForm
{
    /**
     * @var string Requires a quote
     * @validator Wedeto\HTTP\Forms\Validation\Pattern("/.*\".{3}/")
     * @error The word needs to contain a quote
     */
    public $word;

    /**
     * @var int Age
     * @validator Wedeto\HTTP\Forms\Validation\Between(10, 50)
     */
    public $age;
}

/**
 * @validator Wedeto\HTTP\Forms\MockFormValidator
 */
class MockForm5 extends BaseForm
{
    /**
     * @var string some
     */
    public $word;

    public $ignored;

    /**
     * @var string this one should be ignored too
     * @ignore
     */
    public $ignored2;

    public static function listFormValidators()
    {
        return [new Validator(Validator::VALIDATE_CUSTOM, ['custom' => function ($form) {
            throw new ValidationException("Invalid foo");
        }])];
    }

    public static function listFieldValidators()
    {
        return ['word' => [new Validation\Pattern('/g/')]];
    }
}

class MockFormValidator extends Validator
{
    public function __construct()
    { }

    public function validate($form, &$filtered = null)
    {
        $val = $form['word']->getValue();
        if (!preg_match('/h/', $val))
        {
            $this->error = ['msg' => 'missing h'];
            return false;
        }

        return true;
    }
}

/**
 * @validator Wedeto\HTTP\Forms\NonExistingValidator
 */
class MockForm6 extends BaseForm
{
    /**
     * @var string some
     */
    public $word;
}

class MockForm7 extends BaseForm
{
    /**
     * @error Custom error
     */
    public $word;
}

class MockForm8 extends BaseForm
{
    /**
     * @var string word
     * @validator Validator("Invalid""String", 3)
     */
    public $word;
}

class MockForm9 extends BaseForm
{
    /**
     * @var string word
     * @validator Wedeto\HTTP\Forms\Validation\Pattern("/Valid\sString/")
     */
    public $word;

    /**
     * @var string email
     * @validator Wedeto\HTTP\Forms\Validation\Email
     */
    public $email;
}

class MockForm10 extends BaseForm
{
    /**
     * @var string word
     * @validator Wedeto\HTTP\Forms\Validation\NonExisting
     */
    public $word;
}

class MockForm11 extends BaseForm
{
    /**
     * @var string bogus
     * @validator Wedeto\HTTP\Forms\FormField
     */
    public $bogus;
}

class MockForm12 extends BaseForm
{
    /**
     * @var string bogus
     * @validator Wedeto\HTTP\Forms\Validation\MinLength(3, 4, 5)
     */
    public $bogus;
}

class MockForm13 extends BaseForm
{
    /**
     * @var string
     */
    public $bogus;

    public function setBogus(string $value)
    {
        $this->bogus = "--" . $value . "--";
    }
}
