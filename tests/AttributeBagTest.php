<?php

namespace Sofa\Eloquence\Tests;

use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Metable\AttributeBag;
use Sofa\Eloquence\Metable\Attribute;

class AttributeBagTest extends \PHPUnit_Framework_TestCase {

    /**
     * @test
     */
    public function it_replicates()
    {
        $original = $this->getBag();
        $copy = $original->replicate();

        $this->assertEquals($original, $copy);
        $this->assertNotSame($original, $copy);
        $this->assertEquals($original->first(), $copy->first());
        $this->assertNotSame($original->first(), $copy->first());
    }

    /**
     * @test
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
     */
    public function it_updates_existing_attribute()
    {
        $bag = $this->getbag()->set('foo', 'new_bar');
        $bag->set(new Attribute('baz', 'new_bax'));
        $bag->add(new Attribute('key', 'value'));
        $bag['key'] = 'new_value';

        $this->assertEquals('new_bar', $bag->getValue('foo'));
        $this->assertEquals('new_bax', $bag->getValue('baz'));
        $this->assertEquals('new_value', $bag->getValue('key'));
    }

    /**
     * @test
     */
    public function it_gets_null_for_non_existent_attributes()
    {
        $bag = $this->getBag();

        $this->assertNull($bag->getValue('bad'));
        $this->assertNull($bag->getValue('wrong'));
    }

    /**
     * @test
     */
    public function it_gets_raw_attribute_value()
    {
        $bag = $this->getBag();

        $this->assertEquals('bar', $bag->getValue('foo'));
        $this->assertEquals('bax', $bag->getValue('baz'));
    }

    /**
     * @test
     */
    public function it_gets_attributes_by_group()
    {
        $bag = $this->getBag();

        $this->assertEquals([
            'baz' => $bag->get('baz'),
            'bar' => $bag->get('bar'),
        ], $bag->getMetaByGroup('group')->toArray());
    }
    /**
     * @test
     */
    public function it_accepts_only_valid_attribute()
    {
        $bag = $this->getBag();

        $bag->add(new Attribute('key', 'value'));

        $newBag = new AttributeBag([new Attribute('key', 'value')]);
    }

    protected function getBag()
    {
        return new AttributeBag([
            new Attribute('foo', 'bar'),
            new Attribute('baz', 'bax','group'),
            new Attribute('bar', 'baz','group'),
        ]);
    }
}

class AttributeBagModelStub extends Model {}
