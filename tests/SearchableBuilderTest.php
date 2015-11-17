<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Query\Builder as Query;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Eloquent\Model;

use Sofa\Eloquence\Searchable\ParserFactory;
use Sofa\Eloquence\Relations\JoinerFactory;
use Sofa\Eloquence\ArgumentBag;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Builder;

use Mockery as m;

class SearchableBuilderTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        Builder::setParserFactory(new ParserFactory);
        Builder::setJoinerFactory(new JoinerFactory);
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function single_character_wildcards()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`last_name` = ? then 150 else 0 end) '.
               'as relevance from `users` where (`users`.`last_name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 2.5 order by `relevance` desc';

        $bindings = ['jaros_aw', 'jaros_aw'];

        $model = $this->getModel();

        $query = $model->search(' jaros?aw ', ['last_name' => 10], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function it_moves_wheres_with_bindings_to_subquery_correctly()
    {
        $innerBindings = [
            'jarek', 'jarek',
            'inner_1', 'inner_2', 'inner_3', 'inner_4',
            'inner_5', 'inner_6', 'inner_7', 'inner_8',
        ];

        $outerBindings = [
            'outer_1', 'outer_2', 'outer_3', 'outer_4',
            'outer_5', 'outer_6', 'outer_7', 'outer_8',
        ];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->andReturn([]);

        $query = $model
                  ->search('jarek', 'first_name', false)
                  ->where('id', 'inner_1')
                  ->where('profiles.id', '<', 'outer_1')
                  ->whereBetween('id', ['inner_2','inner_3'])
                  ->whereRaw('users.first_name = ?', ['outer_2'])
                  ->whereRaw('users.first_name in (?, ?, ?)', ['outer_3', 'outer_4', 'outer_5'])
                  ->whereIn('id', ['inner_4', 'inner_5', 'inner_6', 'inner_7'])
                  ->whereNotNull('id')
                  ->whereExists(function ($q) {$q->whereIn('id', ['outer_6', 'outer_7']);})
                  ->whereRaw('first_name = ?', ['outer_8'])
                  ->whereDate('id', '=', ['inner_8'])
                  ->where('last_name', new Expression('tkaczyk'));

        $query->get();

        $this->assertEquals($innerBindings, $query->getQuery()->getRawBindings()['select']);
        $this->assertEquals($outerBindings, $query->getQuery()->getRawBindings()['where']);
    }

    /**
     * @test
     */
    public function it_moves_wheres_to_subquery_for_performance_if_possible()
    {
        $query = 'select * from (select `users`.*, '.
                 'max(case when `users`.`first_name` = ? then 15 else 0 end) as relevance from `users` '.
                 'where (`users`.`first_name` like ?) and `users`.`last_name` = ? and `users`.`id` > ? group by `users`.`primary_key`) '.
                 'as `users` where (select count(*) from `profiles` where `users`.`profile_id` = `profiles`.`id` '.
                 'and `id` = ?) >= 1 and `relevance` >= 0.25 order by `relevance` desc';

        $bindings = ['jarek', 'jarek', 'tkaczyk', 10, 5];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()
        ->with($query, $bindings, m::any())
        ->andReturn([]);

        $model->whereHas('profile', function ($q) { $q->where('id', 5); }) // where with subquery - not moved
              ->where('last_name', 'tkaczyk') // where on this table's field - moved
              ->search('jarek', ['first_name'], false)
              ->where('id', '>', 10) // where on this table's field - moved
              ->get();
    }

    /**
     * @test
     */
    public function table_prefixed_correctly()
    {
        $sql = 'select * from (select `PREFIX_users`.*, max(case when `PREFIX_users`.`first_name` = ? then 15 else 0 end) '.
               'as relevance from `PREFIX_users` where (`PREFIX_users`.`first_name` like ?) '.
               'group by `PREFIX_users`.`primary_key`) as `PREFIX_users` where `relevance` >= 0.25 order by `relevance` desc';

        $bindings = ['jarek', 'jarek'];

        $query = $this->getModel()->newQuery();
        $query->getQuery()->getGrammar()->setTablePrefix('PREFIX_');
        $query->search('jarek', ['first_name'], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function quoted_string_treated_as_one_word()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`first_name` = ? then 15 else 0 end '.
               '+ case when `users`.`first_name` like ? then 5 else 0 end) as relevance from `users` '.
               'where (`users`.`first_name` like ?) group by `users`.`primary_key`) '.
               'as `users` where `relevance` >= 0.25 order by `relevance` desc';

        $bindings = ['jarek tkaczyk', 'jarek tkaczyk%', 'jarek tkaczyk%'];

        $query = $this->getModel()->search('"jarek tkaczyk*"', ['first_name'], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function additional_order_clauses()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`first_name` = ? then 15 else 0 end '.
               '+ case when `profiles`.`name` = ? then 15 else 0 end) as relevance '.
               'from `users` left join `profiles` on `users`.`profile_id` = `profiles`.`id` '.
               'where (`users`.`first_name` like ? or `profiles`.`name` like ?) group by `users`.`primary_key`) '.
               'as `users` where `relevance` >= 0.5 order by `relevance` desc, `first_name` asc';

        $bindings = ['jarek', 'jarek', 'jarek', 'jarek'];

        $query = $this->getModel()->orderBy('first_name')->search('jarek', ['first_name', 'profile.name'], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function length_aware_pagination()
    {
        $query = 'select count(*) as aggregate from (select `users`.*, max(case when `users`.`last_name` = ? then 150 else 0 end '.
                 '+ case when `users`.`last_name` like ? then 50 else 0 end '.
                 '+ case when `users`.`last_name` like ? then 10 else 0 end) '.
                 'as relevance from `users` where (`users`.`last_name` like ?) '.
                 'group by `users`.`primary_key`) as `users` where `relevance` >= 2.5';

        $bindings = ['jarek', 'jarek%', '%jarek%', '%jarek%'];

        $model = $this->getModel();
        $model->getConnection()->shouldReceive('select')->once()->with($query, $bindings, m::any())->andReturn([]);

        $model->search(' jarek ', ['last_name' => 10])->getCountForPagination();
    }

    /**
     * @test
     */
    public function case_insensitive_operator_in_postgres()
    {
        $sql = 'select * from (select "users".*, max(case when "users"."last_name" = ? then 150 else 0 end '.
               '+ case when "users"."last_name" ilike ? then 50 else 0 end '.
               '+ case when "users"."last_name" ilike ? then 10 else 0 end) '.
               'as relevance from "users" where ("users"."last_name" ilike ?) '.
               'group by "users"."primary_key") as "users" where "relevance" >= 2.5 order by "relevance" desc';

        $bindings = ['jarek', 'jarek%', '%jarek%', '%jarek%'];

        $model = $this->getModel('Postgres');

        $query = $model->search(' jarek ', ['last_name' => 10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function it_fails_silently_if_no_words_or_columns_were_provided()
    {
        $sql = 'select * from `users`';

        $query = $this->getModel()->search('  ');

        $this->assertEquals($sql, $query->toSql());
    }

    /**
     * @test
     */
    public function wildcard_search_by_default()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`last_name` = ? then 150 else 0 end '.
               '+ case when `users`.`last_name` like ? then 50 else 0 end '.
               '+ case when `users`.`last_name` like ? then 10 else 0 end) '.
               'as relevance from `users` where (`users`.`last_name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 2.5 order by `relevance` desc';

        $bindings = ['jarek', 'jarek%', '%jarek%', '%jarek%'];

        $model = $this->getModel();

        $query = $model->search(' jarek ', ['last_name' => 10]);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function wildcard_search()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`last_name` = ? or `users`.`last_name` = ? or `users`.`last_name` = ? then 150 else 0 end '.
               '+ case when `users`.`last_name` like ? or `users`.`last_name` like ? then 50 else 0 end '.
               '+ case when `users`.`last_name` like ? then 10 else 0 end '.
               '+ case when `companies`.`name` = ? or `companies`.`name` = ? or `companies`.`name` = ? then 75 else 0 end '.
               '+ case when `companies`.`name` like ? or `companies`.`name` like ? then 25 else 0 end '.
               '+ case when `companies`.`name` like ? then 5 else 0 end) '.
               'as relevance from `users` left join `company_user` on `company_user`.`user_id` = `users`.`primary_key` '.
               'left join `companies` on `company_user`.`company_id` = `companies`.`id` '.
               'where (`users`.`last_name` like ? or `users`.`last_name` like ? or `users`.`last_name` like ? '.
               'or `companies`.`name` like ? or `companies`.`name` like ? or `companies`.`name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 3.75 order by `relevance` desc';

        $bindings = [
            // select
            'jarek', 'tkaczyk', 'sofa', 'jarek%', 'tkaczyk%', '%jarek%',
            'jarek', 'tkaczyk', 'sofa', 'jarek%', 'tkaczyk%', '%jarek%',
            // where
            '%jarek%', 'tkaczyk%', 'sofa', '%jarek%', 'tkaczyk%', 'sofa',
        ];

        $query = $this->getModel()->search('*jarek* tkaczyk* sofa', ['last_name' => 10, 'companies.name' => 5], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function left_matching_search()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`first_name` = ? then 15 else 0 end '.
               '+ case when `users`.`first_name` like ? then 5 else 0 end) '.
               'as relevance from `users` where (`users`.`first_name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 0.25 order by `relevance` desc';

        $bindings = ['jarek', 'jarek%', 'jarek%'];

        $query = $this->getModel()->search('jarek*', ['first_name'], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function explicit_search_on_joined_table()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`first_name` = ? or `users`.`first_name` = ? then 15 else 0 end '.
               '+ case when `users`.`last_name` = ? or `users`.`last_name` = ? then 75 else 0 end '.
               '+ case when `users`.`email` = ? or `users`.`email` = ? then 150 else 0 end '.
               '+ case when `profiles`.`name` = ? or `profiles`.`name` = ? then 30 else 0 end) '.
               'as relevance from `users` left join `profiles` on `users`.`profile_id` = `profiles`.`id` '.
               'where (`users`.`first_name` like ? or `users`.`first_name` like ? or `users`.`last_name` like ? or `users`.`last_name` like ? '.
               'or `users`.`email` like ? or `users`.`email` like ? or `profiles`.`name` like ? or `profiles`.`name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 4.5 order by `relevance` desc';

        $bindings = [
            'jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk',
            'jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk',
        ];

        $query = $this->getModel()->search('jarek tkaczyk', false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    /**
     * @test
     */
    public function explicit_search_on_single_table_with_provided_columns()
    {
        $sql = 'select * from (select `users`.*, max(case when `users`.`first_name` = ? or `users`.`first_name` = ? then 15 else 0 end '.
               '+ case when `users`.`last_name` = ? or `users`.`last_name` = ? then 30 else 0 end) as relevance from `users` '.
               'where (`users`.`first_name` like ? or `users`.`first_name` like ? or `users`.`last_name` like ? or `users`.`last_name` like ?) '.
               'group by `users`.`primary_key`) as `users` where `relevance` >= 0.75 order by `relevance` desc';

        $bindings = ['jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk', 'jarek', 'tkaczyk'];

        $query = $this->getModel()->search('jarek tkaczyk', ['first_name', 'last_name' => 2], false);

        $this->assertEquals($sql, $query->toSql());
        $this->assertEquals($bindings, $query->getBindings());
    }

    public function getModel($driver = 'MySql')
    {
        $model = new SearchableBuilderUserStub;
        $grammarClass = "Illuminate\Database\Query\Grammars\\{$driver}Grammar";
        $processorClass = "Illuminate\Database\Query\Processors\\{$driver}Processor";
        $grammar = new $grammarClass;
        $processor = new $processorClass;
        $schema = m::mock('StdClass');
        $schema->shouldReceive('getColumnListing')->andReturn(['id', 'first_name', 'last_name']);
        $connection = m::mock('Illuminate\Database\ConnectionInterface', ['getQueryGrammar' => $grammar, 'getPostProcessor' => $processor]);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $resolver = m::mock('Illuminate\Database\ConnectionResolverInterface', ['connection' => $connection]);
        $class = get_class($model);
        $class::setConnectionResolver($resolver);
        return $model;
    }
}

class SearchableBuilderUserStub extends Model {
    use Eloquence;

    protected $table = 'users';
    protected $primaryKey = 'primary_key';
    protected $searchableColumns = [
        'first_name',
        'last_name'    => 5,
        'email'        => 10,
        'profile.name' => 2,
    ];

    public function profile()
    {
        return $this->belongsTo('Sofa\Eloquence\Tests\SearchableProfileStub', 'profile_id');
    }

    public function companies()
    {
        return $this->belongsToMany('Sofa\Eloquence\Tests\SearchableCompanyStub', 'company_user', 'user_id', 'company_id');
    }
}

class SearchableProfileStub extends Model {
    protected $table = 'profiles';
}

class SearchableCompanyStub extends Model {
    protected $table = 'companies';
}
