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
use Wedeto\DB\Exception\InvalidValueException;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

use RuntimeException;

class BinderException extends RuntimeException
{ }

/**
 * Binds forms to object instances in two directions: generates forms
 * based on Model or BaseForm classes, and fills instances of these
 * classes using submitted data.
 */
class Binder
{
    /**
     * Create a form based on a database model
     * 
     * @param string $classname The name of the Model class
     * @param DAO $dao The DAO that provides more information about the class
     */
    public function formForModel(string $classname, DAO $dao)
    {
        if (!is_a($classname, Model::class, true))
            throw new \InvalidArgumentException("You must provide a valid Model class");

        $columns = $dao->getColumns();

        $form = new FormData($method, $classname);
        foreach ($columns as $name => $coldef)
        {
            $validator = function ($value) use ($classname, $coldef)
            {
                return $classname::validate($coldef, $value);
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
     * @return FormData The constructed form
     *
     * @see Wedeto\Util\Validator
     */
    public function formForObject(string $formclass)
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

    /**
     * Set values on an object from a (validated) form.
     * 
     * @param Form $form The form to get values from
     * @param string $class The classname of the object to instantiate and fill
     * @return The new, filled object.
     */
    public function bind(Form $form, string $class)
    {
        if (!((is_string($class) && class_exists($class)) || ($class instanceof ReflectionClass)))
            throw new BinderException("Provide a classname or a reflection class to bind");

        if ($class instanceof ReflectionClass)
        {
            $refl = $class;
            $class = $refl->getName();
            $isModel = $refl->isSubclassOf(Model::class);
            $isForm = $refl->isSubclassOf(BaseForm::class);
        }
        else
        {
            $refl = new ReflectionClass($class);
            $isModel = $refl->isSubclassOf(Model::class);
            $isForm = $refl->isSubclassOf(BaseForm::class);
        }

        if (!$isModel && !$isForm)
            throw new BinderException("Can only bind subclasses of BaseForm and Model");

        $instance = new $class;
        foreach ($form as $key => $formelement)
        {
            if ($formelement instanceof Form)
            {
                $this->bindSubForm($formelement, $refl, $instance);
            }
            else
            {
                $this->bindValue($formelement, $refl, $instance);
            }
        }
    }

    /**
     * Set a value from a form to an instance of a class
     * 
     * @param FormElement $element The element containing the value
     * @param ReflectionClass $refl The ReflectionClass of the object being set
     * @param object $instance The object being set
     */
    protected function bindValue(FormElement $element, ReflectionClass $refl, object $instance)
    {
        $name = $element->getName();
        $value = $element->getValue();
        $method_name = "set" . strtoupper($name, 0, 1) . substr($name, 1);
        if ($refl->hasMethod($method_name))
        {
            $setter = $refl->getMethod($method_name);
            $params = $setters->getParameters();
            if (count($params) !== 1)
                throw new BinderException("Setter $method_name should take exactly one argument");

            $param = reset($params);

            if ($param->hasType())
            {
                // If the parameter has a type hint, make sure it fits
                $transformedValue = $this->transform($param, $value);
                $setter->invoke($instance, $transformedValue);
            }
            else
            {
                // Otherwise, there's nothing we can do except just set it
                $setter->invoke($instance, $value);
            }
        }
        elseif ($refl->isSubclassOf(Model::class))
        {
            // Model provides a setField method that should be able to set all parameters
            $instance->setField($key, $formelement->getValue());
        }
        else
        {
            // No known accessors, the property should be settable directly
            if (!$refl->hasProperty($name))
                throw new BinderException("There is no attribute $name on class {$refl->getName()}");

            $property = $refl->getProperty($name);
            if (!$property->isPublic() || $property->isStatic())
                throw new BinderException("Property $name should be public and non-static");

            $property->setValue($instance, $value);
        }
    }

    /**
     * Bind a subform to a parameter of the instance
     *
     * @param Form $subform The subform to bind to a value
     * @param ReflectionClass $refl The ReflectionClass of the parent form
     * @param object $instance The instance to set values on
     */
    protected function bindSubForm(Form $subform, ReflectionClass $refl, object $instance)
    {
        $name = $subform->getName();
        // Nested forms are generated from setters that take Forms or Models as argument
        $method_name = "set" . strtoupper(substr($name, 0, 1)) . substr($name, 1);
        if (!$refl->hasMethod($method_name))
            throw new BinderException("Subform should match a setter with name $method_name");

        $setter = $refl->getMethod($method_name);
        if (!$setter->isPublic())
            throw new BinderException("Setter method $method_name should be public");

        $params = $setter->getParameters();
        if (count($params) !== 1)
            throw new BinderException("Setter method $method_name should take exactly 1 typed parameter");

        $param = reset($params);
        if (!$param->hasType())
            throw new BinderException("Setter method $method_name should have exactly 1 typed parameter");

        $type = (string)$setter->getType();
        if ($type === "array" && $subform->isRepeatable())
        {
            // To fill the array, a classname is still needed. The class should provide a
            // getClassOf method for that.
            $classname = $refl->getName();
            if (!method_exists([$classname, 'getClassOf']))
            {
                throw new BinderException(
                    "Cannot determine type of array elements for field "
                    . "{$this->name}. Provide getClassOf method."
                );
            }

            // Get the name of the class to set
            $subclassname = $classname::getClassOf($name);
            if (empty($subclassname) || !class_exists($subclassname))
                throw new BinderException("getClassOf provided invalid class for $name");

            $subclass = new ReflectionClass($classname);
        }
        else
        {
            $subclass = $setter->getClass();
        }

        $value = $this->bind($subform, $subclass);
        $setter->invoke($instance, $value);
    }
}
