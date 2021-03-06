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

use Wedeto\Util\DI\DI;
use Wedeto\Util\Dictionary;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Validation\ValidationException;
use Wedeto\HTTP\Session;
use Wedeto\HTTP\URL;
use Wedeto\HTTP\Nonce;
use Wedeto\HTTP\Request;

/**
 * @covers Wedeto\HTTP\Forms\Form
 */
final class FormTest extends TestCase
{
    public function setUp()
    {
        DI::startNewContext('test');
        $this->nonce_hook = DI::getInjector()->getInstance(AddNonceToForm::class);
    }

    public function tearDown()
    {
        $this->nonce_hook->unregister();
        DI::destroyContext('test');
    }
    
    public function testConstructorAndGettersAndSetters()
    {
        $form = new Form('foobar');
        $this->assertEquals('foobar', $form->getName());
        $this->assertSame($form, $form->setName('another'));
        $this->assertEquals('another', $form->getName());

        $this->assertSame($form, $form->setMethod('POST'));
        $this->assertEquals('POST', $form->getMethod());

        $this->assertSame($form, $form->setMethod('GET'));
        $this->assertEquals('GET', $form->getMethod());

        $this->assertSame($form, $form->setMethod('PoSt'));
        $this->assertEquals('POST', $form->getMethod());

        $this->assertEquals('fieldset', $form->getControlType());

        $this->assertSame($form, $form->setEndPoint('/foo'));
        $this->assertEquals('/foo', $form->getEndPoint());

        $this->assertEquals('Foobar', $form->getTitle());
        $this->assertSame($form, $form->setTitle('A Nice Title'));
        $this->assertEquals('A Nice Title', $form->getTitle());

        $this->assertSame($form, $form->setDescription('some more information'));
        $this->assertEquals('some more information', $form->getDescription());

        $this->assertEquals('Submit', $form->getSubmitText());
        $this->assertSame($form, $form->setSubmitText('Send'));
        $this->assertEquals('Send', $form->getSubmitText());

        $this->assertTrue($form->isRequired());
        $this->assertSame($form, $form->setRequired(true));
        $this->assertTrue($form->isRequired());
        $this->assertSame($form, $form->setRequired(false));
        $this->assertFalse($form->isRequired());

        $this->assertFalse($form->isRepeatable());
        $this->assertSame($form, $form->setRepeatable(true));
        $this->assertTrue($form->isRepeatable());
        $this->assertSame($form, $form->setRepeatable(false));
        $this->assertFalse($form->isRepeatable());
    }

