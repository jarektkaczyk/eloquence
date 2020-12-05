<?php

namespace Sofa\Eloquence\Tests;

use Mockery;
use Sofa\Eloquence\AttributeCleaner\Observer;

class CleansAttributesObserverTest extends TestCase
{
    /** @test */
    public function saving_with_incorrect_attributes()
    {
        $dirty = ['name' => 'Jarek Tkaczyk', '_method' => 'patch', 'incorrect_field' => 'value'];

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\CleansAttributes');
        $validable->shouldReceive('getDirty')->once()->andReturn($dirty);
        $validable->shouldReceive('getColumnListing')->once()->andReturn(['id', 'name']);

        foreach ($dirty as $key => $value) {
            $validable->{$key} = $value;
        }

        $observer = new Observer;
        $observer->saving($validable);

        $this->assertFalse(isset($validable->_method));
        $this->assertFalse(isset($validable->incorrect_field));
        $this->assertEquals('Jarek Tkaczyk', $validable->name);
    }
}
