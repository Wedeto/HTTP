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

/**
 * @covers Wedeto\HTTP\Forms\DocComment
 */
final class DocCommentTest extends TestCase
{
    public function testParseDocComments()
    {
        $cmt = <<<EOC
/**
 * My preamble
 *
 * @var string A nice value
 * @return string Another return
 * @misc One misc
 * @misc Another misc
 * @foo
 */
EOC;
        
        $comment = new DocComment($cmt);

        $preamble = $comment->getPreamble();
        $this->assertContains('My preamble', $preamble);
        $this->assertEquals('string', $comment->getAnnotationFirst('var'));
        $this->assertEquals('string A nice value', $comment->getAnnotation('var'));
        $this->assertEquals('string Another return', $comment->getAnnotation('return'));
        $this->assertEquals('', $comment->getAnnotationFirst('foo'));

        $expected = ['One misc', 'Another misc'];
        $this->assertEquals($expected, $comment->getAnnotations('misc'));

        $all = [
            'var' => ['string A nice value'],
            'return' => ['string Another return'],
            'misc' => $expected,
            'foo' => ['']
        ];

        $this->assertEquals($all, $comment->getAll());
    }
}
