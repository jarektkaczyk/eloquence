<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Mappable;
use Sofa\Eloquence\Contracts\Mappable as MappableContract;

use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Eloquent\Model;

use Mockery as m;

class BuilderTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->model = new ModelStub;
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Builder::where
     * @covers \Sofa\Eloquence\Builder::notPrefixed
     * @covers \Sofa\Eloquence\Builder::getColumnMapping
     * @covers \Sofa\Eloquence\Builder::nestedMapping
     */
    public function it_adds_where_constraint_for_alias_mapping()
    {
        $builder = $this->getBuilder();

        $sql = $builder->where('foo', 'value')->toSql();

        $this->assertEquals('select * from "table" where "bar" = ?', $sql);
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Builder::where
     * @covers \Sofa\Eloquence\Builder::mappedWhere
     * @covers \Sofa\Eloquence\Builder::notPrefixed
     * @covers \Sofa\Eloquence\Builder::getColumnMapping
     * @covers \Sofa\Eloquence\Builder::nestedMapping
     * @covers \Sofa\Eloquence\Builder::parseMapping
     */
    public function it_adds_where_constraint_for_nested_mappings()
    {
        $alias   = 'aliased_column';
        $target  = 'deeply.nested.relation';
        $mapping = $target . '.column';

        $connection = m::mock('\Illuminate\Database\ConnectionInterface');
        $processor  = m::mock('\Illuminate\Database\Query\Processors\Processor');
        $grammar    = m::mock('\Illuminate\Database\Query\Grammars\Grammar');
        $query      = new Query($connection, $grammar, $processor);

        $model = m::mock('\Sofa\Eloquence\Contracts\Mappable');
        $model->shouldReceive('hasMapping')->with($alias)->andReturn(true);
        $model->shouldReceive('getMappingForAttribute')->with($alias)->andReturn($mapping);

        $builder = m::mock('\Sofa\Eloquence\Builder[has,getModel]', [$query]);
        $builder->shouldReceive('getModel')->andReturn($model);
        $builder->shouldReceive('has')->with($target, '>=', 1, 'and', m::type('callable'))->andReturn($builder);

        $builder->where('aliased_column', 'value');
    }

    protected function getBuilder()
    {
        $grammar    = new \Illuminate\Database\Query\Grammars\Grammar;
        $connection = m::mock('\Illuminate\Database\ConnectionInterface');
        $processor  = m::mock('\Illuminate\Database\Query\Processors\Processor');
        $query      = new Query($connection, $grammar, $processor);
        $builder    = new Builder($query);

        $model = new MappableStub;
        $builder->setModel($model);

        return $builder;
    }
}

class MappableStub extends Model implements MappableContract {
    use Mappable;

    protected $table = 'table';
    protected $maps = [
        'foo' => 'bar',
    ];
}
