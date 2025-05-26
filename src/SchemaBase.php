<?php

namespace paulaba\LaravelJsonSchema;

use Exception;
use ReflectionEnum;
use ReflectionClass;
use ReflectionProperty;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;


class SchemaBase

{

    protected array $schema = [
        'type' => 'object',
        'properties' => [],
        'required' => [],
        'additionalProperties' => config('json-schema.default_additional_properties', false),
    ];



    // Types that should not be processed as classes
    protected array $skipForTypeArray = config('json-schema.skip_for_type_array', ['int', 'string', 'float', 'bool', 'array', 'object', 'mixed']);



    protected static array $validationRules = [];



    protected static array $validationMessages = [];



    protected static array $validationAttributes = [];



    protected static array $validationDescription = [];



    /**

     * SchemaBase constructor.

     *

     * @throws Exception

     */

    private function __construct(protected array $propertiesNeeded = [])

    {

        $this->buildSchema();
    }



    /**

     * @throws Exception

     */

    private function buildSchema(): void

    {

        $properties = self::getProperties();

        if (empty($this->propertiesNeeded)) {

            foreach ($properties as $property) {

                $this->processProperty($property);
            }
        } else {

            foreach ($properties as $property) {

                if (in_array($property->getName(), $this->propertiesNeeded)) {

                    $this->processProperty($property);
                }
            }
        }
    }



    /**

     * Process a class property.

     *

     * @param ReflectionProperty $property

     *

     * @throws Exception

     */

    private function processProperty(ReflectionProperty $property): void

    {

        $type = $property->getType();

        $propertyName = $property->getName();

        $isRequired = ! $property->hasDefaultValue();



        if (! $type) {

            throw new Exception("Property $propertyName must have a type declaration.");
        }



        $typeName = $type->getName();



        if ($typeName === 'array') {

            $this->processArrayProperty($property, $propertyName);
        } else {

            $this->schema['properties'][$propertyName] = $this->mapTypeToSchema($typeName, $propertyName);
        }

        if ($isRequired) {

            $this->schema['required'][] = $propertyName;
        }
    }



    /**

     * Process an array property.

     *

     * @param ReflectionProperty $property

     * @param string $propertyName

     */

    private function processArrayProperty(ReflectionProperty $property, string $propertyName): void

    {

        $docComment = $property->getDocComment();

        // Match annotations like "@var Type[]"

        if (preg_match('/@var\s+(\w+)\[\]/', $docComment, $matches)) {

            $arrayItemType = $matches[1];

            if (! class_exists($arrayItemType) && ! in_array($arrayItemType, $this->skipForTypeArray)) {

                $currentNamespace = (new ReflectionClass($this))->getNamespaceName();

                $arrayItemType = $currentNamespace . '\\' . $arrayItemType;
            }

            if (class_exists($arrayItemType)) {

                $this->schema['properties'][$propertyName] = [

                    'type' => 'array',

                    'description' => $this->getValidationDescription()[$propertyName] ?? '',

                    'items' => (new $arrayItemType)->getSchema(),

                ];
            } else {

                // Standard types (e.g., int[], string[], etc.)

                $this->schema['properties'][$propertyName] = [

                    'type' => 'array',

                    'description' => $this->getValidationDescription()[$propertyName] ?? '',

                    'items' => $this->mapTypeToSchema($arrayItemType, $propertyName),

                ];
            }
        } else {

            $this->schema['properties'][$propertyName] = [

                'type' => 'array',

                'description' => $this->getValidationDescription()[$propertyName] ?? '',

                'items' => ['type' => 'string'],

            ];
        }
    }



    /**

     * Map a PHP type to a JSON schema type.

     *

     * @param string $typeName

     * @return array

     */

    private function mapTypeToSchema(string $typeName, string $propertyName): array

