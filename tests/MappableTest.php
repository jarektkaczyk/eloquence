<?php

namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Sofa\Eloquence\Mappable;
use Sofa\Eloquence\Eloquence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class MappableTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        Relation::morphMap([
            'UserMorph' => MappableEloquentStub::class,
        ]);
        $this->model = new MappableStub;
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function mapped_select()
    {
        $sql = 'select "profiles"."last_name", "users"."id", "users"."ign" from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id"';

        $query = $this->getModel()->select('last_name', 'id', 'nick');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     *
     * @dataProvider aggregateFunctions
     */
    public function mapped_aggregates($function)
    {
        $sql = 'select '.$function.'("images"."path") as aggregate from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id" '.
                'left join "images" on "images"."profile_id" = "profiles"."id"';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, [], m::any())->andReturn([]);

        $model->{$function}('avatar');
    }

    public function aggregateFunctions()
    {
        return [
            ['max'], ['min'], ['avg'], ['count'], ['sum']
        ];
    }

    /**
     * @test
     *
     * @expectedException \LogicException
     */
    public function mapped_join_rejects_morphTo_relation_for_joins()
    {
        // It's a MorphTo relation that is supported for mapping,
        // but due to the way it works it cannot be used for
        // query hooks, because it's impossible to join.
        $this->getModel()->value('role');
    }

    /**
     * @test
     */
    public function mapped_join_polymorphic_relation()
    {
        $sql = 'select "companies"."name" from "users" '.
                'left join "companies" on "companies"."brandable_id" = "users"."id" '.
                'and "companies"."brandable_type" = ? '.
                'limit 1';

        $bindings = ['UserMorph'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, $bindings, m::any())->andReturn([]);

        $model->value('brand');
    }

    /**
     * @test
     */
    public function mapped_pluck()
    {
        $sql = 'select "images"."path" from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id" '.
                'left join "images" on "images"."profile_id" = "profiles"."id" '.
                'limit 1';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, [], m::any())->andReturn([]);

        $model->value('avatar');
    }

    /**
     * @test
     */
    public function mapped_pluck_leaves_prefixed_keys_intact()
    {
        $sql = 'select "profiles"."last_name", "other_table"."id" from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id"';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, [], m::any())->andReturn([]);

        $model->pluck('last_name', 'other_table.id');
    }

    /**
     * @test
     */
    public function mapped_pluck_prefixes_main_table_column()
    {
        $sql = 'select "profiles"."last_name", "users"."id" from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id"';

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($sql, [], m::any())->andReturn([]);

        $model->pluck('last_name', 'id');
    }

    /**
     * @test
     */
    public function mapped_orderBy_nested()
    {
        $sql = 'select "users".* from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id" '.
                'left join "images" on "images"."profile_id" = "profiles"."id" '.
                'order by "images"."path" asc, "profiles"."first_name" desc';

        $query = $this->getModel()->oldest('avatar')->latest('first_name', 'desc');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     */
    public function mapped_orderBy_has_one()
    {
        $sql = 'select "users".* from "users" '.
                'left join "accounts" on "accounts"."user_id" = "users"."id" '.
                'order by "accounts"."photo" asc, "accounts"."address" desc';

        $query = $this->getModel()->oldest('photo')->latest('address', 'desc');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     */
    public function mapped_orderBy_belongs_to()
    {
        $sql = 'select "users".* from "users" '.
                'left join "profiles" on "users"."profile_id" = "profiles"."id" '.
                'order by "ign" asc, "profiles"."first_name" desc';

        $query = $this->getModel()->oldest('nick')->latest('first_name', 'desc');

        $this->assertEquals($sql, $query->toSql());
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
        $sql = 'select * from "users" where "email" = ? and exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "age" between ? and ?)';

        $query = $this->getModel()->where('email', 'some@email')->whereBetween('age', [20, 30]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 20, 30], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotBetween()
    {
        $sql = 'select * from "users" where "email" = ? and not exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "age" between ? and ?)';

        $query = $this->getModel()->where('email', 'some@email')->whereNotBetween('age', [20, 30]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 20, 30], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotIn()
    {
        $sql = 'select * from "users" where "email" = ? and not exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" in (?, ?))';

        $query = $this->getModel()->where('email', 'some@email')->whereNotIn('first_name', ['Jarek', 'Marek']);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 'Jarek', 'Marek'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNotNull()
    {
        $sql = 'select * from "users" where "email" = ? and exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" is not null)';

        $query = $this->getModel()->where('email', 'some@email')->whereNotNull('first_name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_whereNull()
    {
        $sql = 'select * from "users" where "email" = ? and not exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" is not null)';

        $query = $this->getModel()->where('email', 'some@email')->whereNull('first_name');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_orWhere()
    {
        $sql = 'select * from "users" where "email" = ? or exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" = ?)';

        $query = $this->getModel()->where('email', 'some@email')->orWhere('first_name', 'Jarek');

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals(['some@email', 'Jarek'], $query->getBindings());
    }

    /**
     * @test
     */
    public function mapped_where()
    {
        $sql = 'select * from "users" where exists (select * from "profiles" '.
                'where "users"."profile_id" = "profiles"."id" and "first_name" = ?)';

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
     */
    public function it_finds_mapped_attribute_using_implicit_array_notation()
    {
        $this->assertTrue($this->model->hasMapping('name'));
        $this->assertFalse($this->model->hasMapping('bar'));
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

    /**
     * @test
     */
    public function it_inherits_base_model_properly()
    {
        $q = $this->getModel(new UserStub)->select(['id', 'name'])->where('id', '>', 0);
        $expectedSql = 'select "user_stubs"."usr_id", "user_stubs"."usr_name" from "user_stubs" where "usr_id" > ?';
        $this->assertEquals($expectedSql, $q->toSql());

        $q = $this->getModel(new BookStub)->select(['id', 'name', 'pages'])->where('id', '>', 0);
        $expectedSql = 'select "book_stubs"."bk_id", "book_stubs"."bk_name", "book_stubs"."bk_pages" from "book_stubs" where "bk_id" > ?';
        $this->assertEquals($expectedSql, $q->toSql());
    }

    public function getModel($model = null)
    {
        $model = $model ?: new MappableEloquentStub;
        $grammarClass = 'Illuminate\Database\Query\Grammars\SQLiteGrammar';
        $processorClass = 'Illuminate\Database\Query\Processors\SQLiteProcessor';
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $schema = m::mock('StdClass');
        $schema->shouldReceive('getColumnListing')->andReturn(['id', 'email', 'ign']);
        $connection = m::mock('Illuminate\Database\ConnectionInterface', ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model;
    }
}

class MappableStub {

    use Eloquence, Mappable {
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
        'photo'      => 'account.photo',
        'address'    => 'account.address',
        'avatar'     => 'profile.image.path',
        'brand'      => 'company.name',
        'role'       => 'userable.name',
    ];

    public function profile()
    {
        return $this->belongsTo('Sofa\Eloquence\Tests\MappableRelatedStub', 'profile_id');
    }

    public function account()
    {
        return $this->hasOne('Sofa\Eloquence\Tests\MappableRelatedHasOneStub', 'user_id');
    }

    public function company()
    {
        return $this->morphOne('Sofa\Eloquence\Tests\MappablePolymorphicStub', 'brandable');
    }

    public function userable()
    {
        return $this->morphTo();
    }
}

class MappableRelatedStub extends Model {
    protected $table = 'profiles';

    public function image()
    {
        return $this->hasOne('Sofa\Eloquence\Tests\MappableFarRelatedStub', 'profile_id');
    }
}

class MappableRelatedHasOneStub extends Model {
    protected $table = 'accounts';
}

class MappableFarRelatedStub extends Model {
    protected $table = 'images';
}

class MappablePolymorphicStub extends Model {
    protected $table = 'companies';
}



class BaseModelStub extends Model {
    use Eloquence, Mappable;
}

class UserStub extends BaseModelStub {
    protected $maps = [
        'id' => 'usr_id',
        'name' => 'usr_name',
    ];
}

class BookStub extends BaseModelStub {
    protected $maps = [
        'id' => 'bk_id',
        'name' => 'bk_name',
        'pages' => 'bk_pages',
    ];
}
