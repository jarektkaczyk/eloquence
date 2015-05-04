<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Sofa\Eloquence\Validable\Observer;

class ObserverTest extends \PHPUnit_Framework_TestCase {

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function updating_invalid()
    {
        $observer = new Observer;

        $validator = m::mock('\Illuminate\Contracts\Validation\Validator');
        $validator->shouldReceive('setRules')->once()->with(['update_rules'])->andReturn($validator);
        $validator->shouldReceive('setRules')->once()->with(['create_rules'])->andReturn($validator);

        $validable = m::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('getValidator')->twice()->andReturn($validator);
        $validable->shouldReceive('getUpdateRules')->once()->andReturn(['update_rules']);
        $validable->shouldReceive('getCreateRules')->once()->andReturn(['create_rules']);
        $validable->shouldReceive('isValid')->once()->andReturn(false);

        $this->assertFalse($observer->updating($validable));
    }

    /**
     * @test
     */
    public function updating_valid()
    {
        $observer = new Observer;

        $validator = m::mock('\Illuminate\Contracts\Validation\Validator');
        $validator->shouldReceive('setRules')->once()->with(['update_rules'])->andReturn($validator);
        $validator->shouldReceive('setRules')->once()->with(['create_rules'])->andReturn($validator);

        $validable = m::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('getValidator')->twice()->andReturn($validator);
        $validable->shouldReceive('getUpdateRules')->once()->andReturn(['update_rules']);
        $validable->shouldReceive('getCreateRules')->once()->andReturn(['create_rules']);
        $validable->shouldReceive('isValid')->once()->andReturn(true);

        $this->assertNull($observer->updating($validable));
    }

    /**
     * @test
     */
    public function creating_invalid()
    {
        $observer = new Observer;

        $validable = m::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('isValid')->once()->andReturn(false);

        $this->assertFalse($observer->creating($validable));
    }

    /**
     * @test
     */
    public function creating_valid()
    {
        $observer = new Observer;

        $validable = m::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('isValid')->once()->andReturn(true);

        $this->assertNull($observer->creating($validable));
    }
}
