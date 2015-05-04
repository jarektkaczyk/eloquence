<?php namespace Sofa\Eloquence;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Sofa\Eloquence\Mutator\Mutator;

/**
 * @codeCoverageIgnore
 */
class ServiceProvider extends BaseProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('eloquence.mutator', function () {
            return new Mutator;
        });

        $this->app->alias('eloquence.mutator', 'Sofa\Eloquence\Contracts\Mutator');
    }
}
