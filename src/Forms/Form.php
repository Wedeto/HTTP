<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017-2018, Egbert van der Wal

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

use Wedeto\Util\Dictionary;
use Wedeto\Util\TypedDictionary;
use Wedeto\Util\Hook;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Functions as WF;

use Wedeto\HTTP\Request;
use Wedeto\HTTP\URL;
use Wedeto\HTTP\Session;
use Wedeto\HTTP\Nonce;

use ArrayIterator;
use InvalidArgumentException;

class Form implements FormElement, \Iterator, \ArrayAccess, \Countable
{
    protected $method = "POST";
    protected $endpoint;
    protected $name;
    protected $form_elements = [];
    protected $form_validators = [];
    protected $errors;
    protected $value = null;

    protected $required = true;
    protected $repeatable = false;
    protected $title;
    protected $description;
    protected $submit_text = 'Submit';

    /**
     * Create a new form
     *
     * @param string $name The name of the form. Used for nonces and scoping
     *                     when nesting forms.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->title = ucfirst($name);
    }

    /**
     * Add a field to the form
     * @param string $name The name of the field
     * @param Validator $type The validator to use to check the value
     * @param string $control_type The type hint for the control
     * @param mixed $value The default / initial value
     * @return Form Provides fluent interface
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
     * Add a field that validates the entire form, after each
     * individual field has been validated.
     * 
     * @param Validator $validator The validator that will be passed the entire form
     * @return Form Provides fluent interface
     */
    public function addFormValidator(Validator $validator)
    {
        $this->form_validators[] = $validator; 
        return $this;
    }

