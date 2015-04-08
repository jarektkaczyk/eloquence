<?php namespace Sofa\Eloquence\Tests;

use Sofa\Eloquence\Pipeline\ArgumentBag;

class ArgumentBagTest extends \PHPUnit_Framework_TestCase {

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::isEmpty
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
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::get
	 */
	public function get()
	{
		$bag = $this->getBag();
	
		$this->assertEquals('baz', $bag->get('faz'));
	}

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::last
	 */
	public function last()
	{
		$bag = $this->getBag();
	
		$this->assertEquals('baz', $bag->last());
	}

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::first
	 */
	public function first()
	{
		$bag = $this->getBag();
	
		$this->assertEquals('bar', $bag->first());
	}

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::all
	 * @covers \Sofa\Eloquence\Pipeline\ArgumentBag::__construct
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