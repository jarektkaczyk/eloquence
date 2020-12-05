<?php

namespace Sofa\Eloquence\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sofa\Eloquence\Metable\Attribute;

class AttributeTest extends TestCase
{
    /** @test */
    public function it_instantiates_as_eloquent_by_default()
    {
        $emptyModel = new Attribute;
        $arrayModel = new Attribute([]);

        $this->assertEquals([], $emptyModel->getAttributes());
        $this->assertEquals([], $arrayModel->getAttributes());
    }

    /** @test */
    public function it_handles_casting_to_string()
    {
        $color = new Attribute('color', 'red');
        $array = new Attribute('array', [1, 2, 3]);
        $object = new Attribute('array', (object) ['foo', 'bar']);

        $this->assertEquals('red', (string) $color);
        $this->assertEquals('[1,2,3]', (string) $array);
        $this->assertEquals('', (string) $object);
    }

    /** @test */
    public function it_calls_instance_mutators()
    {
        $attribute = new AttributeNoMutatorsStub('foo', [1, 2]);
        $attribute->getterMutators = ['array' => 'customMutator'];
        $this->assertEquals('mutated_value', $attribute->getValue());
    }

    /** @test */
    public function it_rejects_invalid_variable_name_as_key()
    {
        $this->expectException(InvalidArgumentException::class);
        new Attribute('foo-bar', 'value');
    }

    /** @test */
    public function it_rejects_user_types_without_mutator()
    {
        $this->expectException(InvalidArgumentException::class);
        new Attribute('foo', $this->getAttribute()->newCollection());
    }

    /** @test */
    public function it_casts_values_to_proper_types()
    {
        $this->assertTrue(is_int((new AttributeNoMutatorsStub('key', 1))->getValue()));
        $this->assertTrue(is_float((new AttributeNoMutatorsStub('key', 1.5))->getValue()));
        $this->assertTrue(is_bool((new AttributeNoMutatorsStub('key', true))->getValue()));
        $this->assertTrue(is_null((new AttributeNoMutatorsStub('key', null))->getValue()));
        $this->assertTrue(is_array((new AttributeNoMutatorsStub('key', [1, 2]))->getValue()));
    }

    /** @test */
    public function getters()
    {
        $attribute = $this->getAttribute();

        $this->assertEquals('color', $attribute->getMetaKey());
        $this->assertEquals('red', $attribute->getValue());
        $this->assertEquals(null, $attribute->getMetaGroup());

        $attribute = $this->getAttributeWithGroup('group');

        $this->assertEquals('color', $attribute->getMetaKey());
        $this->assertEquals('red', $attribute->getValue());
        $this->assertEquals('group', $attribute->getMetaGroup());
    }

    /** @test */
    public function it_uses_attribute_bag()
    {
        $bag = $this->getAttribute()->newBag();

        $this->assertInstanceOf('Sofa\Eloquence\Metable\AttributeBag', $bag);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\Attribute::getTable
     * @covers \Sofa\Eloquence\Metable\Attribute::setCustomTable
     */
    public function it_allows_custom_table_name_to_be_set_once()
    {
        $attribute = new AttributeStub;
        $this->assertEquals('meta_attributes', $attribute->getTable());

        AttributeStub::setCustomTable('meta');
        $this->assertEquals('meta', $attribute->getTable());

        AttributeStub::setCustomTable('cant_do_it_again');
        $this->assertEquals('meta', $attribute->getTable());
    }

    protected function getAttribute()
    {
        return new Attribute('color', 'red');
    }

    protected function getAttributeWithGroup($group = null)
    {
        return new Attribute('color', 'red', $group);
    }
}

class AttributeStub extends Attribute
{
    protected static $customTable;
}

class AttributeNoMutatorsStub extends Attribute
{
    public $getterMutators = [];

    public function customMutator($value)
    {
        return 'mutated_value';
    }
}