    /**
     * Set the name for the form
     *
     * @param string $name The name
     * @return Form Provides fluent interface
     */
    public function setName(string $name)
    {
        $this->name = $name;
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
     * Set the method to use for this form
     *
     * @param string $method The method to used, PUT, POST, GET, DELETE, PATCH etc
     * @return $this Provides fluent interface
     */
    public function setMethod(string $method)
    {
        $this->method = strtoupper($method);
        return $this;
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
    public function getControlType()
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
        return $this->title;
    }

    public function setDescription(string $desc)
    {
        $this->description = $desc;
        return $this;
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
        return $this->method === $request->method && $arguments['_form_name'] === $this->name;
    }

    /**
     * Check if the form submission is valid
     * @param Request $request The request containing the submitted data
     * @return bool True when the submission is valid, false otherwise
     */
    public function isValid(Request $request)
    {
        $arguments = $this->method === "GET" ? $request->get : $request->post;
        $files = $request->files;

        if (!$this->validate($arguments, $files))
            return false;

        $params = ['form' => $this, 'request' => $request, 'valid' => true, 'arguments' => $arguments];
        $params = TypedDictionary::wrap($params);
        $params->setType('errors', Type::ARRAY);
        $result = Hook::execute('Wedeto.HTTP.Forms.Form.isValid', $params);

        if (!$result['valid'])
        {
            foreach ($result['errors'] as $field => $errors)
            {
                if ($errors instanceof Dictionary)
                    $this->errors[$field] = $errors->toArray();
            }
            return false;
        }

        unset($this->form_elements[Nonce::getParameterName()]);
        unset($this->form_elements['_form_name']);

        return true;
    }
    
    /**
     * @return bool True when required, false when not
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * Set the required flag of the form
     * @param bool $required Whether the form is required
     * @return Form Provides fluent interface
     */
    public function setRequired(bool $required)
    {
        $this->required = $required;
        return $this;
    }

    /**
     * @return bool True if the values in this form occur multiple times, grouped
     */
    public function isRepeatable()
    {
        return $this->repeatable;
    }

    /**
     * Set the repeatable state of this subform
     * @param bool $repeatable Set to true to make this form an array of sub-elements
     * @return Form Provides fluent interface
     */
    public function setRepeatable(bool $repeatable)
    {
        $this->repeatable = $repeatable;
        return $this;
    }

    /**
     * Make sure the form was submitted correctly. When errors occur,
     * these can be obtained using getErrors.
     *
     * @param Dictionary $arguments The submitted arguments
     * @param Dictionary $files The submitted files
     * @return bool True if all fields validate, false if not.
     */
    public function validate(Dictionary $arguments, Dictionary $files)
    {
        // Check all submitted values
        $complete = true;
        $this->errors = [];
        $this->value = [];
        foreach ($this->form_elements as $name => $element)
        {
            if ($element instanceof Form)
            {
                // Subforms are validated as sub form to unwrap nested structures
                if (!$element->validateAsSubForm($arguments, $files))
                {
                    $this->errors[$name] = $element->getErrors();
                    $complete = false;
                }
            }
            elseif (!$element->validate($arguments, $files))
            {
                // Delegate validation to the form field
                $this->errors[$name] = $element->getErrors();
                $complete = false;
            }
            $this->value[$name] = $element->getValue();
        }

        foreach ($this->form_validators as $validator)
        {
            if (!$validator->validate($this))
            {
                $complete = false;
                $error = $validator->getErrorMessage($this);
                $this->errors[''][] = $error;
            }
        }

        return $complete;
    }

    /**
     * @return array All values in the form
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Validate the form as a sub form
     * 
     * @param Dictionary $arguments The submitted data
     * @param Dictionary $files The submitted files
     * @return True if the form valides, false if not.
     */
    public function validateAsSubForm(Dictionary $arguments, Dictionary $files)
    {
        $name = $this->name;
        if (!$arguments->has($name, Type::ARRAY))
        {
            $this->errors = [];
            if ($this->isRequired())
            {
                $this->errors = ['' => ['msg' => "{name} is required", 'context' => ['name' => $name]]];
                $this->complete = false;
            }
            return $this->complete;
        }

        $sub = $arguments->get($name);
        $subfiles = $files->has($name, Type::ARRAY) ? $files[$name] : new Dictionary;

        if (!$this->repeatable)
            return $this->validate($sub, $subfiles);

        // Repeatable forms should be an array of arrays - each containing
        // their own set of the values for the form elements. We need to
        // collect them all here.
        $value = [];
        $errors = [];

        // Only numeric keys are allowed for repeatable forms.
        if (!WF::is_numeric_array($sub))
        {
            $this->errors = ['' => ['msg' => "{name} should be an array", 'context' => ['name' => $name]]];
            $this->complete = false;
            return $this->complete;
        }

        // Validate each set of submitted data
        $valid = true;
        foreach ($sub as $idx => $subsub)
        {
            $subsubfiles = $subfiles->has($idx, Type::ARRAY) ? $subfiles[$idx] : new Dictionary;
            $valid = $valid && $this->validate($subsub, $subsubfiles);
            $subvalue = $this->getValue();
            $value[$idx] = $subvalue;

            if (count($this->errors))
                $errors[$idx] = $this->errors;
        }

        // Store the value and the errors for the form as a whole.
        $this->value = $value;
        $this->errors = $errors;
        return $valid;
    }

    /**
     * Prepare the form for rendering. This will generate the nonce and add the form name
     * @param Session $session The session used to generate the nonce. Omit to skip adding a nonce
     * @param bool $is_root_form Whether this is the root form or not. When
     *                           false, the _form_name element is not added
     * @return Form Provides fluent interface
     */
    public function prepare(Session $session = null, bool $is_root_form = true)
    {
        foreach ($this->form_elements as $field)
        {
            if ($field instanceof Form)
                $field->prepare(null, false);
        }

        $params = ['form' => $this, 'session' => $session, 'is_root_form' => $is_root_form];
        $params = TypedDictionary::wrap($params);
        Hook::execute('Wedeto.HTTP.Forms.Form.prepare', $params);

        if ($is_root_form)
            $this->form_elements['_form_name'] = new FormField('_form_name', Type::STRING, "hidden", $this->name);

        return $this;
    }

    /**
     * @return array the list of errors, grouped by name. Errors relating to the form as a while
     *               are returned with an empty string as key.
     */
    public function getErrors()
    {
        return $this->errors;
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
        return $this->form_elements[$offset] ?? null;
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
