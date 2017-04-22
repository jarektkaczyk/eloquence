<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Relations\JoinerFactory;

use Mockery as m;

class JoinerTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->factory = new JoinerFactory;
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function it_joins_dot_nested_relations()
    {
        $sql = 'select * from "users" '.
               'inner join "profiles" on "users"."profile_id" = "profiles"."id" '.
               'inner join "companies" on "companies"."morphable_id" = "profiles"."id" and "companies"."morphable_type" = ?';

        $query = $this->getQuery();
        $joiner = $this->factory->make($query);

        $joiner->join('profile.company');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     *
     * @expectedException \LogicException
     */
    public function it_cant_join_morphTo()
    {
        $query = $this->getQuery();
        $joiner = $this->factory->make($query);

        $joiner->join('morphs');
    }

    /**
     * @test
     */
    public function it_joins_relations_on_query_builder()
    {
        $sql = 'select * from "users" '.
               'right join "company_user" on "company_user"."user_id" = "users"."id" '.
               'right join "companies" on "company_user"."company_id" = "companies"."id"';

        $eloquent = $this->getQuery();
        $model = $eloquent->getModel();
        $query = $eloquent->getQuery();
        $joiner = $this->factory->make($query, $model);

        $joiner->rightJoin('companies');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     */
    public function it_joins_relations_on_eloquent_builder()
    {
        $sql = 'select * from "users" '.
               'left join "companies" on "companies"."user_id" = "users"."id" '.
               'left join "profiles" on "profiles"."company_id" = "companies"."id"';

        $query = $this->getQuery();
        $joiner = $this->factory->make($query);

        $joiner->leftJoin('profiles');

        $this->assertEquals($sql, $query->toSql());
    }

    public function getQuery()
    {
        $model = new JoinerUserStub;
        $grammarClass = "Illuminate\Database\Query\Grammars\SQLiteGrammar";
        $processorClass = "Illuminate\Database\Query\Processors\SQLiteProcessor";
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $schema = m::mock('StdClass');
        $connection = m::mock('Illuminate\Database\ConnectionInterface', ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model->newQuery();
    }
}

class JoinerUserStub extends Model {

    protected $table = 'users';

    public function profile()
    {
        return $this->belongsTo('Sofa\Eloquence\Tests\JoinerProfileStub', 'profile_id');
    }

    public function companies()
    {
        return $this->belongsToMany('Sofa\Eloquence\Tests\JoinerCompanyStub', 'company_user', 'user_id', 'company_id');
    }

    public function profiles()
    {
        // due to lack of getters on HasManyThrough this relation works only with default fk!
        $related = 'Sofa\Eloquence\Tests\JoinerProfileStub';
        $through = 'Sofa\Eloquence\Tests\JoinerCompanyStub';
        return $this->hasManyThrough($related, $through, 'user_id', 'company_id');
    }

    public function posts()
    {
        return $this->hasMany('Sofa\Eloquence\Tests\JoinerPostStub', 'user_id');
    }

    public function morphed()
    {
        return $this->morphOne('Sofa\Eloquence\Tests\MorphOneStub');
    }

    public function morphs()
    {
        return $this->morphTo();
    }
}

class JoinerProfileStub extends Model {
    protected $table = 'profiles';

    public function company()
    {
        return $this->morphOne('Sofa\Eloquence\Tests\JoinerCompanyStub', 'morphable');
    }
}

class JoinerCompanyStub extends Model {
    protected $table = 'companies';
}

class JoinerPostStub extends Model {
    protected $table = 'posts';
}

class MorphOneStub extends Model {
    protected $table = 'morphs';
}
