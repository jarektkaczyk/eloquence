<?php

namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Mappable;

use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Eloquent\Model;

use Mockery as m;

class BuilderTest extends \PHPUnit_Framework_TestCase {

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function it_joins_relations_as_strings_or_array()
    {
        $builder = $this->getBuilder();

        $builder->leftJoinRelations('foo', 'bar');
        $builder->rightJoinRelations(['foo', 'bar']);
        $builder->joinRelations('foo', 'bar');
        $builder->joinRelations(['foo', 'bar']);
    }

    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     */
    public function it_takes_exactly_two_values_for_whereBetween()
    {
        $builder = $this->getBuilder();

        $builder->whereBetween('size', [1,2,3]);
    }

    /**
     * @test
     */
    public function it_calls_eloquent_method_if_called()
    {
        $builder = $this->getBuilder();

        $sql = $builder->callParent('where', ['foo', 'value'])->toSql();

        $this->assertEquals('select * from "table" where "foo" = ?', $sql);
    }

    protected function getBuilder()
    {
        $grammar    = new \Illuminate\Database\Query\Grammars\Grammar;
        $connection = m::mock('\Illuminate\Database\ConnectionInterface');
        $processor  = m::mock('\Illuminate\Database\Query\Processors\Processor');
        $query      = new Query($connection, $grammar, $processor);
        $builder    = new Builder($query);

        $joiner = m::mock('stdClass');
        $joiner->shouldReceive('join')->with('foo', m::any());
        $joiner->shouldReceive('join')->with('bar', m::any());
        $factory = m::mock('\Sofa\Eloquence\Relations\JoinerFactory');
        $factory->shouldReceive('make')->andReturn($joiner);
        Builder::setJoinerFactory($factory);

        Builder::setParserFactory(new \Sofa\Eloquence\Searchable\ParserFactory);

        $model = new BuilderModelStub;
        $builder->setModel($model);

        return $builder;
    }
}

class BuilderModelStub extends Model {

    use Eloquence;

    protected $table = 'table';
}
