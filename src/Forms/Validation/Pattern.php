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

namespace Wedeto\HTTP\Forms\Validation;

use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\ValidationException;
use Wedeto\Util\ErrorInterceptor;
use InvalidArgumentException;

class Pattern extends Validator
{
    const ERROR_MESSAGE = "Match with pattern required: {pattern}";

    protected $pattern;

    public function __construct(string $pattern)
    {
        $this->setPattern($pattern);
        parent::__construct(Type::STRING, ['custom' => [$this, 'validatePattern']]);
    }

    public function validatePattern($value)
    {
        if (!preg_match($this->pattern, $value))
        {
            throw new ValidationException(
                [
                    'msg' => 'Match with pattern {pattern} required',
                    'context' => ['pattern' => $this->pattern]
                ]
            );
        }
        return true;
    }

    public function setPattern(string $pattern)
    {
        // Validate the pattern by calling it.
        $err = new ErrorInterceptor("preg_match");
        $err->registerError(E_WARNING, "preg_match");
        $err->execute($pattern, "");
        
        $intercepted = $err->getInterceptedErrors();
        if (count($intercepted))
        {
            $msg = reset($intercepted)->getMessage();
            throw new InvalidArgumentException("Pattern $pattern fails: $msg");
        }   

        $this->pattern = $pattern;
    }
}
