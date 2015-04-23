<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\ArgumentBag;

class ArgumentBagTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::isEmpty
	 */
	public function is_empty()
	{
		$bag = $this->getBag();

		$empty = new ArgumentBag([]);

		$this->assertFalse($bag->isEmpty());
		$this->assertTrue($empty->isEmpty());
	}

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::set
	 */
	public function set()
	{
		$bag = $this->getBag();
		$bag->set('foo', 'foo_value');
		$bag->set('bar', 'bar_value');

		$this->assertEquals('foo_value', $bag->get('foo'));
		$this->assertEquals('foo_value', $bag->get('foo'));

		$bag->set('foo', 'bazinga');

		$this->assertEquals('bazinga', $bag->get('foo'));
	}

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::get
	 */
	public function get()
	{
		$bag = $this->getBag();

		$this->assertEquals('baz', $bag->get('faz'));
	}

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::last
	 */
	public function last()
	{
		$bag = $this->getBag();

		$this->assertEquals('baz', $bag->last());
	}

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::first
	 */
	public function first()
	{
		$bag = $this->getBag();

		$this->assertEquals('bar', $bag->first());
	}

	/**
	 * @test
	 * @covers \Sofa\Eloquence\ArgumentBag::all
	 * @covers \Sofa\Eloquence\ArgumentBag::__construct
	 */
	public function all()
	{
		$args = ['foo' => 'bar', 'faz' => 'baz'];

		$bag = $this->getBag();

		$this->assertEquals($args, $bag->all());
	}

	protected function getBag()
	{
		return new ArgumentBag(['foo' => 'bar', 'faz' => 'baz']);
	}
}
