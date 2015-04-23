<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Grammars\Grammar;
use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Metable;
use Sofa\Eloquence\ArgumentBag;
use Sofa\Eloquence\Contracts\Metable as MetableContract;

class MetableTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function meta_max()
    {
        $sql = 'select max("meta_attributes"."value") as aggregate from "metables" '.
                'left join "meta_attributes" on "meta_attributes"."metable_id" = "metables"."id" '.
                'and "meta_attributes"."metable_type" = ? and "meta_attributes"."key" = ?';

        $bindings = ['Metable', 'size'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->max('size');
    }

    /**
     * @test
     */
    public function meta_pluck()
    {
        $sql = 'select "meta_attributes"."value" from "metables" '.
                'left join "meta_attributes" on "meta_attributes"."metable_id" = "metables"."id" '.
                'and "meta_attributes"."metable_type" = ? and "meta_attributes"."key" = ? '.
                'limit 1';

        $bindings = ['Metable', 'color'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->pluck('color');
    }

    /**
     * @test
     */
    public function it_prefixes_selected_columns_when_joining_meta_attributes()
    {
        $sql = 'select "metables"."id", "metables"."name" as "full_name", "other"."field" from "metables" '.
                'left join "meta_attributes" on "meta_attributes"."metable_id" = "metables"."id" '.
                'and "meta_attributes"."metable_type" = ? and "meta_attributes"."key" = ? '.
                'order by "meta_attributes"."value" asc';

        $query = $this->getModel()->select('id', 'name as full_name', 'other.field')->orderBy('color', 'asc');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orderBy()
    {
        $sql = 'select "metables".* from "metables" '.
                'left join "meta_attributes" on "meta_attributes"."metable_id" = "metables"."id" '.
                'and "meta_attributes"."metable_type" = ? '.
                'and "meta_attributes"."key" = ? '.
                'where "name" = ? order by "meta_attributes"."value" asc, "metables"."name" desc';

        $query = $this->getModel()->where('name', 'jarek')->orderBy('color', 'asc')->orderBy('metables.name', 'desc');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'color', 'jarek'], $query->getBindings());
    }

    /**
     * @test
     *
     * @dataProvider whereDateTypes
     */
    public function meta_whereDates($type, $placeholder)
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and strftime(\''.$placeholder.'\', "value") = ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->{"where{$type}"}('published_at', '=', 'date_value');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'published_at', 'date_value'], $query->getBindings());
    }

    public function whereDateTypes()
    {
        return [
            ['Year' , '%Y'],
            ['Month', '%m'],
            ['Day'  , '%d'],
            ['Date' , 'date']
        ];
    }

    /**
     * @test
     */
    public function meta_orWhereNull()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ?) < 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotNull()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotNull()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->whereNotNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNull()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ?) < 1';

        $query = $this->getModel()->where('name', 'jarek')->whereNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotBetween()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" between ? and ?) < 1';

        $query = $this->getModel()->where('name', 'jarek')->whereNotBetween('size', [5,10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 5, 10], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereBetween()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" between ? and ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->whereBetween('size', [5,10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 5, 10], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereBetween()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" between ? and ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereBetween('size', [5,10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 5, 10], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotBetween()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" between ? and ?) < 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotBetween('size', [5,10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 5, 10], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotIn()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" in (?, ?, ?)) < 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotIn()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" in (?, ?, ?)) < 1';

        $query = $this->getModel()->where('name', 'jarek')->whereNotIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereIn()
    {
        $sql = 'select * from "metables" where "name" = ? and (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" in (?, ?, ?)) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->whereIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereIn()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" in (?, ?, ?)) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhereIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhere()
    {
        $sql = 'select * from "metables" where "name" = ? or (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" = ?) >= 1';

        $query = $this->getModel()->where('name', 'jarek')->orWhere('color', 'red');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color', 'red'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_where()
    {
        $sql = 'select * from "metables" where (select count(*) from "meta_attributes" '.
                'where "meta_attributes"."metable_id" = "metables"."id" and "meta_attributes"."metable_type" = ? '.
                'and "key" = ? and "value" = ?) >= 1';

        $query = $this->getModel()->where('color', 'red');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'color', 'red'], $query->getBindings());
    }

    /**
     * @test
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

        $model = m::mock('\Sofa\Eloquence\Tests\MetableEloquentStub')->makePartial();
        $model->shouldReceive('getMetaAttributes')->andReturn([$color, $size]);
        $model->shouldReceive('metaAttributes')->andReturn($relation);
        $model->exists = true;

        $model->save(['timestamps' => false, 'touch' => false]);
    }

    /**
     * @test
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
     */
    public function it_checks_not_null_attributes()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('lists')->with('value', 'key')->once()->andReturn(['color' => 'red', 'size' => null]);

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $this->assertTrue($model->hasMeta('color'));
        $this->assertFalse($model->hasMeta('size'));
    }

    /**
     * @test
     */
    public function it_gets_attribute_from_the_bag()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('getValue')->with('color')->once();

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $model->getMeta('color');
    }

    /**
     * @test
     */
    public function it_sets_attribute_on_the_bag()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('set')->with('color', 'red')->once();

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $model->setMeta('color', 'red');
    }

    /**
     * @test
     */
    public function it_gets_meta_attributes_as_key_value_array()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('lists')->with('value', 'key')->once()->andReturn(['color' => 'red']);

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $this->assertEquals(['color' => 'red'], $model->getMetaAttributesArray());
    }

    /**
     * @test
     */
    public function it_uses_attribute_bag_for_relation_dynamic_property()
    {
        list($model, $bag) = $this->getMetableStubLoadingRelation();

        $this->assertSame($bag, $model->getMetaAttributesAttribute());
    }

    /**
     * @test
     */
    public function it_loads_meta_attributes_relation_if_not_loaded()
    {
        list($model, $bag) = $this->getMetableStubLoadingRelation();

        $this->assertSame($bag, $model->getMetaAttributes());
    }

    protected function getMetableStubLoadingRelation()
    {
        $bag = m::mock('StdClass');
        $relation = m::mock('StdClass');
        $attribute = m::mock('StdClass');
        $collection = m::mock('StdClass');

        $relation->shouldReceive('getRelated')->once()->andReturn($attribute);
        $attribute->shouldReceive('newBag')->once()->andReturn($bag);
        $collection->shouldReceive('all')->andReturn([]);

        $model = $this->getMetableStub();
        $model->relations = [];
        $model->shouldReceive('load')->with('metaAttributes')->once()->andReturn($model);
        $model->shouldReceive('getRelation')->with('metaAttributes')->once()->andReturn($collection);
        $model->shouldReceive('metaAttributes')->once()->andReturn($relation);
        $model->shouldReceive('setRelation')->with('metaAttributes', $bag)->once();
        $model->shouldReceive('getRelation')->with('metaAttributes')->once()->andReturn($bag);

        return [$model, $bag];
    }

    /**
     * @test
     */
    public function it_gets_empty_array_or_provided_allowed_meta_attributes()
    {
        $model = new MetableStub;
        $this->assertEquals([], $model->getAllowedMeta());

        $model->setAllowedMeta(['foo', 'bar']);
        $this->assertEquals(['foo', 'bar'], $model->getAllowedMeta());
    }

    public function getMetableStub()
    {
        return m::mock('\Sofa\Eloquence\Tests\MetableStub')->makePartial();
    }

    public function getBag()
    {
        return m::mock('\Sofa\Eloquence\Metable\AttributeBag');
    }

    public function getModel()
    {
        $model = new MetableEloquentStub;
        $grammarClass = 'Illuminate\Database\Query\Grammars\SQLiteGrammar';
        $processorClass = 'Illuminate\Database\Query\Processors\SQLiteProcessor';
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $schema = m::mock('StdClass');
        $schema->shouldReceive('getColumnListing')->andReturn(['id', 'name']);
        $connection = m::mock('Illuminate\Database\ConnectionInterface', array('getQueryGrammar' => $grammar, 'getPostProcessor' => $processor));
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', array('connection' => $connection));
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model;
    }
}

class MetableEloquentStub extends Model implements MetableContract {
    use Eloquence, Metable;

    protected $table = 'metables';
    protected $morphClass = 'Metable';
}

class MetableStub implements MetableContract {
    use Eloquence, Metable;
}
