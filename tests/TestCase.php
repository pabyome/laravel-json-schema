<?php

namespace paulaba\LaravelJsonSchemaValidator\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use paulaba\LaravelJsonSchemaValidator\JsonSchemaServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            JsonSchemaServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
    }
}