    {

        if (class_exists($typeName) && is_subclass_of($typeName, SchemaBase::class)) {

            $nestedSchema = (new $typeName)->getSchema();



            return [

                'type' => 'object',

                'properties' => $nestedSchema['properties'],

                'required' => $nestedSchema['required'],

                'additionalProperties' => $nestedSchema['additionalProperties'] ?? false,

            ];
        }



        if (enum_exists($typeName)) {

            $enumValues = $this->getEnumValues($typeName);



            return [

                'type' => $this->getTypeForEnum($typeName),

                'enum' => $enumValues,

            ];
        }



        return match ($typeName) {

            'string' => ['type' => 'string', 'description' => $this->getValidationDescription()[$propertyName] ?? ''],

            'int', 'float', 'double' => ['type' => 'number', 'description' => $this->getValidationDescription()[$propertyName] ?? ''],

            'bool', 'boolean' => ['type' => 'boolean', 'description' => $this->getValidationDescription()[$propertyName] ?? ''],

            'array' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => $this->getValidationDescription()[$propertyName] ?? ''],

            default => ['type' => 'object', 'description' => $this->getValidationDescription()[$propertyName] ?? ''],
        };
    }



    /**

     * Get the schema for the class, which can be used to validate JSON data.

     */

    public function getSchema(): array

    {

        return $this->schema;
    }



    /**

     * Get the values of an enum class.

     */

    private function getEnumValues(string $enumClass): array

    {

        return array_map(fn($case) => $case->value, $enumClass::cases());
    }



    /**

     * Get the type for an enum class.

     */

    private function getTypeForEnum(string $enumClass): string

    {

        $reflectionEnum = new ReflectionEnum($enumClass);

        $backingType = $reflectionEnum->getBackingType();



        return $backingType ?? 'string';
    }



    /**

     * Create instances of subclasses.

     *

     * @param string $class

     * @param array $propertiesNeeded

     * @return static

     */

    public static function create(array $propertiesNeeded = []): static

    {

        return new static($propertiesNeeded);
    }



    public static function getProperties(): array

    {

        $reflection = new ReflectionClass(static::class);



        return $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }



    public static function getPropertiesNames(): array

    {

        $properties = static::getProperties();



        return array_map(fn($property) => $property->getName(), $properties);
    }



    protected function getValidationDescription()

    {

        if (property_exists(static::class, 'validationDescription')) {

            return static::$validationDescription;
        }



        return [];
    }



    /*

        * Create a schema for an array of objects.

        *

        * @param array $propertiesNeeded

        * @param string $arrayFieldName

        * @param string $fieldDescription

        * @return static
    */

    public static function createAsArray(array $propertiesNeeded = [], string $arrayFieldName = '', string $fieldDescription = ''): static

    {

        $instance = new static($propertiesNeeded);

        $itemSchema = $instance->getSchema();



        $instance->schema = [

            'type' => 'object',

            'properties' => [

                $arrayFieldName => [

                    'type' => 'array',

                    'description' => $fieldDescription,

                    'items' => $itemSchema,

                ],

            ],

            'required' => [$arrayFieldName],

            'additionalProperties' => false,

        ];



        return $instance;
    }



    protected static function extendValidationRules(): void {}



    /**

     * Get the validation rules defined in the subclass.

     */

    protected static function getValidationRules(): array

    {

        if (property_exists(static::class, 'validationRules')) {

            static::extendValidationRules();



            return static::$validationRules;
        }



        return [];
    }



    /**

     * Get custom validation messages defined in the subclass.

     */

    protected static function getValidationMessages(): array

    {

        return property_exists(static::class, 'validationMessages') ? (array) static::$validationMessages : [];
    }



    /**

     * Get custom attribute names for validation messages.

     */

    protected static function getValidationAttributes(): array

    {

        return property_exists(static::class, 'validationAttributes') ? (array) static::$validationAttributes : [];
    }



    protected function resolveClassName(string $typeName): string

    {

        $typeName = trim($typeName, '\\');

        if (str_contains($typeName, '\\')) {

            return $typeName;
        }

        if (in_array(strtolower($typeName), $this->skipForTypeArray)) {

            return strtolower($typeName);
        }

        if (class_exists($typeName) || enum_exists($typeName)) {

            return $typeName;
        }



        $currentNamespace = (new ReflectionClass(static::class))->getNamespaceName();

        if ($currentNamespace) {

            $potentialClass = $currentNamespace . '\\' . $typeName;

            if (class_exists($potentialClass) || enum_exists($potentialClass)) {

                return $potentialClass;
            }
        }



        return $typeName;
    }



    /**

     * Cleans the given data array by removing any keys that are not valid.

     *

     * @param array $data The associative array data to clean.

     * @return array The cleaned data array.

     */

    public function cleanData(array $data): array

    {

        $validationErrors = array_keys($this->validate($data));

        foreach ($validationErrors as $key) {

            Arr::forget($data, $key);
        }



        return $data;
    }



    /**

     * Validates the given data array against the static::$validationRules

     * using Validator. Also validates nested SchemaBase objects/arrays.

     *

     * @param array $data The associative array data to validate.

     * @return array|null An array of validation errors ['field' => ['message1', ...]],

     * or ['field.index.subfield' => [...] for nested errors].

     * Returns null if validation passes.

     *

     * @throws Exception

     */

    public function validate(array $data): ?array

    {

        $rules = static::getValidationRules();

        $messages = static::getValidationMessages();

        $attributes = static::getValidationAttributes();



        $validator = Validator::make($data, $rules, $messages, $attributes);



        $allErrors = [];

        if ($validator->fails()) {

            $allErrors = $validator->errors()->toArray();
        }

        $nestedErrors = $this->validateNestedItems($data);

        if (! empty($nestedErrors)) {

            $allErrors = array_merge_recursive($allErrors, $nestedErrors);
        }



        return empty($allErrors) ? [] : $allErrors;
    }



    /**

     * Helper to recursively validate nested items defined as SchemaBase subclasses.

     * Uses Reflection on properties to determine nested types

     *

     * @param array $data The data subset being validated.

     * @return array Errors found in nested structures, keyed by dot notation path.

     *

     * @throws Exception

     */

    protected function validateNestedItems(array $data): array

    {

        $nestedErrors = [];

        $reflectionClass = new ReflectionClass(static::class);



        foreach ($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {

            if ($property->getDeclaringClass()->getName() !== $reflectionClass->getName()) {

                continue;
            }



            $propName = $property->getName();

            if (! isset($data[$propName])) {

                continue;
            }

            $propData = $data[$propName];



            $propType = $property->getType();

            $resolvedItemClassName = null;



            if ($propType && $propType->getName() === 'array' && is_array($propData)) {

                $docComment = $property->getDocComment();

                if ($docComment && preg_match('/@var\s+(\w+)\[\]/', $docComment, $matches)) {

                    $rawItemType = $matches[1];

                    $itemClassName = $this->resolveClassName($rawItemType);

                    if (class_exists($itemClassName) && is_subclass_of($itemClassName, SchemaBase::class)) {

                        $resolvedItemClassName = $itemClassName;

                        foreach ($propData as $index => $itemData) {

                            if (is_array($itemData) || is_object($itemData)) {

                                $itemDataArray = is_object($itemData) ? (array) $itemData : $itemData;

                                try {

                                    $nestedInstance = $resolvedItemClassName::create();

                                    $errors = $nestedInstance->validate($itemDataArray);

                                    if ($errors) {

                                        foreach ($errors as $field => $messages) {

                                            $nestedErrors[$propName . '.' . $index . '.' . $field] = $messages;
                                        }
                                    }
                                } catch (\Throwable $e) {

                                    $nestedErrors[$propName . '.' . $index . '._error'] = ['Validation failed for nested item: ' . $e->getMessage()];
                                }
                            } else {

                                $nestedErrors[$propName . '.' . $index] = ['Item must be an object/array.'];
                            }
                        }
                    }
                }
            } elseif ($propType && ! $propType->isBuiltin() && (is_array($propData) || is_object($propData))) {

                $typeName = $propType->getName();



                $itemClassName = $this->resolveClassName($typeName);

                if (class_exists($itemClassName) && is_subclass_of($itemClassName, SchemaBase::class)) {

                    $resolvedItemClassName = $itemClassName;

                    $itemDataArray = is_object($propData) ? (array) $propData : $propData;

                    try {

                        $nestedInstance = $resolvedItemClassName::create();

                        $errors = $nestedInstance->validate($itemDataArray);

                        if ($errors) {

                            foreach ($errors as $field => $messages) {

                                $nestedErrors[$propName . '.' . $field] = $messages;
                            }
                        }
                    } catch (\Throwable $e) {

                        $nestedErrors[$propName . '._error'] = ['Validation failed for nested object: ' . $e->getMessage()];
                    }
                }
            }
        }



        return $nestedErrors;
    }
}
