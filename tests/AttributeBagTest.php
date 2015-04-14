<?php namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Metable\AttributeBag;
use Sofa\Eloquence\Metable\Attribute;

class AttributeBagTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__isset
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__unset
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__get
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__set
     */
    public function it_handles_magic_calls()
    {
        $bag = $this->getBag();

        $this->assertTrue(isset($bag->foo));
        $this->assertFalse(isset($bag->wrong));

        $this->assertEquals('bar', $bag->foo);
        $this->assertNull($bag->wrong);

        $bag->color = 'red';
        $this->assertEquals('red', $bag->color);

        unset($bag->color);
        $this->assertNull($bag->color);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::forget
     * @covers \Sofa\Eloquence\Metable\AttributeBag::offsetUnset
     */
    public function it_sets_value_to_null_when_unsetting()
    {
        $bag = $this->getBag()->set('key', 'value');
        $bag->set('key', null);
        $bag->forget('baz');
        unset($bag['foo']);

        $this->assertNull($bag->getValue('key'));
        $this->assertNull($bag->getValue('baz'));
        $this->assertNull($bag->getValue('foo'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::set
     * @covers \Sofa\Eloquence\Metable\AttributeBag::update
     */
    public function it_updates_existing_attribute()
    {
        $bag = $this->getBag()->set('foo', 'new_bar');

        $this->assertEquals('new_bar', $bag->getValue('foo'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::getValue
     */
    public function it_gets_null_for_non_existent_attributes()
    {
        $bag = $this->getBag();

        $this->assertNull($bag->getValue('bad'));
        $this->assertNull($bag->getValue('wrong'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::getValue
     */
    public function it_gets_raw_attribute_value()
    {
        $bag = $this->getBag();

        $this->assertEquals('bar', $bag->getValue('foo'));
        $this->assertEquals('bax', $bag->getValue('baz'));
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__construct
     * @covers \Sofa\Eloquence\Metable\AttributeBag::loadAndIndex
     * @covers \Sofa\Eloquence\Metable\AttributeBag::add
     * @covers \Sofa\Eloquence\Metable\AttributeBag::set
     * @covers \Sofa\Eloquence\Metable\AttributeBag::validate
     * @covers \Sofa\Eloquence\Metable\AttributeBag::newAttribute
     */
    public function it_accepts_only_valid_attribute()
    {
        $bag = $this->getBag();

        $bag->add(new Attribute('key', 'value'));

        $newBag = new AttributeBag([new Attribute('key', 'value'), (object) ['key' => 'foo', 'value' => 'bar']]);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::add
     * @covers \Sofa\Eloquence\Metable\AttributeBag::validate
     *
     * @dataProvider nonAttributes
     * @expectedException \InvalidArgumentException
     */
    public function it_doesnt_accept_non_attribute_types($nonAttribute)
    {
        $bag = $this->getBag();

        $bag->add($nonAttribute);
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Metable\AttributeBag::__construct
     * @covers \Sofa\Eloquence\Metable\AttributeBag::loadAndIndex
     *
     * @dataProvider invalidAttributes
     * @expectedException \InvalidArgumentException
     */
    public function it_doesnt_accept_invalid_attributes($invalidAttribute)
    {
        $bag = new AttributeBag([$invalidAttribute]);
    }

    /**
     * dataProvider
     */
    public function invalidAttributes()
    {
        // Because of how Eloquent handles eager loading
        // we need to allow StdClass instance as well.
        return [
            [(object) ['key' => 'foo', 'value' => null]],
            [(object) ['value' => 'bar']],
            [new Attribute('key', null)],
        ];
    }

    /**
     * dataProvider
     */
    public function nonAttributes()
    {
        return [
            [['key' => 'value']],
            [new AttributeBagModelStub],
            [1],
        ];
    }

    protected function getBag()
    {
        return new AttributeBag([
            new Attribute('foo', 'bar'),
            new Attribute('baz', 'bax')
        ]);
    }
}

class AttributeBagModelStub extends Model {}
