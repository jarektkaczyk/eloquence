<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Formattable;

class FormattableTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->model = new FormattableModelStub();
    }

    /**
     * @test
     */
    public function it_know_if_its_static_call()
    {
        $this->assertTrue($this->model->isStaticClassCall('SomeClass@staticMethod'));
        $this->assertFalse($this->model->isStaticClassCall('uppercase'));
    }

    /**
     * @test
     */
    public function it_allow_us_to_call_methods_with_a_pipe()
    {
        $this->assertEquals('Mister', $this->model->formatAttribute('title', 'miStER'));
    }

    /**
     * @test
     */
    public function it_allow_us_to_call_methods_with_an_array()
    {
        $this->assertEquals('Male', $this->model->formatAttribute('gender', 'maLE'));
    }

    /**
     * @test
     */
    public function it_format_attributes_with_global_function()
    {
        $this->assertEquals('Romain', $this->model->formatAttribute('first_name', 'romain'));
        $this->assertEquals('lanz', $this->model->formatAttribute('last_name', 'Lanz'));
    }

    /**
     * @test
     */
    public function it_format_attributes_with_class_method()
    {
        $this->assertEquals('(555)', $this->model->formatAttribute('phone', '555'));
    }

    /**
     * @test
     */
    public function it_format_attributes_with_static_method()
    {
        $this->assertEquals('DEV', $this->model->formatAttribute('type', 'dev'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Formattable::parseMethodArguments
     */
    public function it_passes_additional_parameters_to_formatting_method()
    {
        $this->assertEquals('short', $this->model->formatAttribute('clipped', 'shorten me to 5 letters'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Formattable::callSingleMethod
     * @covers \Sofa\Eloquence\Formattable::isValidStaticMethod
     * @expectedException \InvalidArgumentException
     */
    public function it_throws_exception_for_invalid_formatting_method()
    {
        $this->model->formatAttribute('wrong', 'value');
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Formattable::hasFormatting
     */
    public function it_finds_formatting()
    {
        $this->assertTrue($this->model->hasFormatting('title'));
        $this->assertFalse($this->model->hasFormatting('nothing_here'));
    }
}

class FormattableModelStub
{
    use Formattable {
        isStaticClassCall   as protectedIsStaticClassCall;
        isValidStaticMethod as protectedIsValidStaticMethod;
        hasClassMethod      as protectedHasClassMethod;
        isGlobalFunction    as protectedIsGlobalFunction;
        callStaticMethod    as protectedCallStaticMethod;
        callClassMethod     as protectedCallClassMethod;
        callGlobalFunction  as protectedCallGlobalFunction;
        formatAttribute     as protectedFormatAttribute;
    }

    protected $formats = [
        'title'      => 'strtolower|ucwords',
        'gender'     => ['strtolower', 'ucwords'],
        'first_name' => 'ucwords',
        'last_name'  => 'strtolower',
        'phone'      => 'formatPhone',
        'type'       => 'Sofa\Eloquence\Tests\FormatterStub@uppercase',
        'clipped'    => 'substr:0,5',
        'wrong'      => 'I\Dont\Exist@function'
    ];

    public function formatPhone($phone)
    {
        return "({$phone})";
    }

    public function isStaticClassCall($method)
    {
        return $this->protectedIsStaticClassCall($method);
    }

    public function isValidStaticMethod($method)
    {
        return $this->protectedIsValidStaticMethod($method);
    }

    public function hasClassMethod($method)
    {
        return $this->protectedHasClassMethod($method);
    }

    public function isGlobalFunction($method)
    {
        return $this->protectedIsGlobalFunction($method);
    }

    public function callStaticMethod($method, $value)
    {
        return $this->protectedCallStaticMethod($method, $value);
    }

    public function callClassMethod($method, $value)
    {
        return $this->protectedCallClassMethod($method, $value);
    }

    public function callGlobalFunction($function, $value)
    {
        return $this->protectedCallGlobalFunction($function, $value);
    }

    public function formatAttribute($key, $value)
    {
        return $this->protectedFormatAttribute($key, $value);
    }
}

class FormatterStub
{
    public static function uppercase($value)
    {
        return strtoupper($value);
    }
}
