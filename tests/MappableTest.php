<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Mappable;

class MappableTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->model = new ModelStub;
    }

    /**
     * @test
     * @covers \Sofa\Eloquence\Mappable::hasMapping
     * @covers \Sofa\Eloquence\Mappable::hasExplicitMapping
     * @covers \Sofa\Eloquence\Mappable::getMaps
     */
    public function it_finds_mapped_attribute_using_explicit_dot_notation()
    {
        $this->assertTrue($this->model->hasMapping('foo'));
        $this->assertFalse($this->model->hasMapping('bar'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Mappable::hasMapping
     * @covers \Sofa\Eloquence\Mappable::hasImplicitMapping
     */
    public function it_finds_mapped_attribute_using_implicit_array_notation()
    {
        $this->assertTrue($this->model->hasMapping('name'));
        $this->assertFalse($this->model->hasMapping('bar'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Mappable::getExplicitMapping
     */
    public function it_fetches_explicit_mapping()
    {
        $this->assertEquals('bar.baz', $this->model->getExplicitMapping('foo'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Mappable::getImplicitMapping
     */
    public function it_fetches_implicit_mapping()
    {
        $this->assertEquals('related.name', $this->model->getImplicitMapping('name'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Mappable::mapAttribute
     * @covers \Sofa\Eloquence\Mappable::getMappingForAttribute
     */
    public function it_gets_mapped_value()
    {
        // close relations
        $this->assertEquals('name_value', $this->model->mapAttribute('name'));
        $this->assertEquals('baz_value', $this->model->mapAttribute('foo'));

        // far relations
        $this->assertEquals('far_value', $this->model->mapAttribute('far_field'));
        $this->assertEquals('bam_value', $this->model->mapAttribute('bam'));
    }

    /** 
     * @test
     * @covers \Sofa\Eloquence\Mappable::setMappedAttribute
     * @covers \Sofa\Eloquence\Mappable::getTarget
     */
    public function it_sets_mapped_value()
    {
        // close relation
        $this->model->setMappedAttribute('email', 'new_email_value');
        $this->assertEquals('new_email_value', $this->model->mapAttribute('email'));

        // far relation
        $this->model->setMappedAttribute('bam', 'new_bam_value');
        $this->assertEquals('new_bam_value', $this->model->mapAttribute('bam'));
    }
}


class ModelStub {

    use Mappable {
        hasExplicitMapping as protectedHasExplicitMapping;
        hasImplicitMapping as protectedHasImplicitMapping;
        getExplicitMapping as protectedGetExplicitMapping;
        getImplicitMapping as protectedGetImplicitMapping;
        setMappedAttribute as protectedSetMappedAttribute;
        mapAttribute       as protectedMapAttribute;
    }

    protected $maps = [
        // explicit mappings
        'foo'         => 'bar.baz',         // $this->bar->baz
        'bam'         => 'bar.bad.bam',     // $this->bar->bad->bam

        // implicit mappings
        'related'     => ['name', 'email'], // $this->related->name
        'related.far' => ['far_field'],     // $this->related->far->far_field
    ];

    public function __construct()
    {
        $this->bar     = $this->getRelatedStub([
            'baz' => 'baz_value',
            'bad' => $this->getRelatedStub([
                'bam' => 'bam_value'
            ])
        ]);

        $this->related = $this->getRelatedStub([
            'name'  => 'name_value',
            'email' => 'email_value',
            'far'   => $this->getRelatedStub([
                'far_field' => 'far_value'
            ])
        ]);
    }

    public function setMappedAttribute($key, $value)
    {
        return $this->protectedSetMappedAttribute($key, $value);
    }

    public function mapAttribute($key)
    {
        return $this->protectedMapAttribute($key);
    }

    public function getImplicitMapping($key)
    {
        return $this->protectedGetImplicitMapping($key);
    }

    public function getExplicitMapping($key)
    {
        return $this->protectedGetExplicitMapping($key);
    }

    protected function getRelatedStub($attributes)
    {
        return (object) $attributes;
    }
}
