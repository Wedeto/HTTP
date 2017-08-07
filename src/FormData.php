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

use ArrayIterator;

class FormData implements FormElement, \Iterator, \ArrayAccess, \Countable
{
    protected $method;
    protected $endpoint;
    protected $name;
    protected $form_elements = [];
    protected $errors;

    protected $title;
    protected $description;
    protected $submit_text = 'Submit';

    /**
     * Create a new form
     */
    public function __construct(string $method, string $name)
    {
        $this->method = strtoupper($method);
        $this->name = $name;
        $this->title = ucfirst($name);
    }

    /**
     * Add a field to the form
     * @param string $name The name of the field
     * @param Type $type The validator to use to check the value
     * @param string $control_type The type hint for the control
     * @param mixed $value The default / initial value
     * @return FormData Provides fluent interface
     */
    public function addField(string $name, $type, string $control_type, $value = '')
    {
        return $this->add(new FormField($name, $type, $control_type, $value));
    }

    /**
     * Add a field to the form
     * @param FormField $field The field to add
     */
    public function add(FormElement $field)
    {
        $this->form_elements[$field->getName()] = $field;
        return $this;
    }

    /**
     * @return string The name of the form
     */
    public function getName(bool $strip_array = false)
    {
        return $this->name;
    }

    /**
     * @return string The method to use to submit the form
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string The type of the control: fieldset
     */
    public function getType()
    {
        return "fieldset";
    }

    public function setEndPoint($url)
    {
        $this->endpoint = $url instanceof URL ? $url : new URL($url);
        return $this;
    }

    public function getEndPoint()
    {
        return $this->endpoint;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return ucfirst($this->name);
    }

    public function setDescription(string $desc)
    {
        $this->description = $desc;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setSubmitText(string $txt)
    {
        $this->submit_text = $txt;
        return $this;
    }

    public function getSubmitText()
    {
        return $this->submit_text;
    }

    /**
     * Check if the form has been submitted
     * @return bool True if the form was submitted, false if not
     */
    public function isSubmitted(Request $request)
    {
        $arguments = $this->method === "GET" ? $request->get : $request->post;

        // Check if the form has been submitted at all
        if ($this->method !== $request->method || !$arguments['_form_name'] !== $this->name)
            return false;
    }

    /**
     * Check if the form submission is valid
     * @param Request $request The request containing the submitted data
     * @return bool True when the submission is valid, false otherwise
     */
    public function isValid(Request $request)
    {
        if (!$this->validate($request, $this->method))
            return false;

        // Validate nonce
        $result = Nonce::validateNonce($this->name, $request->session, $arguments);
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

    /**
     * Make sure the form was submitted correctly
     */
    public function validate(Request $request, string $method)
    {
        $arguments = $method === "GET" ? $request->get : $request->post;

        // Check all posted values
        $complete = true;
        $this->errors = [];
        foreach ($this->form_elements as $name => $element)
        {
            if (!$element->validate($request, $method))
            {
                $complete = false;
                $this->errors[$name] = $element->getErrorMessage($value);
            }
        }

        if (!$complete)
            return false;
    }

    /**
     * Prepare the form for rendering. This will generate the nonce and add the form name
     * @param Session $session The session used to generate the nonce. Omit to skip adding a nonce
     * @param bool $is_root_form Whether this is the root form or not. When
     *                           false, the _form_name element is not added
     * @return FormData Provides fluent interface
     */
    public function prepare(Session $session = null, bool $is_root_form = true)
    {
        foreach ($this->form_elements as $field)
        {
            if ($field instanceof FormData)
                $field->prepare(null, false);
        }

        if ($session !== null)
        {
            $nonce_name = Nonce::getParameterName();
            if (!isset($this->form_elements[$nonce_name]))
            {
                $context = [];
                $nonce = Nonce::getNonce($this->name, $session, $context);
                $this->form_elements[$nonce_name] = new FormField($nonce_name, Type::STRING, "hidden", $nonce);
            }

        }

        if ($is_root_form)
            $this->form_elements['_form_name'] = new FormField('_form_name', Type::STRING, "hidden", $this->name);

        return $this;
    }

    /**
     * Rewind the array iterator
     */
    public function rewind()
    {
        $this->iterator = new ArrayIterator($this->form_elements);
    }

    /**
     * Move the iterator forward
     */
    public function next()
    {
        $this->iterator->next();
    }

    /**
     * @return bool True if the iterator is valid, false if not
     */
    public function valid()
    {
        return $this->iterator !== null && $this->iterator->valid();
    }

    /**
     * @return string The name of the current form element
     */
    public function key()
    {
        return $this->iterator->key();
    }

    /**
     * @return FormElement The current form element
     */
    public function current()
    {
        return $this->iterator->current();
    }

    /**
     * @return int The amount of form elements
     */
    public function count()
    {
        return count($this->form_elements);
    }

    /**
     * @return FormElement The element with the specified name
     */
    public function offsetGet($offset)
    {
        return $this->form_elements[$offset];
    }

    /**
     * Add or replace a form element
     * @param string $offset The name of the element
     * @param FormElement $element The element to set
     */
    public function offsetSet($offset, $value)
    {
        if (!($value instanceof FormField))
            throw new InvalidArgumentException("Value must be a FormField");

        $this->form_elements[$offset] = $value; 
    }

    /** 
     * Remove an element
     * @param string $offset The name of the element to remove
     */
    public function offsetUnset($offset)
    {
        unset($this->form_elements[$offset]);
    }

    /**
     * @param string $offset The offset to check
     * @return bool True when the specified offset exists, false if it does not
     */
    public function offsetExists($offset)
    {
        return isset($this->form_elements[$offset]);
    }
}
