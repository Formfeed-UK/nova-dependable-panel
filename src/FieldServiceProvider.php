<?php

namespace Formfeed\DependablePanel;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

use Formfeed\DependablePanel\Http\Middleware\InterceptDependentFields;
use Formfeed\DependablePanel\Http\Middleware\InterceptDisplayFields;
use Formfeed\DependablePanel\Http\Middleware\InterceptValidationFailure;

class FieldServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        $this->addMiddleware();

        Nova::serving(function (ServingNova $event) {
            Nova::script('nova-dependable-panel', __DIR__.'/../dist/js/field.js');
            Nova::style('nova-dependable-panel', __DIR__.'/../dist/css/field.css');
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    public function addMiddleware()
    {
        $router = $this->app['router'];
        
        if ($router->hasMiddlewareGroup('nova')) {
            $router->pushMiddlewareToGroup('nova', InterceptDependentFields::class);
            $router->pushMiddlewareToGroup('nova', InterceptValidationFailure::class);
            $router->pushMiddlewareToGroup('nova', InterceptDisplayFields::class);
            return;
        }
        
        if (! $this->app->configurationIsCached()) {
            config()->set('nova.middleware', array_merge(
                config('nova.middleware', []),
                [InterceptDependentFields::class,
                InterceptValidationFailure::class,
                InterceptDisplayFields::class]
            ));
        }
    }
}