    public function testInterfaceImplementations()
    {
        $form = new Form('foobar');
        $form->add(new FormField('test1', Type::STRING, 'text', '1'));
        $form->addField('test2', Type::STRING, 'text', '2');
        $form->add(new FormField('test3', Type::INT, 'text', 3));
        $form->addField('test4', Type::INT, 'text', 4);

        $names = [];
        $els = [];

        foreach ($form as $name => $el)
        {
            $names[] = $name;
            $els[] = $el;
        }

        for ($i = 0; $i < count($names); ++$i)
        {
            $name = $names[$i];
            $this->assertEquals($els[$i], $form[$name]);
        }

        $this->assertEquals(4, count($names));
        $this->assertEquals(4, count($form));

        $this->assertTrue(isset($form['test1']));
        unset($form['test1']);
        $this->assertFalse(isset($form['test1']));
        $this->assertEquals(3, count($form));

        unset($form['test1']);
        $this->assertEquals(3, count($form));

        $this->assertTrue(isset($form['test2']));
        unset($form['test2']);
        $this->assertFalse(isset($form['test2']));
        $this->assertEquals(2, count($form));

        $this->assertTrue(isset($form['test3']));
        unset($form['test3']);
        $this->assertFalse(isset($form['test3']));
        $this->assertEquals(1, count($form));

        $this->assertTrue(isset($form['test4']));
        unset($form['test4']);
        $this->assertFalse(isset($form['test4']));
        $this->assertEquals(0, count($form));

        $fld = new FormField('test5', Type::STRING, 'text', 'foo');
        $form['test5'] = $fld;
        $this->assertEquals(1, count($form));
        $this->assertSame($fld, $form['test5']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Value must be a FormField");
        $form['test6'] = new \Stdclass;
    }

    public function testNonceing()
    {
        $session = new Session(new URL(''), new Dictionary, new Dictionary);
        $session->start();

        $form = new Form('foobar');
        $form->add(new FormField('test1', Type::STRING, 'text', '1'));
        $form->addField('test2', Type::STRING, 'text', '2');

        $this->assertSame($form, $form->prepare($session, true));

        $name_el = $nonce_el = null;
        foreach ($form as $name => $element)
        {
            if ($name === "_form_name")
            {
                $name_el = $element;
            }
            elseif ($element->getControlType() === "hidden")
            {
                $nonce_el = $element;
            }
        }

        $this->assertNotNull($name_el);
        $this->assertEquals("foobar", $name_el->getValue());
        $this->assertNotNull($nonce_el);
        $this->assertEquals("_nonce", $nonce_el->getName());

        $value = $nonce_el->getValue();
        $this->assertTrue(Nonce::validateNonce('foobar', $session, new Dictionary(['_nonce' => $value]), []));
    }

    public function testFormSubmitted()
    {
        $post = [
            'test1' => '88',
            'test2' => 'bar',
            '_form_name' => 'myform'
        ];
        $none = [];
        $server = ['REQUEST_METHOD' => 'POST'];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form = new Form('myform');
        $form->setMethod('POST');
        $form->addField('test1', Type::INT, 'text', '2');
        $form->addField('test2', Type::STRING, 'text', 'foo');
        $form->prepare($req->session);

        $nonce = $form['_nonce'];
        $post['_nonce'] = $nonce->getValue();

        $this->assertTrue($form->isSubmitted($req));
        $this->assertTrue($form->isValid($req));
    }

    public function testFormFailsWithoutNonce()
    {
        $post = ['test1' => 'foo'];
        $server = ['REQUEST_METHOD' => 'POST'];
        $form = new Form('test');
        $form->addField('test1', Type::STRING, 'text', 'test');

        $none = [];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary;
        $req->startSession(new URL('http://www.wedeto.nl/'), $cfg);
        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['_nonce']));
        $this->assertcontains('Nonce was not submitted', $errors['_nonce'][0]['msg']);

