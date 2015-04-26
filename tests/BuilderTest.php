<?php namespace Sofa\Eloquence\Tests;

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

        $model = new BuilderModelStub;
        $builder->setModel($model);

        return $builder;
    }
}

class BuilderModelStub extends Model {

    use Eloquence;

    protected $table = 'table';
}
