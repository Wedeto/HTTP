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

/**
 * This file contains several Mock forms to be used in the binder
 * tests. All forms are based on BaseForm.
 */

namespace Wedeto\HTTP\Forms;

use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Validation\ValidationException;

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
