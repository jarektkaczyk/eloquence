<?php

namespace Sofa\Eloquence\Tests;

use Mockery;
use Sofa\Eloquence\Validable\Observer;

class ValidableObserverTest extends TestCase
{
    /** @test */
    public function saved_validation_disabled()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('skipsValidation')->once()->andReturn(Observer::SKIP_ALWAYS);
        $validable->shouldReceive('enableValidation')->never();

        $observer->saved($validable);
    }

    /** @test */
    public function saved_after_skipping_once()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('skipsValidation')->once()->andReturn(Observer::SKIP_ONCE);
        $validable->shouldReceive('enableValidation')->once();

        $observer->saved($validable);
    }

    /** @test */
    public function updating_invalid()
    {
        $observer = new Observer;

        $validator = Mockery::mock('\Illuminate\Contracts\Validation\Validator');
        $validator->shouldReceive('setRules')->once()->with(['update_rules'])->andReturn($validator);
        $validator->shouldReceive('setRules')->once()->with(['create_rules'])->andReturn($validator);

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(true);
        $validable->shouldReceive('getValidator')->twice()->andReturn($validator);
        $validable->shouldReceive('getUpdateRules')->once()->andReturn(['update_rules']);
        $validable->shouldReceive('getCreateRules')->once()->andReturn(['create_rules']);
        $validable->shouldReceive('isValid')->once()->andReturn(false);

        $this->assertFalse($observer->updating($validable));
    }

    /** @test */
    public function updating_validation_disabled()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(false);

        $this->assertNull($observer->updating($validable));
    }

    /** @test */
    public function updating_valid()
    {
        $observer = new Observer;

        $validator = Mockery::mock('\Illuminate\Contracts\Validation\Validator');
        $validator->shouldReceive('setRules')->once()->with(['update_rules'])->andReturn($validator);
        $validator->shouldReceive('setRules')->once()->with(['create_rules'])->andReturn($validator);

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(true);
        $validable->shouldReceive('getValidator')->twice()->andReturn($validator);
        $validable->shouldReceive('getUpdateRules')->once()->andReturn(['update_rules']);
        $validable->shouldReceive('getCreateRules')->once()->andReturn(['create_rules']);
        $validable->shouldReceive('isValid')->once()->andReturn(true);

        $this->assertNull($observer->updating($validable));
    }

    /** @test */
    public function creating_invalid()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(true);
        $validable->shouldReceive('isValid')->once()->andReturn(false);

        $this->assertFalse($observer->creating($validable));
    }

    /** @test */
    public function creating_validation_disabled()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(false);

        $this->assertNull($observer->creating($validable));
    }

    /** @test */
    public function creating_valid()
    {
        $observer = new Observer;

        $validable = Mockery::mock('\Sofa\Eloquence\Contracts\Validable');
        $validable->shouldReceive('validationEnabled')->once()->andReturn(true);
        $validable->shouldReceive('isValid')->once()->andReturn(true);

        $this->assertNull($observer->creating($validable));
    }
}
