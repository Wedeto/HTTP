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

/**
 * @covers Wedeto\HTTP\Forms\TransformStore
 */
final class TransformStoreTest extends TestCase
{
    public function setUp()
    {
        DI::startNewContext('test');
    }

    public function tearDown()
    {
        DI::destroyContext('test');
    }

    public function testGetUnknownTransform()
    {
        $store = TransformStore::getInstance();

        $this->expectException(TransformException::class);
        $this->expectExceptionMessage("No transform to class FooBar");
        $store->getTransformer(\FooBar::class);
    }

    public function testGetTransformReturnsDirectTransformer()
    {
        $store = TransformStore::getInstance();
        $mocker = $this->prophesize(Transformer::class);
        $tf = $mocker->reveal();
        $this->assertSame($store, $store->registerTransformer(MockClassA::class, $tf));
        $this->assertSame($tf, $store->getTransformer(MockClassA::class));
    }

    public function testGetTransformReturnsSubclassTransformer()
    {
        $store = TransformStore::getInstance();
        $mocker = $this->prophesize(Transformer::class);
        $mocker->getInheritMode()->willReturn(Transformer::INHERIT_DOWN);
        $tf = $mocker->reveal();

        $this->assertSame($store, $store->registerTransformer(MockClassA::class, $tf));
        $this->assertSame($tf, $store->getTransformer(MockClassB::class));

        $mocker->getInheritMode()->willReturn(Transformer::INHERIT_UP);
        $this->expectExceptionMessage("No transform to class");
        $this->expectException(TransformException::class);
        $store->getTransformer(MockClassB::class);
    }

    public function testGetTransformReturnsSuperclassTransformer()
    {
        $store = TransformStore::getInstance();
        $mocker = $this->prophesize(Transformer::class);
        $mocker->getInheritMode()->willReturn(Transformer::INHERIT_UP);
        $tf = $mocker->reveal();

        $this->assertSame($store, $store->registerTransformer(MockClassB::class, $tf));
        $this->assertSame($tf, $store->getTransformer(MockClassA::class));

        $mocker->getInheritMode()->willReturn(Transformer::INHERIT_DOWN);
        $this->expectExceptionMessage("No transform to class");
        $this->expectException(TransformException::class);
        $store->getTransformer(MockClassA::class);
    }
}

class MockClassA
{}

class MockClassB extends MockClassA
{}
