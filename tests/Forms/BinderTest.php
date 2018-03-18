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

    public function testConstruct()
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
