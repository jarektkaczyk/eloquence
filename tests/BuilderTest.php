<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;
use Mockery;
use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Searchable\ParserFactory;

class BuilderTest extends TestCase
{
    /** @test */
    public function it_takes_exactly_two_values_for_whereBetween()
    {
        $this->expectException(InvalidArgumentException::class);
        $builder = $this->getBuilder();
        $builder->whereBetween('size', [1, 2, 3]);
    }

    /** @test */
    public function it_calls_eloquent_method_if_called()
    {
        $builder = $this->getBuilder();
        $sql = $builder->callParent('where', ['foo', 'value'])->toSql();
        $this->assertEquals('select * from "table" where "foo" = ?', $sql);
    }

    protected function getBuilder()
    {
        $grammar = new Grammar;
        $connection = Mockery::mock('\Illuminate\Database\ConnectionInterface');
        $processor = Mockery::mock('\Illuminate\Database\Query\Processors\Processor');
        $query = new Query($connection, $grammar, $processor);
        $builder = new Builder($query);

        $joiner = Mockery::mock('stdClass');
        $joiner->shouldReceive('join')->with('foo', Mockery::any());
        $joiner->shouldReceive('join')->with('bar', Mockery::any());
        $factory = Mockery::mock('\Sofa\Eloquence\Relations\JoinerFactory');
        $factory->shouldReceive('make')->andReturn($joiner);
        Builder::setJoinerFactory($factory);

        Builder::setParserFactory(new ParserFactory);

        $model = new BuilderModelStub;
        $builder->setModel($model);

        return $builder;
    }
}

class BuilderModelStub extends Model
{
    use Eloquence;

    protected $table = 'table';
}
