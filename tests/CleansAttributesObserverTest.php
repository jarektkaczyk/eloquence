<?php

namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Sofa\Eloquence\AttributeCleaner\Observer;

class CleansAttributesObserverTest extends \PHPUnit_Framework_TestCase {

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function saving_with_incorrect_attributes()
    {
        $dirty = ['name' => 'Jarek Tkaczyk', '_method' => 'patch', 'incorrect_field' => 'value'];

        $validable = m::mock('\Sofa\Eloquence\Contracts\CleansAttributes');
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