        $post['_nonce'] = 'invalid';
        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['_nonce']));
        $this->assertcontains('Nonce was invalid', $errors['_nonce'][0]['msg']);
    }

    public function testFormInvalidDataFailsValidation()
    {
        $post = ['test1' => 'foo'];
        $server = ['REQUEST_METHOD' => 'POST'];
        $form = new Form('test');
        $form->addField('test1', Type::INT, 'text', '1');

        $none = [];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary;
        $req->startSession(new URL('http://www.wedeto.nl/'), $cfg);
        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['test1']));

        $error = FormField::formatErrorMessage($errors['test1'][0]);
        $this->assertcontains('Integral value required', $error);
    }

    public function testFormWithSubforms()
    {
        $post = [
            'test1' => 'foo',
            'part' => ['foo' => 'bar'],
            '_form_name' => 'test'
        ];
        $none = [];
        $server = ['REQUEST_METHOD' => 'POST'];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form = new Form('test');
        $form->addField('test1', Type::STRING, 'text', 'test');
        
        $subform = new Form('part');
        $subform->addField('foo', Type::STRING, 'text', 'test2');
        $form->add($subform);
        $form->prepare($req->session);

        $post['_nonce'] = $form['_nonce']->getValue();

        $this->assertTrue($form->isSubmitted($req));
        $result = $form->isValid($req);

        $this->assertEquals([], $form->getErrors());
    }

    public function testFormWithSubformsWithInvalidData()
    {
        $post = [
            'test1' => 'foo',
            'part' => ['foo' => 'bar'],
            '_form_name' => 'test'
        ];
        $none = [];
        $server = ['REQUEST_METHOD' => 'POST'];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form = new Form('test');
        $form->addField('test1', Type::STRING, 'text', 'test');
        
        $subform = new Form('part');
        $subform->addField('foo', Type::INT, 'text', 'test2');
        $form->add($subform);
        $form->prepare($req->session);

        $post['_nonce'] = $form['_nonce']->getValue();

        $this->assertTrue($form->isSubmitted($req));
        $result = $form->isValid($req);
        $this->assertFalse($result);

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['part']), "Subform should have errors");
        $this->assertTrue(isset($errors['part']['foo']), "Foo field should have errors");

        $error = FormField::formatErrorMessage($errors['part']['foo'][0]);
        $this->assertContains('Integral value required', $error);
    }

    public function testValidatewithFormValidators()
    {
        $post = [
            'test1' => '3',
            'test2' => '1',
            '_form_name' => 'test'
        ];
        $none = [];
        $server = ['REQUEST_METHOD' => 'POST'];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form = new Form('test');
        $form->addField('test1', Type::INT, 'text', 'test');
        $form->addField('test2', Type::INT, 'text', 'test');
        $form->prepare($req->session);

        $post['_nonce'] = $form['_nonce']->getValue();

        $this->assertTrue($form->isValid($req));

        $validator = new Validator(Validator::VALIDATE_CUSTOM, ['custom' => function ($form) {
            $t1 = $form['test1'];
            $t2 = $form['test2'];
            if ($t2->getValue() <= $t1->getValue())
                throw new ValidationException("Test2 should be greater than test1");
            return true;
        }]);
        $form->addFormValidator($validator);
    
        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['']));
        $error = FormField::formatErrorMessage($errors[''][0]);
        $this->assertContains('should be greater than', $error);

        $post['test2'] = '5';
        $this->assertTrue($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertEquals([], $errors);
    }

    public function testValidateWithRepeatable()
    {
        $post = [
            'test1' => '3',
            'foo' => [
                [
                    'test2' => '1',
                    'test3' => 'foo'
                ],
                [
                    'test2' => '3',
                    'test3' => 'bar'
                ]
            ],
            '_form_name' => 'test'
        ];
        $none = [];
        $server = ['REQUEST_METHOD' => 'POST'];
        $req = new Request($none, $post, $none, $server, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form = new Form('test');
        $form->addField('test1', Type::INT, 'text', 'test');

        $form2 = new Form('foo');
        $form2->setRepeatable(true);
        $form2->addField('test2', Type::INT, 'text', 'test');
        $form2->addField('test3', Type::STRING, 'text', 'test');

        $form->add($form2);
        $GLOBALS['foobar'] = true;
        $form->prepare($req->session);
        $nnc = $form['_nonce'];
        $form->prepare($req->session);
        $GLOBALS['foobar'] = false;
        $post['_nonce'] = $form['_nonce']->getValue();
        $this->assertTrue($form->isValid($req));

        $post['foo'][] = ['test2' => 'bar','test3' => 'baz'];
        $this->assertFalse($form->isValid($req));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['foo'][2]['test2'][0]));
        $msg = FormField::formatErrorMessage($errors['foo'][2]['test2'][0]);
        $this->assertContains('Integral value required', $msg);

        $post['foo'] = "bar";
        $this->assertFalse($form->isValid($req));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['foo']));
        $msg = FormField::formatErrorMessage($errors['foo']['']);
        $this->assertContains('foo is required', $msg);

        $post['foo'] = ['bar' => "bar"];
        $this->assertFalse($form->isValid($req));

        $errors = $form->getErrors();
        $this->assertTrue(isset($errors['foo']));
        $msg = FormField::formatErrorMessage($errors['foo']['']);
        $this->assertContains('foo should be an array', $msg);
    }

}
