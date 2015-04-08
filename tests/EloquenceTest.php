<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Eloquence;

class EloquenceTest extends \PHPUnit_Framework_TestCase {

	public function setUp()
	{
		EloquenceStub::clearHooks();
	}

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Eloquence::hasHook
	 */
	public function it_finds_registered_hooks()
	{
		EloquenceStub::hook('__isset', '__issetExtensionStub');

		$model = new EloquenceStub;

		$this->assertTrue($model->hasHook('__isset'));
	}

	/** 
	 * @test
	 * @covers \Sofa\Eloquence\Eloquence::hook
	 * @covers \Sofa\Eloquence\Eloquence::wrapHook
	 * @covers \Sofa\Eloquence\Eloquence::unwrapHooks
	 */
	public function it_registers_and_call_hooks_on_eloquent_methods()
	{
		EloquenceStub::hook('__isset', '__issetExtensionStub');

		$model = new EloquenceStub;

		$this->assertFalse(isset($model->foo));

		$model->foo = 1;
		$this->assertFalse(isset($model->foo));
	}
}

class EloquenceStub extends Model {

	use Eloquence, ExtensionStub;

	public static function clearHooks()
	{
		static::$wrappedHooks = [];
	}
}

trait ExtensionStub {

	public function __issetExtensionStub()
	{
		return function () {
			return false;
		};
	}
}