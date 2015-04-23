<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Mappable;
use Sofa\Eloquence\Contracts\Mappable as MappableContract;

use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Eloquent\Model;

use Mockery as m;

class BuilderTest extends \PHPUnit_Framework_TestCase {

    public function tearDown()
    {
        m::close();
    }

    /**
     * @covers \Sofa\Eloquence\Builder::where
     */
    // public function it_adds_where_constraint_for_alias_mapping()
    // {
    //     $builder = $this->getBuilder();

    //     $sql = $builder->where('foo', 'value')->toSql();

    //     $this->assertEquals('select * from "table" where "bar" = ?', $sql);
    // }

    /**
     * @test
     * @covers \Sofa\Eloquence\Builder::parentWhere
     */
    public function it_calls_basic_where_if_explicitly_called()
    {
        $builder = $this->getBuilder();

        $sql = $builder->parentWhere('where', ['foo', 'value'])->toSql();

        $this->assertEquals('select * from "table" where "foo" = ?', $sql);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Builder::where
     * @covers \Sofa\Eloquence\Builder::isCustomWhere
     */
    // public function it_adds_where_constraint_for_nested_mappings()
    // {
    //     $alias   = 'aliased_column';
    //     $target  = 'deeply.nested.relation';
    //     $mapping = $target . '.column';

    //     $connection = m::mock('\Illuminate\Database\ConnectionInterface');
    //     $processor  = m::mock('\Illuminate\Database\Query\Processors\Processor');
    //     $grammar    = m::mock('\Illuminate\Database\Query\Grammars\Grammar');
    //     $query      = new Query($connection, $grammar, $processor);

    //     $builder = m::mock('\Sofa\Eloquence\Builder[getModel]', [$query]);
    //     $model = m::mock('\Sofa\Eloquence\Contracts\Mappable');
    //     $model->shouldReceive('customWhere')
    //         ->with($builder, ['key' => 'aliased_column', 'operator' => 'some_value', 'value' => '', 'boolean' => 'and'])
    //         ->andReturn($builder);

    //     $builder->shouldReceive('getModel')->andReturn($model);

    //     $builder->where('aliased_column', 'some_value');
    // }

    /**
     * @covers \Sofa\Eloquence\Builder::where
     * @covers \Sofa\Eloquence\Builder::baseWhere
     */
    // public function it_calls_basic_where_if_custom_not_applies()
    // {
    //     $builder = $this->getBuilder();

    //     $sql = $builder->where(['column' => 'value'])->toSql();

    //     $this->assertEquals('select * from "table" where ("column" = ?)', $sql);
    // }

    protected function getBuilder()
    {
        $grammar    = new \Illuminate\Database\Query\Grammars\Grammar;
        $connection = m::mock('\Illuminate\Database\ConnectionInterface');
        $processor  = m::mock('\Illuminate\Database\Query\Processors\Processor');
        $query      = new Query($connection, $grammar, $processor);
        $builder    = new Builder($query);

        $model = new BuilderMappableStub;
        $builder->setModel($model);

        return $builder;
    }
}

class BuilderMappableStub extends Model implements MappableContract {

    use Eloquence, Mappable;

    protected $table = 'table';
    protected $maps = [
        'foo' => 'bar',
        'aliased_column' => 'deeply.nested.relation.column',
    ];
}
