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

namespace Wedeto\HTTP\Forms;

use PHPUnit\Framework\TestCase;

use Wedeto\Util\DI\DI;
use Wedeto\Util\Dictionary;
use Wedeto\Util\Validation\Type;
use Wedeto\HTTP\Nonce;
use Wedeto\HTTP\Request;
use Wedeto\HTTP\URL;

/**
 * @covers Wedeto\HTTP\Forms\AddNonceToForm
 */
final class AddNonceToFormTest extends TestCase
{
    public function setUp()
    {
        DI::startNewContext('test');
    }

    public function tearDown()
    {
        $inst = DI::getInjector()->getInstance(AddNonceToForm::class);
        if ($inst !== null)
            $inst->unregister();

        DI::destroyContext('test');
    }

    public function testNonceIsAddedToForm()
    {
        $inst = DI::getInjector()->getInstance(AddNonceToForm::class);
        $inst->register();

        $nonce_name = Nonce::getParameterName();

        $form = new Form('person');
        $form->addField('name', Type::STRING, 'text');
        $form->addField('age', Type::INT, 'text');

        $this->assertTrue(isset($form['name']));
        $this->assertTrue(isset($form['age']));
        $this->assertFalse(isset($form[$nonce_name]));

        $post = ['name' => 'foo', 'age' => '25', '_form_name' => 'person'];
        $none = [];
        $req = new Request($none, $post, $none, $none, $none);
        $cfg = new Dictionary([]);
        $req->startSession(new URL('http://www.wedeto.net/'), $cfg);

        $form->prepare($req->session, true);
        $this->assertTrue(isset($form[$nonce_name]));

        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $this->assertTrue(isset($errors[$nonce_name]));

        $post[$nonce_name] = "foobar";
        $this->assertFalse($form->isValid($req));
        $errors = $form->getErrors();
        $msg = FormField::formatErrorMessage($errors['_nonce'][0]);
        $this->assertEquals('Nonce was invalid for form person', $msg);

        $nonce = Nonce::getNonce('person', $req->session, [], time());
        $post[$nonce_name] = $nonce;
        $this->assertTrue($form->isValid($req));
    }
}
