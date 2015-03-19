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
    public function it_parse_the_static_call()
    {
        list($class, $method) = $this->model->parseStaticCall('SomeClass@staticMethod');

        $this->assertEquals('SomeClass', $class);
        $this->assertEquals('staticMethod', $method);
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
}

class FormattableModelStub
{
    use Formattable {
        isStaticClassCall  as protectedIsStaticClassCall;
        parseStaticCall    as protectedParseStaticCall;
        hasStaticMethod    as protectedHasStaticMethod;
        hasClassMethod     as protectedHasClassMethod;
        hasGlobalFunction  as protectedHasGlobalFunction;
        callStaticMethod   as protectedCallStaticMethod;
        callClassMethod    as protectedCallClassMethod;
        callGlobalFunction as protectedCallGlobalFunction;
        formatAttribute    as protectedFormatAttribute;
    }

    protected $formats = [
        'title' => 'strtolower|ucwords',
        'gender' => ['strtolower', 'ucwords'],
        'first_name' => 'ucwords',
        'last_name' => 'strtolower',
        'phone' => 'formatPhone',
        'type' => 'Sofa\Eloquence\Tests\FormatterStub@uppercase',
    ];

    public function formatPhone($phone)
    {
        return "({$phone})";
    }

    public function parseStaticCall($method)
    {
        return $this->protectedParseStaticCall($method);
    }

    public function isStaticClassCall($method)
    {
        return $this->protectedIsStaticClassCall($method);
    }

    public function hasStaticMethod($method)
    {
        return $this->protectedHasStaticMethod($method);
    }

    public function hasClassMethod($method)
    {
        return $this->protectedHasClassMethod($method);
    }

    public function hasGlobalFunction($method)
    {
        return $this->protectedHasGlobalFunction($method);
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
