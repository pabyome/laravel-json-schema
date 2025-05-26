<?php

use paulaba\LaravelJsonSchemaValidator\SchemaBase; 

enum StringBackedEnum: string {
    case Alpha = 'alpha';
    case Beta = 'beta';
}

class TestSchema extends SchemaBase
{
    public string $name;
    public int $age;
    public bool $isActive = true;
    /** @var int[] */
    public array $scores;
    public ?StringBackedEnum $status;
    public string $unDescribedProperty;
}

it('generates a basic schema correctly', function () {
    $schemaInstance = TestSchema::create();
    $schema = $schemaInstance->getSchema();

    expect($schema['type'])->toBe('object');

    expect($schema['properties'])
        ->toHaveKey('name')
        ->and($schema['properties']['name'])->toBe(['type' => 'string', 'description' => ''])
        ->toHaveKey('age')
        ->and($schema['properties']['age'])->toBe(['type' => 'number', 'description' => ''])
        ->toHaveKey('isActive')
        ->and($schema['properties']['isActive'])->toBe(['type' => 'boolean', 'description' => ''])
        ->toHaveKey('scores')
        ->and($schema['properties']['scores']['type'])->toBe('array')
        ->and($schema['properties']['scores']['items'])->toBe(['type' => 'number', 'description' => '']) // Assuming description is empty if not set for array items in mapTypeToSchema
        ->toHaveKey('status')
        ->and($schema['properties']['status']['type'])->toBe('string')
        ->and($schema['properties']['status']['enum'])->toBe(['alpha', 'beta'])
        ->toHaveKey('unDescribedProperty')
        ->and($schema['properties']['unDescribedProperty'])->toBe(['type' => 'string', 'description' => '']);


    $requiredFields = ['name', 'age', 'scores', 'status', 'unDescribedProperty'];
    expect($schema['required'])->toBeArray()->toHaveCount(count($requiredFields));
    foreach ($requiredFields as $field) {
        expect($schema['required'])->toContain($field);
    }
    expect($schema['required'])->not->toContain('isActive');
});
