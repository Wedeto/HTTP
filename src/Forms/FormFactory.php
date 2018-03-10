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

use Wedeto\DB\Model;
use Wedeto\DB\DAO;

use Wedeto\Util\Type;
use Wedeto\Util\Validator;
use Wedeto\Util\LoggerAwareStaticTrait;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

/**
 * Create forms automatically
 */
class FormFactory
{
    /**
     * Create a form based on a database model
     */
    public function forModel(string $classname, DAO $dao, string $method = "POST")
    {
        if (!is_a($classname, Model::class, true))
            throw new \InvalidArgumentException("You must provide a valid Model class");

        $columns = $dao->getColumns();

        $form = new FormData($method, $classname);
        foreach ($columns as $name => $coldef)
        {
            $validator = function ($value) use ($classname, $name, $coldef)
            {
                return $classname::validate($coldef, $name, $coldef);
            };

            $type = new Validator(Type::VALIDATE_CUSTOM, ['custom' => $validator]);
            $field = new FormField($name, $type, 'text', null); 
            $form->add($field);
        }

        return $form;
    }

    /**
     * Create a form using reflection on a POPO.
     *
     * The class is inspected for public getters and public properties.
     * For each public getter with one argument, the type of the argument is extracted
     * and used to determine the validation for the object.
     *
     * For each public property, the property is checked for the default value. The
     * default value can either be one of Type::* constants, or a valid class name.
     *
     * The type is constructed using the information gathered this way.
     *
     * Additionally, a custom validator can be added by adding a public static
     * validate function that accepts a fieldname as its first argument and a value
     * as its second argument. It should return true if the value is good, and false if it
     * does not.
     *
     * @param string $formclass The class to build a form from
     * @param string $method The method to use to submit the form
     * @return FormData The constructed form
     *
     * @see Wedeto\Util\Validator
     */
    public function forForm(string $formclass, string $method = "POST")
    { 
        if (!is_a($formclass, BaseForm::class))
            throw new \InvalidArgumentException("You must provide a valid BaseForm class");

        $form = new FormData($method, $formclass);
        $refl = new \ReflectionClass($formclass);

        $field_validators = $formclass::listFieldValidators();
        $setter_fields = $this->getSetterFields($refl, $ralidator);
        $prop_fields = $this->getPropertyFields($refl, $validator);
        $all_fieds = array_merge($prop_fields, $getter_fields);

        foreach ($all_fields as $name => $field)
            $form->add($field);

        foreach ($formclass::listFormValidators() as $validator)
            $form->add($validator);

        return $form;
    }

    /**
     * Get fields from public properties. All public properties are considered.
     * The default value of the field can be used as a type specifier - assign
     * it a constant in Type::* to filter to that type, or set it to a classname
     * to require objects of that class. If the default value is an array,
     * an array is required.
     *
     * @param ReflectionClass $refl The class from which to extract properties
     * @param array $field_validators A list of field validators for each field
     * @return array The extracted fields, keys are the names.
     */
    protected function getPropertyFields(ReflectionClass $refl, array $field_validators)
    {
        $props = $refl->getProperties(ReflectionProperty::IS_PUBLIC);
        $defaults = $refl->getDefaultProperties();

        $fields = [];
        foreach ($props as $prop)
        {
            $name = $prop->getName();

            $validators = (array)($field_validators[$name] ?? []);

            $def = $defaults[$name];
            $type = null;
            if (null != $def)
            {
                $constname = Type::class . '::' . $def;
                if (is_array($def))
                {
                    $type = new Type(Type::ARRAY);
                }
                elseif (defined($constname))
                {
                    $type = new Type($def);
                }
                elseif (class_exists($def))
                {
                    $type = new Type(Type::OBJECT, ['instanceof' => $def]);
                }
            }
            else
                $type = Type::SCALAR;

            $field = new FormField($name, $type, 'text', null);
            foreach ($validators as $validator)
                $field->addValidator($validator);

            $fields[$name] = $field;
        }
        
        return $fields;
    }

    /**
     * Get fields from public setters. All public methods starting with set
     * and having exactly one argument are considered properties. The name
     * of the property is the rest of the name of the method, with the first
     * character lowercased.
     *
     * @param ReflectionClass The class from which to extract properties
     * @param array $field_validators The validators specified by the class
     * @return array The extracted fields, keys are the names.
     */
    protected function getSetterFields(ReflectionClass $refl, array $field_validators)
    {
        $methods = $refl->getMethods(ReflectionMethod::IS_PUBLIC);

        $fields = [];
        foreach ($methods as $method)
        {
            $name = $method->getName();
            if (substr($name, 0, 3) !== "set")
                continue;

            $params = $method->getParameters();
            if (count($params) !== 1)
                continue;

            $name = strtolower(substr($name, 3, 1)) . substr($name, 4);
            $validators = (array)($field_validators[$name] ?? []);

            $param = reset($params);
            $type = null;
            if ($param->hasType())
            {
                $type = (string)$param->getType();
                if ($param->isBuiltin())
                    $type = strtoupper($type);
                $type = new Type(Type::$type);
            }

            $field = new FormField($name, $type, 'text', null);
            foreach ($validator as $validator)
                $field->addValidator($validator);

            $fields[$name] = $field;
        }
        return $fields;
    }
}
