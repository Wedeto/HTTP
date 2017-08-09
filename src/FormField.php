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

use Wedeto\Util\Type;
use Wedeto\Util\Functions as WF;
use Wedeto\Util\Dictionary;

class FormField implements FormElement
{
    const TYPE_FILE = "__FILE__";

    protected $validator;
    protected $name;
    protected $name_parts = [];
    protected $name_depth = 1;
    protected $value;
    protected $is_file = false;
    protected $is_array = false;

    protected $attributes = [];

    protected $title = '';
    protected $description = '';

    protected $errors = [];

    /**
     * Create a new form field
     * @param string $name The name of the field
     * @param Type $type The validator to use to check the value
     * @param mixed $value The default / initial value
     * @return FormData Provides fluent interface
     */
    public function __construct(string $name, $type, string $control_type, $value = '')
    {
        $this
            ->setName($name)
            ->setType($type)
            ->setControlType($control_type)
            ->setValue($value)
            ->setTitle(ucfirst($name));
    }

    /**
     * Set the control type hint.
     * @param string $type Should be a form element type such as text,
     *                     textarea, hidden, etc.
     */
    public function setControlType(string $type)
    {
        $this->control_type = strtolower($type);
        return $this;
    }

    /**
     * @return string The control type hint
     */
    public function getControlType()
    {
        return $this->control_type;
    }

    /** 
     * Change the name of the field.
     * @param string $name The name to set. If the name contains [] pairs,
     *                     the field type will be set to array.
     * @return FormField Provides fluent interface
     * @throws InvalidArgumentException When an invalid names as used. An invalid name
     *                                  is a name with more than one empty array pair,
     *                                  like foo[][]
     */
    public function setName(string $name)
    {
        $this->name = $name;
        while (preg_match('/^(.*)\[([^\]]*)\]$/', $name, $matches) === 1)
        {
            if (empty($matches[2]))
            {
                if ($this->name_depth > 1)
                    throw new \InvalidArgumentException("Invalid name: $name");
                $this->is_array = true;
            } 
            else
                array_unshift($this->name_parts, $matches[2]);

            $name = $matches[1];
            ++$this->name_depth;
        }
        array_unshift($this->name_parts, $name);

        return $this;
    }

    /**
     * Change the type of the field
     * @param Type $type The type to set. Set this to anything acceptable
     *                   to the Wedeto\Util\Type constructor, or to
     *                   FormField::TYPE_FILE to accept file uploads.
     * @return FormField Provides fluent interface
     */
    public function setType($type)
    {
        if ($type === FormField::TYPE_FILE)
        {
            $this->is_file = true;
            $type = Type::RESOURCE;
        }

        if (!$type instanceof Type)
            $type = new Type($type);

        $this->validator = $type;
        return $this;
    }


    /**
     * @return bool True when this field is a file upload, false if not
     */
    public function isFile()
    {
        return $this->is_file;
    }

    /**
     * @return bool True when this field is an array, false if not
     */
    public function isArray()
    {
        return $this->is_array;
    }

    /**
     * Validate the value
     * @param mixed $value The value to validate
     * @return bool True when the value validates, false if it does not
     */
    public function validate(Request $request, string $method)
    {
        $arguments = $method === "GET" ? $request->get : $request->post;
        $value = $this->extractValue($this->isFile() ? $request->files : $arguments);
        $this->setValue($value);

        $this->errors = [];
        if ($this->is_array)
        {
            // Validate arrays element by element
            if (WF::is_array_like($value) && !($value instanceof Dictionary))
                $value = new Dictionary($value);

            $this->error[] = $this->validator->getErrorMessage($value);
            if ($value instanceof Dictionary)
                return false;

            // Value should be a shallow array
            if (!$value->isShallow())
                return false;

            // Empty depends on the value of nullable
            if (count($value) === 0 && !$this->validator->isNullable())
                return false;

            // Validate each value of the array
            foreach ($value as $key => $v)
            {
                if (!$this->validator->validate($v))
                {
                    $this->errors[] = $this->validator->getErrorMessage($v);
                    return false;
                }
            }

            // All values validate
            $this->errors = [];
            return true;
        }

        $result = $this->validator->validate($value);
        $this->errors = $result ? null : [$this->validator->getErrorMessage($value)];
        return $result;
    }

    /**
     * Returns any errors that occured during validation
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @return string The name of the field
     */
    public function getName(bool $strip_array = false)
    {
        if ($strip_array)
        {
            $parts = $this->name_parts;
            $name = array_shift($parts);
            return $name . '[' . implode('][', $parts) . ']';
        }

        return $this->name;
    }

    /**
     * @return Type The validator for this element
     */
    public function getType()
    {
        return $this->validator;
    }

    public function setTitle(string $title)
    {
        $this->title = $title;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setDescription(string $desc)
    {
        $this->description = $desc;
    }

    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Returns the value, optionally HTML escaped
     * @param bool $html_escape Set to true to escape the output
     * @return mixed The value for the item
     */
    public function getValue(bool $html_escape = false)
    {
        return $html_escape ? htmlspecialchars($this->value) : $this->value;
    }
        
    /**
     * Change the value for this field. The value is not validated, this is
     * just to be referred to later, for example for showing to the visitor
     * @param mixed $value The value to set
     * @return FormField Provides fluent interface
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Extract the value submitted from the provided dictionary
     * @param Dictionary $dict Where to get the value from
     *
     * @return mixed The value from the dictionary, null if it doesn't exist
     */
    public function extractValue(Dictionary $dict)
    {
        $parts = $this->name_parts;

        $value = null;
        $depth = 1;
        while (count($parts) > 1)
        {
            $key = array_shift($parts);
            if (!$dict->has($key, Type::ARRAY))
                return null;

            $dict = $dict->get($key);
            ++$depth;
        }

        $key = array_shift($parts);
        $value = $dict->get($key);
        
        // No values submitted
        if (empty($value) && count($value) === 0)
            return null;

        if ($depth < $this->name_depth)
        {
            // Depth is one less than the amount of array levels,
            // because the last one was without a key. This
            // means that $value should not be a shallow array
            // containing no sub-arrays.
            if (!$value instanceof Dictionary || !$value->isShallow())
                return null;
        }

        // Result matches expected depth
        return $value;
    }

    public function getAttribute(string $name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function setAttribute(string $name, string $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }
    
    public function clearAttribute(string $name)
    {
        unset($this->attributes[$name]);
        return $this;
    }

    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;
        return $this;
    }
    
    public function getAttributes()
    {
        return $this->attributes;
    }
}
