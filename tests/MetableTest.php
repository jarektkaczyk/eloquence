<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Metable;

class MetableTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::whereMeta
     */
    public function it_queries_meta_attributes_relation()
    {
        $model = $this->getModel();
        $model->shouldReceive('getMetaWhereConstraint')->andReturn('closure');

        $query = m::mock('StdClass');
        $query->shouldReceive('has')->with('metaAttributes', '>=', 1, 'and', 'closure')->once()->andReturn($query);
        $query->shouldReceive('with')->with('metaAttributes')->once()->andReturn($query);

        $model->whereMeta($query, 'color', '=', 'red', 'and');
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::getMetaWhereConstraint
     */
    public function it_applies_meta_value_constraint_on_the_query()
    {
        $query = m::mock('StdClass');
        $query->shouldReceive('where')->with('key', 'color')->once();
        $query->shouldReceive('where')->with('value', '=', 'red')->once();

        $model = $this->getModel();
        $closure = $model->getMetaWhereConstraint('color', '=', 'red');

        call_user_func($closure, $query);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::getMetaWhereConstraint
     */
    public function it_applies_meta_key_constraint_on_the_query()
    {
        $query = m::mock('StdClass');
        $query->shouldReceive('where')->with('key', 'color')->once();

        $model = $this->getModel();
        $closure = $model->getMetaWhereConstraint('color');

        call_user_func($closure, $query);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::saveMeta
     */
    public function it_saves_meta_attributes()
    {
        $size = m::mock('StdClass');
        $size->shouldReceive('getValue')->andReturn(null);
        $size->shouldReceive('delete')->once();

        $color = m::mock('StdClass');
        $color->shouldReceive('getValue')->andReturn('red');

        $relation = m::mock('StdClass');
        $relation->shouldReceive('save')->with($color);

        $model = $this->getModel();
        $model->shouldReceive('getMetaAttributes')->andReturn([$color, $size]);
        $model->shouldReceive('metaAttributes')->andReturn($relation);

        $model->saveMeta();
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::allowsMeta
     */
    public function it_allows_any_attribute_if_not_restricted()
    {
        $model = new MetableStub;

        $this->assertTrue($model->allowsMeta('foo'));
        $this->assertTrue($model->allowsMeta('bar'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::allowsMeta
     */
    public function it_allows_only_defined_attributes_if_provided()
    {
        $model = new MetableStub;
        $model->allowedMeta = ['color'];

        $this->assertTrue($model->allowsMeta('color'));
        $this->assertFalse($model->allowsMeta('size'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::hasMeta
     */
    public function it_checks_not_null_attributes()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('lists')->with('value', 'key')->once()->andReturn(['color' => 'red', 'size' => null]);

        $model = $this->getModel();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $this->assertTrue($model->hasMeta('color'));
        $this->assertFalse($model->hasMeta('size'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::getMeta
     */
    public function it_gets_attribute_from_the_bag()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('getValue')->with('color')->once();

        $model = $this->getModel();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $model->getMeta('color');
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::setMeta
     */
    public function it_sets_attribute_on_the_bag()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('set')->with('color', 'red')->once();

        $model = $this->getModel();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $model->setMeta('color', 'red');
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::getMetaAttributesArray
     */
    public function it_gets_meta_attributes_as_key_value_array()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('lists')->with('value', 'key')->once()->andReturn(['color' => 'red']);

        $model = $this->getModel();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $this->assertEquals(['color' => 'red'], $model->getMetaAttributesArray());
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::loadMetaAttributes
     * @covers \Sofa\Eloquence\Metable::getMetaAttributes
     */
    public function it_loads_meta_attributes_relation_if_not_loaded()
    {
        $model = $this->getModel();
        $model->relations = [];
        $model->shouldReceive('load')->with('metaAttributes')->once()->andReturn($model);
        $model->shouldReceive('getRelation')->with('metaAttributes')->once();

        $model->getMetaAttributes();
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable::getAllowedMeta
     * @covers \Sofa\Eloquence\Metable::setAllowedMeta
     */
    public function it_gets_empty_array_or_provided_allowed_meta_attributes()
    {
        $model = new MetableStub;
        $this->assertEquals([], $model->getAllowedMeta());

        $model->setAllowedMeta(['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $model->getAllowedMeta());
    }

    public function getModel()
    {
        return m::mock('\Sofa\Eloquence\Tests\MetableStub')->makePartial();
    }

    public function getBag()
    {
        return m::mock('\Sofa\Eloquence\Metable\AttributeBag');
    }
}

class MetableStub {
    use Eloquence, Metable {
        saveMeta               as protectedSaveMeta;
        whereMeta              as protectedWhereMeta;
        getMetaWhereConstraint as protectedGetMetaWhereConstraint;
    }

    public function whereMeta()
    {
        return call_user_func_array([$this, 'protectedWhereMeta'], func_get_args());
    }

    public function getMetaWhereConstraint()
    {
        return call_user_func_array([$this, 'protectedGetMetaWhereConstraint'], func_get_args());
    }

    public function saveMeta()
    {
        return $this->protectedSaveMeta();
    }
}
