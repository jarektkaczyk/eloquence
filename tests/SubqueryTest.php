<?php

namespace Sofa\Eloquence\Tests;

use Mockery;
use Sofa\Eloquence\Subquery;

class SubqueryTest extends TestCase
{
    /** @test */
    public function it_forwards_calls_to_the_builder()
    {
        $builder = Mockery::mock('\Illuminate\Database\Query\Builder');
        $builder->shouldReceive('where')->once()->with('foo', 'bar')->andReturn($builder);

        $sub = new Subquery($builder);
        $sub->from = 'table';
        $sub->where('foo', 'bar');

        $this->assertFalse(property_exists($sub, 'from'));
        $this->assertEquals('table', $sub->getQuery()->from);
        $this->assertEquals('table', $sub->from);
    }

    /** @test */
    public function it_prints_as_aliased_query_in_parentheses()
    {
        $grammar = Mockery::mock('StdClass');
        $grammar->shouldReceive('wrapTable')->with('table_alias')->once()->andReturn('"table_alias"');
        $builder = Mockery::mock('\Illuminate\Database\Query\Builder');
        $builder->shouldReceive('getGrammar')->once()->andReturn($grammar);
        $sub = new Subquery($builder);
        $sub->getQuery()->shouldReceive('toSql')->andReturn('select * from "table" where id = ?');

        $this->assertEquals('(select * from "table" where id = ?)', (string) $sub);

        $sub->setAlias('table_alias');

        $this->assertEquals('(select * from "table" where id = ?) as "table_alias"', (string) $sub);
        $this->assertEquals('table_alias', $sub->getAlias());
    }

    /** @test */
    public function it_accepts_eloquent_and_query_builder()
    {
        $builder = Mockery::mock('\Illuminate\Database\Query\Builder');
        $sub = new Subquery($builder);

        $eloquent = Mockery::mock('\Illuminate\Database\Eloquent\Builder');
        $eloquent->shouldReceive('getQuery')->andReturn($builder);
        $sub = new Subquery($eloquent);
    }
}
