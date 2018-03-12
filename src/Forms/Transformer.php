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

/**
 * Base class for transforms. Extend this class and register with the
 * transformstore to allow using objects of this field as form value.
 *
 * Transformer should:
 * - serialize from an object to a single string
 * - unserialize from a single string to an object
 *
 * The transformer is automatically invoked when an object of this type
 * is required to bind a form value.
 */
interface Transformer
{
    /** 
     * Apply transformer to the specified class and superclasses
     */
    const INHERIT_UP = 1;

    /**
     * Apply the transformer to the specified class and subclasses
     */
    const INHERIT_DOWN = -1;

    /**
     * Apply the transformer to the specified class only
     */
    const INHERIT_NONE = 0;

    /**
     * Serialize a value
     * @param object The object to serialize
     * @return string The serialized object
     */
    public function serialize($value);

    /**
     * Deserialize a value
     *
     * @param string The object to deserialize
     * @return object The deserialized object
     */
    public function deserialize($value);

    /**
     * @return int Inherit mode: INHERIT_UP, INHERIT_DOWN or INHERIT_NONE
     */
    public function getInheritMode();
}
