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

use Wedeto\Util\DocComment;
use Wedeto\Util\DI\DI;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;
use Wedeto\Util\Validation\ValidationException;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\DB\Exception\InvalidValueException;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

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
    public function createFormForModel(string $classname, DAO $dao)
    {
        if (!is_a($classname, Model::class, true))
            throw new \InvalidArgumentException("Not a valid Model class provided");

        $form = new Form($classname);
        $refl = new ReflectionClass($classname);
        $fields = $this->getAnnotatedFields($refl, []);

        $columns = $dao->getColumns();
        foreach ($columns as $name => $coldef)
        {
            if (isset($fields[$name]))
                continue;

            // Ignore serial columns
            if ($coldef->getSerial())
                continue;

            $validator = function ($value) use ($classname, $coldef)
            {
                return $classname::validate($coldef, $value);
            };

            $type = new Validator(Type::VALIDATE_CUSTOM, ['custom' => $validator]);
            $field = new FormField($name, $type, 'text', null); 

            $fields[$name] = $field;
        }

        foreach ($fields as $name => $field)
            $form->add($field);

        $this->addFormValidators($refl, [], $form);
        return $form;
    }

    /**
     * Create a form using reflection on a POPO.
     *
     * The class is inspected for public properties. All properties that have
     * annotations in doc comments are used in the form, unless they have an
     * ignore annotation.
     *
     * Supported annations are @var to provide the type. When @var is array,
     * you need to specify the type of the elements in @element. 
     * 
     * You can specify more validators for the field by adding @validator annotations.
     * Each annotation must be a Fully Qualified Class Name of a class can be be instantiated
     * using Wedeto\DI. A default constructor will suffice for this.
     *
     * If you need more complex validators, you can also override the static listFieldValidators
     * method to return an array with field names as key and arrays of validators as value.
     *
     * The class doc comment is used to assign validators to the form as a whole, using the
     * @validator annotation. The same rules for field validators apply. You can provide more
     * complex validators by overriding the static listFormValidators method.
     *
     * @param string $formclass The class to build a form from
     * @return FormData The constructed form
     *
     * @see Wedeto\Util\Validator
     */
    public function createFormForObject(string $formclass)
    { 
        if (!is_a($formclass, BaseForm::class, true))
            throw new \InvalidArgumentException("Not a valid BaseForm class provided");

        $form = new Form($formclass);
        $refl = new \ReflectionClass($formclass);

        $field_validators = $formclass::listFieldValidators();
        $fields = $this->getAnnotatedFields($refl, $field_validators);
        foreach ($fields as $name => $field)
            $form->add($field);

        $this->addFormValidators($refl, [$formclass, 'listFormValidators'](), $form);
        return $form;
    }

    /**
     * Add validators for the whole form
     *
     * @param ReflectionClass The reflection class to get validators from
     * @param array $additional_validators Validators to add
     * @param Form $form The form to add the validators to
     */
    protected function addFormValidators(ReflectionClass $refl, array $additional_validators, Form $form)
    {
        $classdoc = $refl->getDocComment();
        if (!empty($classdoc))
        {
            $classdoc = new DocComment($classdoc);
            foreach ($classdoc->getAnnotations('validator') as $validator)
            {
                if (!is_a($validator, Validator::class, true))
                    throw new BinderException("Invalid validator class: $validator");
                $validator = DI::getInjector()->getInstance($validator);
                $form->addFormValidator($validator);
            }
        }

        foreach ($additional_validators as $validator)
            $form->addFormValidator($validator);

        return $form;
    }

    /**
     * Iterate over all properties and add their values to the form
     *
     * @param ReflectionClass $class The class to extract properties from
     * @param array $field_validator Additional validators
     */
    protected function getAnnotatedFields(ReflectionClass $class, array $field_validators)
    {
        $properties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        $fields = [];
        
        foreach ($properties as $prop)
        {
            $name = $prop->getName();
            $comment = $prop->getDocComment();
            if ($comment === false)
                continue;

            $annotations = new DocComment($comment);
            if (!empty($annotations->getAnnotations("ignore")))
                continue;

            $var = $annotations->getAnnotationTokens("var");
            if (count($var) === 0 || empty($var[0]))
                throw new BinderException("No type defined for $prop");

            $base_tp = reset($var);
            $element_tp = $base_tp === "array" ? $annotations->getAnnotationTokens("element") : [];
            $element_tp = reset($element_tp);
            $search_type = $element_tp ?: $base_tp;

            $const_name = Type::class . '::' . strtoupper($search_type);
            $transformer = null;
            if (defined($const_name))
            {
                $type = new Type(constant($const_name), ['unstrict' => true]);
            }
            else
            {
                $type = new Validator(Type::OBJECT, ['instanceof' => $search_type]);
                $transformer = TransformStore::getInstance()->getTransformer($search_type);
            }

            $fieldname = $element_tp !== null ? $name . '[]' : $name;

            $field = new FormField($name, $type, 'text', '');
            if ($transformer)
                $field->setTransformer($transformer);

            foreach ($annotations->getAnnotations('validator') as $valtype)
            {
                $instance = $this->getValidatorInstance($valtype);
                $field->addValidator($instance);
            }

            $additional = $field_validators[$name] ?? [];
            foreach ($additional as $validator)
                $field->addValidator($validator);

            $error_override = $annotations->getAnnotation('error', true);
            if (!empty($error_override))
                $field->setFixedError(['msg' => $error_override]);

            $fields[$name] = $field;
        }
        return $fields;
    }

    /**
     * Instantiate a validator from a comment
     *
     * @param string $annotation The annotation
     * @return Validator The instantiated validator
     */
    protected function getValidatorInstance(string $annotation)
    {
        if (preg_match("/([a-zA-Z0-9_\\\\]+)\\((.*)\\)/", $annotation, $matches))
        {
            $classname = $matches[1];
            $params = $matches[2];
            $in_quote = false;
            $out_quote = false;
            $escaped = false;
            $current_arg = '';

            $args = [];

            for ($i = 0; $i < strlen($params); ++$i)
            {
                $ch = substr($params, $i, 1);
                if ($ch === '"')
                {
                    if ($in_quote)
                    {
                        if ($escaped)
                        {
                            $escaped = false;
                            $current_arg = substr($current_arg, 0, -1) . '"';
                        }
                        else
                        {
                            $in_quote = false;
                            $out_quote = true;
                        }
                    }
                    else if ($out_quote)
                    {
                        throw new BinderException("Unexpected quote on $i in $params");
                    }
                    else
                    {
                        $in_quote = true;
                    }
                    continue;
                }

                if ($ch === '\\' && $in_quote)
                {
                    $escaped = true;
                }
                else if ($escaped)
                {
                    $escaped = false;
                }

                if (!$in_quote && ($ch === ' ' || $ch === "\t"))
                    continue;

                if (!$in_quote && $ch === ',')
                {
                    $args[] = $current_arg;
                    $current_arg = '';
                    $out_quote = false;
                }
                else
                {
                    $current_arg .= $ch;
                }
            }

            if (!empty($current_arg))
            {
                $args[] = $current_arg;
            }

            if ($in_quote)
            {
                throw new BinderException("Invalid syntax - expected '\"'");
            }
        }
        else
        {
            $classname = trim($annotation);
            $args = [];
        }

        if (!class_exists($classname))
            throw new BinderException("Validator class does not exist: $classname");

        if (!is_a($classname, Validator::class, true))
            throw new BinderException("Invalid validator class: $classname");

        $refl = new ReflectionClass($classname);
        $construct = $refl->getConstructor();
        
        $params = $construct->getParameters();
        $param_names = [];
        foreach ($params as $param)
            $param_names[] = $param->getName();

        if (count($args) !== count($param_names))
        {
            throw new BinderException(
                "Invalid amount of arguments for validator constructor: " 
                . count($args) . " given but " . count($param_names) . " required"
            );
        }

        $args = array_combine($param_names, $args);
        return DI::getInjector()->newInstance($classname, $args);
    }

    /**
     * Set values on an object from a (validated) form.
     * 
     * @param Form $form The form to get values from
     * @param string $class The classname of the object to instantiate and fill
     * @return The new, filled object.
     */
    public function bind(Form $form, $class)
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
                throw new BinderException("Cannot bind nested forms");

            $this->bindValue($formelement, $refl, $instance);
        }
        return $instance;
    }

    /**
     * Set a value from a form to an instance of a class
     * 
     * @param FormElement $element The element containing the value
     * @param ReflectionClass $refl The ReflectionClass of the object being set
     * @param object $instance The object being set
     */
    protected function bindValue(FormElement $element, ReflectionClass $refl, $instance)
    {
        $name = $element->getName();
        if (substr($name, 0, 1) === "_")
            return;

        $value = $element->getValue();
        $method_name = "set" . strtoupper(substr($name, 0, 1)) . substr($name, 1);
        if ($refl->hasMethod($method_name))
        {
            $setter = $refl->getMethod($method_name);
            $params = $setter->getParameters();
            if (count($params) !== 1)
                throw new BinderException("Setter $method_name should take exactly one argument");

            $setter->invoke($instance, $value);
        }
        elseif ($refl->isSubclassOf(Model::class))
        {
            // Model provides a setField method that should be able to set all parameters
            $instance->setField($name, $element->getValue());
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
}
