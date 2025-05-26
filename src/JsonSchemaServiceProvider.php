<?php

namespace paulaba\LaravelJsonSchemaValidator;

use Illuminate\Support\ServiceProvider;

class JsonSchemaServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/json-schema.php', 'json-schema'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/json-schema.php' => config_path('json-schema.php'),
        ]);
    }
}
