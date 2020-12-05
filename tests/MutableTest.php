<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Mutable;

class MutableTest extends TestCase
{
    /** @test */
    public function it_mutates_attributes_on_set()
    {
        $model = $this->getModel();
        $mutator = $model->getAttributeMutator();
        $mutator->shouldReceive('mutate')->with('EMAIL@domain.com', 'strtolower')->andReturn('email@domain.com');

        $model->email = 'EMAIL@domain.com';

        $this->assertEquals('email@domain.com', $model->getAttributes()['email']);
    }

    /** @test */
    public function it_mutates_attributes_to_array()
    {
        $model = $this->getModel();
        $mutator = $model->getAttributeMutator();
        $mutator->shouldReceive('mutate')->with('jarek', 'ucwords')->andReturn('Jarek');
        $mutator->shouldReceive('mutate')->with('tkaczyk', 'strtoupper')->andReturn('TKACZYK');

        $expected = ['first_name' => 'Jarek', 'last_name' => 'TKACZYK', 'email' => 'JAREK@SOFTONSOFA.COM'];

        $this->assertEquals($expected, $model->toArray());
    }

    protected function getModel()
    {
        $mutator = Mockery::mock('\Sofa\Eloquence\Contracts\Mutator');

        $model = new MutableEloquentStub;
        $model->setRawAttributes(['first_name' => 'jarek', 'last_name' => 'tkaczyk', 'email' => 'JAREK@SOFTONSOFA.COM']);
        $model->setAttributeMutator($mutator);

        return $model;
    }
}

class MutableEloquentStub extends Model
{
    use Eloquence, Mutable;

    protected $getterMutators = [
        'first_name' => 'ucwords',
        'last_name' => 'strtoupper',
    ];

    protected $setterMutators = [
        'email' => 'strtolower',
    ];
}
