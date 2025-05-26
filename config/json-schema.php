<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Skip for Type Array
    |--------------------------------------------------------------------------
    |
    | Defines an array of primitive types that should not be processed as
    | classes when encountered in array type hints (e.g., string[]).
    |
    */
    'skip_for_type_array' => ['int', 'string', 'float', 'bool', 'array', 'object', 'mixed'],

    /*
    |--------------------------------------------------------------------------
    | Default Additional Properties
    |--------------------------------------------------------------------------
    |
    | Sets the default value for 'additionalProperties' in the generated
    | JSON schema. Set to true to allow extra properties by default.
    |
    */
    'default_additional_properties' => false,
];
