<?php

namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Sofa\Eloquence\Builder;
use Sofa\Eloquence\Metable;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\ArgumentBag;
use Sofa\Eloquence\Metable\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Eloquent\Relations\Relation;

class MetableTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        Relation::morphMap([
            'Metable' => MetableEloquentStub::class,
        ]);
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function meta_select()
    {
        $sql = 'select "meta_alias_1"."meta_value" as "size", "other"."id", "metables"."name" from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ?';

        $bindings = ['Metable', 'size'];

        $query = $this->getModel()->select('size', 'other.id', 'name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_pluck_with_both_meta_column_and_key_joins_twice()
    {
        $sql = 'select "meta_alias_1"."meta_value" as "size", "meta_alias_2"."meta_value" as "uuid" from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ? '.
                'left join "meta_attributes" as "meta_alias_2" on "meta_alias_2"."metable_id" = "metables"."id" '.
                'and "meta_alias_2"."metable_type" = ? and "meta_alias_2"."meta_key" = ?';

        $bindings = ['Metable', 'size', 'Metable', 'uuid'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->pluck('size', 'uuid');
    }

    /**
     * @test
     */
    public function meta_pluck_with_another_join()
    {
        $sql = 'select "meta_alias_1"."meta_value" as "size", "joined"."id" from "metables" '.
                'inner join "another_table" as "joined" on "joined"."metable_id" = "metables"."id" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ?';

        $bindings = ['Metable', 'size'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->join('another_table as joined', 'joined.metable_id', '=', 'metables.id')
                ->pluck('size', 'joined.id');
    }

    /**
     * @test
     */
    public function meta_pluck_prefixes_main_table_column()
    {
        $sql = 'select "meta_alias_1"."meta_value" as "size", "metables"."id" from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ?';

        $bindings = ['Metable', 'size'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->pluck('size', 'id');
    }

    /**
     * @test
     *
     * @dataProvider aggregateFunctions
     */
    public function meta_aggregates($function)
    {
        $sql = 'select '.$function.'("meta_alias_1"."meta_value") as aggregate from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ?';

        $bindings = ['Metable', 'size'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->{$function}('size');
    }

    public function aggregateFunctions()
    {
        return [
            ['max'], ['min'], ['avg'], ['count'], ['sum']
        ];
    }

    /**
     * @test
     */
    public function meta_pluck()
    {
        $sql = 'select "meta_alias_1"."meta_value" from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ? '.
                'limit 1';

        $bindings = ['Metable', 'color'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->value('color');
    }

    /**
     * @test
     */
    public function it_prefixes_selected_columns_when_joining_meta_attributes()
    {
        $sql = 'select "metables"."id", "metables"."name" as "full_name", "other"."field" from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? and "meta_alias_1"."meta_key" = ? '.
                'order by "meta_alias_1"."meta_value" asc';

        $query = $this->getModel()->select('id', 'name as full_name', 'other.field')->orderBy('color', 'asc');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orders()
    {
        $sql = 'select "metables".* from "metables" '.
                'left join "meta_attributes" as "meta_alias_1" on "meta_alias_1"."metable_id" = "metables"."id" '.
                'and "meta_alias_1"."metable_type" = ? '.
                'and "meta_alias_1"."meta_key" = ? '.
                'where "name" = ? order by "meta_alias_1"."meta_value" asc, "metables"."name" desc';

        $query = $this->getModel()->where('name', 'jarek')->oldest('color')->latest('metables.name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'color', 'jarek'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereExists()
    {
        $sql = 'select * from "metables" where exists (select 1 from "metables" where exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" > 10))';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('raw')->once()->andReturnUsing(function ($value) { return new Expression($value); });

        $query = $model->whereExists(function ($q) {$q->selectRaw(1)->where('size', '>', 10);});

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'size'], $query->getBindings());
    }

    /**
     * @test
     *
     * @dataProvider whereDateTypes
     */
    public function meta_whereDates($type, $placeholder)
    {
        $sql = 'select * from "metables" where "name" = ? and exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and strftime(\''.$placeholder.'\', "meta_value") = ?)';

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
            ['Date' , '%Y-%m-%d']
        ];
    }

    /**
     * @test
     */
    public function meta_orWhereNull()
    {
        $sql = 'select * from "metables" where "name" = ? or not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ?)';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotNull()
    {
        $sql = 'select * from "metables" where "name" = ? or exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ?)';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotNull()
    {
        $sql = 'select * from "metables" where "name" = ? and exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ?)';

        $query = $this->getModel()->where('name', 'jarek')->whereNotNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNull()
    {
        $sql = 'select * from "metables" where "name" = ? and not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ?)';

        $query = $this->getModel()->where('name', 'jarek')->whereNull('color');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotBetween()
    {
        $sql = 'select * from "metables" where "name" = ? and not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" >= ? and "meta_value" <= ?)';

        $query = $this->getModel()->where('name', 'jarek')->whereNotBetween('size', ['5','10']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', '5', '10'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereBetween_numeric()
    {
        $sql = 'select * from "metables" where "name" = ? and exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" >= 5 and "meta_value" <= 10.5)';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('raw')->twice()->andReturnUsing(function ($value) { return new Expression($value); });

        $query = $model->where('name', 'jarek')->whereBetween('size', [5,10.5]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereBetween_string()
    {
        $sql = 'select * from "metables" where "name" = ? and exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" >= ? and "meta_value" <= ?)';

        $query = $this->getModel()->where('name', 'jarek')->whereBetween('size', ['M','L']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'M', 'L'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereBetween()
    {
        $sql = 'select * from "metables" where "name" = ? or exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" >= ? and "meta_value" <= ?)';

        $query = $this->getModel()->where('name', 'jarek')->orWhereBetween('size', ['M','L']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'M', 'L'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotBetween()
    {
        $sql = 'select * from "metables" where "name" = ? or not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" >= ? and "meta_value" <= ?)';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotBetween('size', ['M','L']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'M', 'L'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereNotIn()
    {
        $sql = 'select * from "metables" where "name" = ? or not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" in (?, ?, ?))';

        $query = $this->getModel()->where('name', 'jarek')->orWhereNotIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereNotIn()
    {
        $sql = 'select * from "metables" where "name" = ? and not exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" in (?, ?, ?))';

        $query = $this->getModel()->where('name', 'jarek')->whereNotIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_whereIn()
    {
        $sql = 'select * from "metables" where "name" = ? and exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" in (?, ?, ?))';

        $query = $this->getModel()->where('name', 'jarek')->whereIn('size', ['L', 'M', 55]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 55], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhereIn()
    {
        $sql = 'select * from "metables" where "name" = ? or exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" in (?, ?, ?))';

        $query = $this->getModel()->where('name', 'jarek')->orWhereIn('size', ['L', 'M', 'S']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'size', 'L', 'M', 'S'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_orWhere()
    {
        $sql = 'select * from "metables" where "name" = ? or exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" = ?)';

        $query = $this->getModel()->where('name', 'jarek')->orWhere('color', 'red');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['jarek', 'Metable', 'color', 'red'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_where_numeric()
    {
        $sql = 'select * from "metables" where exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" > 5)';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('raw')->once()->andReturnUsing(function ($value) { return new Expression($value); });

        $query = $model->where('size', '>', 5);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Metable', 'size'], $query->getBindings());
    }

    /**
     * @test
     */
    public function meta_where()
    {
        $sql = 'select * from "metables" where exists (select * from "meta_attributes" '.
                'where "metables"."id" = "meta_attributes"."metable_id" and "meta_attributes"."metable_type" = ? '.
                'and "meta_key" = ? and "meta_value" = ?)';

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
        $size->shouldReceive('getValue')->once()->andReturn(null);
        $size->shouldReceive('delete')->once();

        $color = m::mock('StdClass');
        $color->shouldReceive('getValue')->once()->andReturn('red');

        $relation = m::mock('StdClass');
        $relation->shouldReceive('save')->once()->with($color);

        $model = m::mock('\Sofa\Eloquence\Tests\MetableEloquentStub')->makePartial();
        $model->shouldReceive('getMetaAttributes')->once()->andReturn([$color, $size]);
        $model->shouldReceive('metaAttributes')->once()->andReturn($relation);
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
        $bag->shouldReceive('set')->with('color', 'red', NULL)->once();

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
        $bag->shouldReceive('toArray')->once()->andReturn(['color' => 'red']);

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $this->assertEquals(['color' => 'red'], $model->getMetaAttributesArray());
    }

    /**
     * @test
     */
    public function it_sets_attribute_with_group_on_the_bag()
    {
        $bag = $this->getBag();
        $bag->shouldReceive('set')->with('color', 'red', 'group')->once();

        $model = $this->getMetableStub();
        $model->shouldReceive('getMetaAttributes')->andReturn($bag);

        $model->setMeta('color', 'red', 'group');
    }

    /**
     * @test
     */
    public function it_gets_attribute_with_group_on_the_bag()
    {
        $model = $this->getModel();

        $model->setMeta('test','1','group');
        $model->setMeta('test2','2',null);

        $this->assertEquals($model->getMetaByGroup('group')->toArray(), ['test' => new Attribute('test','1','group')]);

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

        $this->assertEquals($bag, $model->getMetaAttributes());
    }

    protected function getMetableStubLoadingRelation()
    {
        $bag = m::mock('StdClass');
        $collection = m::mock('StdClass');

        $collection->shouldReceive('all')->andReturn([]);

        $model = $this->getMetableStub();
        $model->exists = true;
        $model->relations = [];
        $model->shouldReceive('load')->with('metaAttributes')->once()->andReturn($model);
        $model->shouldReceive('getRelation')->with('metaAttributes')->once()->andReturn($collection);
        $model->shouldReceive('setRelation')->with('metaAttributes', m::any())->once();
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
        $connection = m::mock('Illuminate\Database\ConnectionInterface', ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model;
    }
}

class ParentModel extends Model {
    public function save(array $options = [])
    {
        return true;
    }
}

class MetableEloquentStub extends ParentModel {
    use Eloquence, Metable;

    protected $table = 'metables';

    public $aliases = [];

    protected function generateMetaAlias()
    {
        $len = count($this->aliases);

        return $this->aliases[$len] = 'meta_alias_'.($len+1);
    }
}

class MetableStub {
    use Eloquence, Metable;
}
