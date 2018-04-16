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

use PHPUnit\Framework\TestCase;

/**
 * @covers Wedeto\HTTP\ProcessChain
 */
final class ProcessChainTest extends TestCase
{
    public function setUp()
    {
        $this->get = [];
        $this->post = [];
        $this->cookie = [];
        $this->server = $_SERVER;
        $this->files = [];

        $this->server['REQUEST_METHOD'] = "GET";
        $this->request = new Request($this->get, $this->post, $this->cookie, $this->server, $this->files);
    }

    public function testChainRunInOrder()
    {
        $chain = new ProcessChain();

        $mock1 = new MockProcessor();
        $mock2 = new MockProcessor();
        $mock3 = new MockProcessor();

        $chain->addFilter($mock1);
        $chain->addProcessor($mock2);
        $chain->addPostProcessor($mock3);

        $start = MockProcessor::$seq;
        $chain->process($this->request);

        $this->assertEquals($start + 1, $mock1->my_sequence);
        $this->assertEquals($start + 2, $mock2->my_sequence);
        $this->assertEquals($start + 3, $mock3->my_sequence);
    }

    public function testChainRunInOrderWhenAddedInOtherOrder()
    {
        $chain = new ProcessChain();

        $mock1 = new MockProcessor();
        $mock2 = new MockProcessor();
        $mock3 = new MockProcessor();

        $chain->addProcessor($mock2);
        $chain->addPostProcessor($mock3);
        $chain->addFilter($mock1);

        $start = MockProcessor::$seq;
        $chain->process($this->request);

        $this->assertEquals($start + 1, $mock1->my_sequence);
        $this->assertEquals($start + 2, $mock2->my_sequence);
        $this->assertEquals($start + 3, $mock3->my_sequence);
    }

    public function testChainRunInOrderOfPrecedence()
    {
        $chain = new ProcessChain();

        $mock1 = new MockProcessor();
        $mock2 = new MockProcessor();
        $mock3 = new MockProcessor();

        $chain->addFilter($mock1, 10);
        $chain->addFilter($mock2, 5);
        $chain->addProcessor($mock3);

        $start = MockProcessor::$seq;
        $chain->process($this->request);

        $this->assertEquals($start + 2, $mock1->my_sequence);
        $this->assertEquals($start + 1, $mock2->my_sequence);
        $this->assertEquals($start + 3, $mock3->my_sequence);
    }

    public function testChainRunInOrderOfPrecedenceWithSkipAfterThrow()
    {
        $chain = new ProcessChain();

        $mock1 = new MockProcessor();
        $mock2 = new MockThrowingProcessor();
        $mock3 = new MockProcessor();
        $mock4 = new MockProcessor();

        $chain->addFilter($mock1, 10);
        $chain->addFilter($mock2, 5);
        $chain->addProcessor($mock3);
        $chain->addPostProcessor($mock4);

        $start = MockProcessor::$seq;
        $chain->process($this->request);

        $this->assertNull($mock1->my_sequence);
        $this->assertEquals($start + 1, $mock2->my_sequence);
        $this->assertNull($mock3->my_sequence);
        $this->assertEquals($start + 2, $mock4->my_sequence);
    }
}

class MockProcessor implements Processor
{
    public static $seq = 0;
    public $my_sequence = null;

    public function process(Request $req, Response $resp)
    {
        ++static::$seq;
        $this->my_sequence = static::$seq;
    }
}

class MockThrowingProcessor implements Processor
{
    public function process(Request $req, Response $resp)
    {
        ++MockProcessor::$seq;
        $this->my_sequence = MockProcessor::$seq;
        throw new Response\StringResponse("foobar");
    }
}
