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

use Wedeto\Util\DI\InjectionTrait;

/**
 * Maintain the list of transforms to convert values from- and to strings.
 */
class TransformStore
{
    use InjectionTrait;

    const WDI_REUSABLE = true;

    /**
     * The list of registered transformers
     */
    protected $transformers = [];

    /**
     * Get a transformer that converts to the target class. If a direct version
     * is available, that is returned. Otherwise, a transformer registered to
     * transform to a superclass of the target may be returned instead.
     *
     * @param string $target_class The class to transform to
     * @return Transformer A suitable transformer
     * @throws TransformException When no transform is available
     */
    public function getTransformer(string $target_class)
    {
        if (isset($this->transformers[$target_class]))
            return $this->transformers[$target_class];

        foreach ($this->transformers as $class => $transformer)
        {
            $inherit_mode = $transformer->getInheritMode();
            if (
                ($inherit_mode === Transformer::INHERIT_UP && is_a($class, $target_class, true))
                || ($inherit_mode === Transformer::INHERIT_DOWN && is_a($target_class, $class, true))
            )
            {
                return $transformer;
            }
        }

        throw new TransformException("No transform to class $target_class");
    }

    /**
     * Register a transformer transforming to a class
     *
     * @param string $class The class provided
     * @param Transformer $transformer The transformer to register.
     */
    public function registerTransformer(string $class, Transformer $transformer)
    {
        $this->transformers[$class] = $transformer;
        return $this;
    }
}
