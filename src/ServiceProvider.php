<?php namespace Sofa\Eloquence;

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
        $this->app->singleton('eloquence.mutator', function () {
            return new Mutator;
        });

        $this->app->alias('eloquence.mutator', 'Sofa\Eloquence\Contracts\Mutator');
    }
}
