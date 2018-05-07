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

namespace Wedeto\HTTP\Forms;

use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Functions as WF;
use Wedeto\Util\Dictionary;

use Wedeto\Log\Logger;

class FormField implements FormElement
{
    const TYPE_FILE = "__FILE__";

    protected $validators = [];
    protected $name;
    protected $name_parts = [];
    protected $name_depth = 1;
    protected $value;
    protected $is_file = false;
    protected $is_array = false;
    protected $transformer = null;

    protected $title = '';
    protected $description = '';

    protected $errors = [];
    protected $fixed_error = null;

    /**
     * Create a new form field
     * @param string $name The name of the field
     * @param Validator $validator The validator to use to check the value
     * @param mixed $value The default / initial value
     * @return FormData Provides fluent interface
     */
    public function __construct(string $name, $validator, string $control_type, $value = '')
    {
        $this
            ->setName($name)
            ->addValidator($validator)
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
                    throw new \InvalidArgumentException("Invalid name: {$this->name}");
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
     * @param Validator $validator The validator to add. Provide either
     *                   one of the constants in Type, or a instantiated
     *                   Validator. FormField::TYPE_FILE will be transparently
     *                   converted to a file upload form field.
     * @return FormField Provides fluent interface
     */
    public function addValidator($validator)
    {
        if ($validator === FormField::TYPE_FILE)
        {
            $this->is_file = true;
            $validator = Type::RESOURCE;
        }

        if (!$validator instanceof Validator)
        {
            $validator = new Validator($validator, ['unstrict' => true]);
        }

        $this->validators[] = $validator;
        return $this;
    }

    /**
     * Remove a validator with a specific index
     * @param int $index The index to remove
     * @return FormField Provides fluent interface
     */
    public function removeValidator(int $index)
    {
        $validators = $this->validators;
        unset($validators[$index]);
        $this->validators = array_values($validators);
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
     * @return bool True if the field is required, false if not
     */
    public function isRequired()
    {
        // If an empty value validates, the field is not required
        return !($this->validate(new Dictionary, new Dictionary));
    }

    /**
     * Validate the value
     * @param mixed $request The HTTP request containing the post data
     * @return bool True when the value validates, false if it does not
     */
    public function validate(Dictionary $arguments, Dictionary $files)
    {
        $source = $this->isFile() ? $files : $arguments;
        $value = $this->extractValue($source);
        $this->setValue($value, true);
        $value = $this->value;
        $this->errors = [];
        if ($this->is_array)
        {
            $name = $this->getName(true);
            $value = $this->isFile() ? $files[$name] : $arguments[$name];
            $this->setValue($value);

            // Validate arrays element by element
            if (WF::is_array_like($value) && !($value instanceof Dictionary))
                $value = new Dictionary($value);

            if (!$value instanceof Dictionary)
            {
                $this->errors[''][] = [
                    'msg' => 'Invalid value for {name}: {value}', 
                    'context' => ['name' => $this->name, 'value' => (string)$value]
                ];
                return false;
            }

            // Value should be a shallow array
            if (!$value->isShallow())
            {
                $this->errors[''][] = [
                    'msg' => 'Field {name} should not nest',
                    'context' => ['name' => $this->name]
                ];
            }

            // Empty depends on the value of nullable
            if (count($value) === 0)
            {
                $nullable = true;
                foreach ($this->validators as $validator)
                    $nullable = $nullable && $validator->isNullable();

                if (!$nullable)
                {
                    $this->errors[''][] = [
                        'msg' => 'Required field: {name}',
                        'context' => ['name' => $this->name]
                    ];
                }
            }

            // Validate each value of the array
            foreach ($value as $key => $v)
            {
                foreach ($this->validators as $validator)
                {
                    if (!$validator->validate($v))
                    {
                        $this->errors[$key][] = $validator->getErrorMessage($v);
                    }
                }
            }

            // Check if any errors were produced
            return count($this->errors) === 0;
        }

        foreach ($this->validators as $validator)
        {
            $result = $validator->validate($this->value, $value);
            if (!$result)
            {
                $this->errors[] = $validator->getErrorMessage($value);
            }
            else
            {
                $this->value = $value;
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * Return the error messages produced during validation. Should be called
     * after validation failed, otherwise an non-relevant or empty array will be
     * returned.
     *
     * @return array List of errors. Empty when validation succeeded.
     */
    public function getErrors()
    {
        if (!empty($this->fixed_error))
            return count($this->errors) ? [$this->fixed_error] : [];

        return $this->errors;
    }

    /**
     * Set the fixed error that is returned when validation fails. Overrides the
     * errors from the validators themselves.
     *
     * @param array $msg The fixed error
     * @return FormField Provides fluent interface
     */
    public function setFixedError(array $msg)
    {
        if (!isset($msg['msg']))
            throw new InvalidArgumentException("Error message does not contain msg key");
        $this->fixed_error = $msg;
        return $this;
    }

    /**
     * Get the fixed error message
     * @retur array The fixed error message
     */
    public function getFixedError()
    {
        return $this->fixed_error;
    }

    /**
     * Set the form field with an error state. This will override the
     * fixed error.
     *
     * @param array $msg The error message. Must contain a 'msg' key.
     * @return FormField provides fluent interface
     */
    public function setError(array $msg)
    {
        if (!isset($msg['msg']))
            throw new InvalidArgumentException("Error message does not contain msg key");

        $this->errors = [$msg];
        $this->fixed_error = $msg;
        return $this;
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
            return $name;
        }

        return $this->name;
    }

    /**
     * @return array The list of validators for this element
     */
    public function getValidators()
    {
        return $this->validators;
    }

    /**
     * Get the validator with a specific index
     * @param int $index The index of the validator
     * @return Validator The validator at that index. Null if it doesn't exist.
     */
    public function getValidator(int $index)
    {
        return $this->validators[$index] ?? null;
    }

    /**
     * Set the title of the field
     * @param string $title The title
     * @return FormField Provides fluent interface
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * @return string The title of the field
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Set the description of the field
     * @param string $desc The description
     * @return FormField Provides fluent interface
     */
    public function setDescription(string $desc)
    {
        $this->description = $desc;
        return $this;
    }

    /**
     * @return string The description of the field
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set a transformer that serializes / deserializes values
     * @param Transformer The Transformer to use
     * @return FormField Provides fluent interface
     */
    public function setTransformer(Transformer $transformer)
    {
        $this->transformer = $transformer;
        return $this;
    }

    /**
     * @return Transformer The transformer used by this field
     */
    public function getTransformer()
    {
        return $this->transformer;
    }

    /**
     * Returns the value, optionally HTML escaped
     * @param bool $transform Set to true to run the transformer on the value, false
     *                        to return the raw value.
     * @return mixed The value for the item
     */
    public function getValue(bool $transform = false)
    {
        if ($transform && $this->transformer !== null)
            return $this->transformer->serialize($this->value);
        return $this->value;
    }
        
    /**
     * Change the value for this field. The value is not validated, this is
     * just to be referred to later, for example for showing to the visitor
     *
     * @param mixed $value The value to set
     * @param bool $transform Whether to run the transformer on it before setting
     * @return FormField Provides fluent interface
     */
    public function setValue($value, bool $transform = false)
    {
        if (null === $value)
            $this->value = null;
        elseif ($transform && $this->transformer !== null)
            $this->value = $this->transformer->deserialize($value);
        else
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

    /** 
     * Format the error message into a single string
     *
     * @return string The formatted error message
     */
    public static function formatErrorMessage(array $error_message)
    {
        $msg = $error_message['msg'] ?? "";
        $context = $error_message['context'] ?? [];
        if (class_exists("Wedeto\I18n\I18nShortcut"))
        {
            return t($msg, $context);
        }

        return WF::fillPlaceholders($msg, $context);
    }
}
