<?php

namespace Sofa\Eloquence;

use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Mutator\Mutator;
use Sofa\Eloquence\Relations\JoinerFactory;
use Sofa\Eloquence\Searchable\ParserFactory;
use Illuminate\Support\ServiceProvider as BaseProvider;

/**
 * @codeCoverageIgnore
 */
class ServiceProvider extends BaseProvider
{
    public function boot()
    {
        Builder::setJoinerFactory(new JoinerFactory);

        Builder::setParserFactory(new ParserFactory);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerMutator();
        $this->registerJoiner();
        $this->registerParser();
    }

    /**
     * Register attribute mutator service.
     *
     * @return void
     */
    protected function registerMutator()
    {
        $this->app->singleton('eloquence.mutator', function () {
            return new Mutator;
        });

        $this->app->alias('eloquence.mutator', 'Sofa\Eloquence\Contracts\Mutator');
    }

    /**
     * Register relation joiner factory.
     *
     * @return void
     */
    protected function registerJoiner()
    {
        $this->app->singleton('eloquence.joiner', function () {
            return new JoinerFactory;
        });

        $this->app->alias('eloquence.joiner', 'Sofa\Eloquence\Contracts\Relations\JoinerFactory');
    }

    /**
     * Register serachable parser factory.
     *
     * @return void
     */
    protected function registerParser()
    {
        $this->app->singleton('eloquence.parser', function () {
            return new ParserFactory;
        });

        $this->app->alias('eloquence.parser', 'Sofa\Eloquence\Contracts\Relations\ParserFactory');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['eloquence.mutator', 'eloquence.joiner', 'eloquence.parser'];
    }
}
