<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Metable\Attribute;
use Sofa\Eloquence\Metable\AttributeBag;

class AttributeTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function it_instantiates_as_eloquent_by_default()
    {
        $emptyModel = new Attribute;
        $arrayModel = new Attribute([]);

        $this->assertEquals([], $emptyModel->getAttributes());
        $this->assertEquals([], $arrayModel->getAttributes());
    }

    /**
     * @test
     */
    public function it_handles_casting_to_string()
    {
        $color  = new Attribute('color', 'red');
        $array  = new Attribute('array', [1,2,3]);
        $object = new Attribute('array', (object) ['foo', 'bar']);

        $this->assertEquals('red', (string) $color);
        $this->assertEquals('[1,2,3]', (string) $array);
        $this->assertEquals('', (string) $object);
    }

    /**
     * @test
     */
    public function it_calls_instance_mutators()
    {
        $attribute = new AttributeNoMutatorsStub('foo', [1,2]);
        $attribute->getterMutators = ['array' => 'customMutator'];
        $this->assertEquals('mutated_value', $attribute->getValue());
    }

    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     */
    public function it_rejects_invalid_variable_name_as_key()
    {
        $attribute = new Attribute('foo-bar', 'value');
    }

    /**
     * @test
     *
     * @expectedException \InvalidArgumentException
     */
    public function it_rejects_user_types_without_mutator()
    {
        $attribute = new Attribute('foo', $this->getAttribute()->newCollection());
    }

    /**
     * @test
     *
     * @dataProvider validTypes
     */
    public function it_casts_values_to_proper_types($typeAttribute)
    {
        list($type, $attribute) = $typeAttribute;

        $this->assertInternalType($type, $attribute->getValue());
    }

    /**
     * dataProvider
     */
    public function validTypes()
    {
        return [
            [['int',    new AttributeNoMutatorsStub('key', 1)]],
            [['float',  new AttributeNoMutatorsStub('key', 1.5)]],
            [['bool',   new AttributeNoMutatorsStub('key', true)]],
            [['array',  new AttributeNoMutatorsStub('key', [1,2])]],
            [['null',   new AttributeNoMutatorsStub('key', null)]],
        ];
    }

    /**
     * @test
     */
    public function getters()
    {
        $attribute = $this->getAttribute();

        $this->assertEquals('color', $attribute->getMetaKey());
        $this->assertEquals('red', $attribute->getValue());
        $this->assertEquals(NULL, $attribute->getMetaGroup());

        $attribute = $this->getAttributeWithGroup('group');

        $this->assertEquals('color', $attribute->getMetaKey());
        $this->assertEquals('red', $attribute->getValue());
        $this->assertEquals('group', $attribute->getMetaGroup());
    }

    /**
     * @test
     */
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

class AttributeStub extends Attribute {
    protected static $customTable;
}

class AttributeNoMutatorsStub extends Attribute {
    public $getterMutators = [];

    public function customMutator($value)
    {
        return 'mutated_value';
    }
}
