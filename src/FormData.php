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

use Wedeto\Util\Dictionary;
use Wedeto\Util\Type;

class FormData
{
    protected $method;
    protected $name;
    protected $form_elements = [];
    protected $errors;

    /**
     * Create a new form
     */
    public function __construct(string $method, string $name)
    {
        $this->method = $method;
        $this->name = $name;
    }

    /**
     * Add a field to the form
     * @param string $name The name of the field
     * @param Type $type The validator to use to check the value
     * @param mixed $value The default / initial value
     * @return FormData Provides fluent interface
     */
    public function addField(string $name, $type, $value = null)
    {
        return $this->add(new FormField($name, $type, $value));
    }

    /**
     * Add a field to the form
     * @param FormField $field The field to add
     */
    public function add(FormField $field)
    {
        $tihs->field[$field->getName()] = $field;
        return $this;
    }

    /**
     * Make sure the form was submitted correctly
     */
    public function validate(Request $request)
    {
        $arguments = $this->method === "GET" ? $request->get : $request->post;

        // Check if the form has been submitted at all
        if ($method !== $request->method || !$arguments['submit_form'] !== $this->name)
        {
            foreach ($this->form_elements as $k => $v)
                $this->errors[$k] = $this->form_elements->getErrorMessage(null);

            return false;
        }

        // Check all posted values
        $complete = true;
        $this->errors = [];
        foreach ($this->form_elements as $name => $field)
        {
            $value = $field->extractValue($field->isFile() ? $request->files : $arguments);
            $field->setValue($value);

            if ($field->validate($value))
            {
                $complete = false;
                $this->errors[$name] = $field->getErrorMessage($value);
            }
        }

        if (!$complete)
            return false;

        // Validate nonce
        $result = Nonce::validateNonce($this->name, $arguments, $request->session);
        if ($result === null)
        {
            $this->errors['nonce'] = [
                'msg' => 'Nonce was not submitted for form {form}',
                'context' => ['form' => $this->name]
            ];
            return false;
        }

        if ($result === false)
        {
            $this->errors['nonce'] = [
                'msg' => 'Nonce was invalid for form {form}',
                'context' => ['form' => $this->name] 
            ];
            return false;
        }

        return true;
    }
}
