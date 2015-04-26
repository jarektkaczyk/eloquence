<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Mappable;

class MappableTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->model = new MappableStub;
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function alias_where()
    {
        $sql = 'select * from "users" where "email" = ? and "ign" = ?';

        $query = $this->getModel()->where('email', 'some@email')->where('nick', 'FooBar');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 'FooBar'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereBetween()
    {
        $sql = 'select * from "users" where "email" = ? and (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "age" between ? and ?) >= 1';

        $query = $this->getModel()->where('email', 'some@email')->whereBetween('age', [20, 30]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 20, 30], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotBetween()
    {
        $sql = 'select * from "users" where "email" = ? and (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "age" between ? and ?) < 1';

        $query = $this->getModel()->where('email', 'some@email')->whereNotBetween('age', [20, 30]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 20, 30], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotIn()
    {
        $sql = 'select * from "users" where "email" = ? and (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" in (?, ?)) < 1';

        $query = $this->getModel()->where('email', 'some@email')->whereNotIn('first_name', ['Jarek', 'Marek']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 'Jarek', 'Marek'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotNull()
    {
        $sql = 'select * from "users" where "email" = ? and (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" is not null) >= 1';

        $query = $this->getModel()->where('email', 'some@email')->whereNotNull('first_name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNull()
    {
        $sql = 'select * from "users" where "email" = ? and (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" is not null) < 1';

        $query = $this->getModel()->where('email', 'some@email')->whereNull('first_name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_orWhere()
    {
        $sql = 'select * from "users" where "email" = ? or (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" = ?) >= 1';

        $query = $this->getModel()->where('email', 'some@email')->orWhere('first_name', 'Jarek');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 'Jarek'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_where()
    {
        $sql = 'select * from "users" where (select count(*) from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" = ?) >= 1';

        $query = $this->getModel()->where('first_name', 'Jarek');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['Jarek'], $query->getBindings());
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::saveMapped
     */
    public function it_saves_mapped_related_models()
    {
        $model = new MappableStub;

        $model->bar = m::mock('StdClass');
        $model->bar->shouldReceive('save')->once()->andReturn(true);

        $model->setMappedAttribute('foo', 'new_value');
        $model->saveMapped();
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::forget
     */
    public function it_unsets_mapped_attribute()
    {
        $model = new MappableStub;

        $this->assertTrue(property_exists($model->bar, 'baz'));
        $model->forget('foo');
        $this->assertFalse(property_exists($model->bar, 'baz'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::hasMapping
     * @covers \Sofa\Eloquence\Mappable::hasExplicitMapping
     * @covers \Sofa\Eloquence\Mappable::getMaps
     */
    public function it_finds_mapped_attribute_using_explicit_dot_notation()
    {
        $this->assertTrue($this->model->hasMapping('foo'));
        $this->assertFalse($this->model->hasMapping('bar'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::hasMapping
     * @covers \Sofa\Eloquence\Mappable::hasImplicitMapping
     */
    public function it_finds_mapped_attribute_using_implicit_array_notation()
    {
        $this->assertTrue($this->model->hasMapping('name'));
        $this->assertFalse($this->model->hasMapping('bar'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::getExplicitMapping
     */
    public function it_fetches_explicit_mapping()
    {
        $this->assertEquals('bar.baz', $this->model->getExplicitMapping('foo'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::getImplicitMapping
     */
    public function it_fetches_implicit_mapping()
    {
        $this->assertEquals('related.name', $this->model->getImplicitMapping('name'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::mapAttribute
     * @covers \Sofa\Eloquence\Mappable::getMappingForAttribute
     */
    public function it_gets_mapped_value()
    {
        // close relations
        $this->assertEquals('name_value', $this->model->mapAttribute('name'));
        $this->assertEquals('baz_value', $this->model->mapAttribute('foo'));

        // far relations
        $this->assertEquals('far_value', $this->model->mapAttribute('far_field'));
        $this->assertEquals('bam_value', $this->model->mapAttribute('bam'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::getTarget
     * @covers \Sofa\Eloquence\Mappable::getMappingForAttribute
     */
    public function it_fails_silently_when_target_or_mapping_was_not_found()
    {
        $this->model->bar = null;

        // no target
        $this->model->setMappedAttribute('foo', 'value');
        $this->assertNull($this->model->mapAttribute('foo'));

        // no mapping
        $this->model->getMappingForAttribute('wrong');
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::setMappedAttribute
     * @covers \Sofa\Eloquence\Mappable::getTarget
     */
    public function it_sets_mapped_value()
    {
        // close relation
        $this->model->setMappedAttribute('email', 'new_email_value');
        $this->assertEquals('new_email_value', $this->model->mapAttribute('email'));

        // far relation
        $this->model->setMappedAttribute('bam', 'new_bam_value');
        $this->assertEquals('new_bam_value', $this->model->mapAttribute('bam'));
    }

    public function getModel()
    {
        $model = new MappableEloquentStub;
        $grammarClass = 'Illuminate\Database\Query\Grammars\SQLiteGrammar';
        $processorClass = 'Illuminate\Database\Query\Processors\SQLiteProcessor';
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $connection = m::mock('Illuminate\Database\ConnectionInterface', ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model;
    }
}

class MappableStub {

    use Eloquence, Mappable {
        hasExplicitMapping as protectedHasExplicitMapping;
        hasImplicitMapping as protectedHasImplicitMapping;
        getExplicitMapping as protectedGetExplicitMapping;
        getImplicitMapping as protectedGetImplicitMapping;
        setMappedAttribute as protectedSetMappedAttribute;
        mapAttribute       as protectedMapAttribute;
        mappedQuery        as protectedMappedQuery;
        forget             as protectedForget;
        saveMapped         as protectedSaveMapped;
    }

    protected $maps = [
        // local alias
        'alias'       => 'original',        // $this->original

        // explicit relation mappings
        'foo'         => 'bar.baz',         // $this->bar->baz
        'bam'         => 'bar.bad.bam',     // $this->bar->bad->bam

        // implicit relation mappings
        'related'     => ['name', 'email'], // $this->related->name
        'related.far' => ['far_field'],     // $this->related->far->far_field
    ];

    public function __construct()
    {
        $this->bar     = $this->getRelatedStub([
            'baz' => 'baz_value',
            'bad' => $this->getRelatedStub([
                'bam' => 'bam_value'
            ])
        ]);

        $this->related = $this->getRelatedStub([
            'name'  => 'name_value',
            'email' => 'email_value',
            'far'   => $this->getRelatedStub([
                'far_field' => 'far_value'
            ])
        ]);
    }

    public function setMappedAttribute($key, $value)
    {
        return $this->protectedSetMappedAttribute($key, $value);
    }

    public function mappedQuery()
    {
        return call_user_func_array([$this, 'protectedMappedQuery'], func_get_args());
    }

    public function mapAttribute($key)
    {
        return $this->protectedMapAttribute($key);
    }

    public function getImplicitMapping($key)
    {
        return $this->protectedGetImplicitMapping($key);
    }

    public function getExplicitMapping($key)
    {
        return $this->protectedGetExplicitMapping($key);
    }

    protected function getRelatedStub($attributes)
    {
        return (object) $attributes;
    }

    public function forget($key)
    {
        return $this->protectedForget($key);
    }

    public function saveMapped()
    {
        return $this->protectedSaveMapped();
    }
}

class MappableEloquentStub extends Model {
    use Eloquence, Mappable;

    protected $table = 'users';

    protected $maps = [
        'first_name' => 'profile.first_name',
        'profile'    => ['last_name', 'age'],
        'nick'       => 'ign',
    ];

    public function profile()
    {
        return $this->belongsTo('Sofa\Eloquence\Tests\MappableRelatedStub', 'profile_id');
    }
}

class MappableRelatedStub extends Model {
    protected $table = 'profiles';
}
