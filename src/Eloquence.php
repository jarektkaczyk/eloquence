<?php namespace Sofa\Eloquence;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Pipeline\Pipeline;
use Sofa\Eloquence\Pipeline\ArgumentBag;

trait Eloquence
{
    /**
     * Wrapped hooks on Eloquent methods.
     *
     * @var array
     */
    protected static $wrappedHooks = [];

    /**
     * Unwrapped hooks bound to the instance.
     *
     * @var array
     */
    protected $unwrappedHooks = [];

    /**
     * Register hook on Eloquent method.
     *
     * @param  string  $method
     * @param  string  $hook
     * @return void
     */
    public static function hook($method, $hook = null)
    {
        if (is_null($hook)) {
            $hook = static::guessHookName($method);
        }

        static::$wrappedHooks[$method][] = static::wrapHook($hook);
    }

    /**
     * Guess the hook name. Defaults to [method][Trait].
     *
     * @codeCoverageIgnore
     *
     * @param  string $method
     * @return string
     */
    protected static function guessHookName($method)
    {
        list(,, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        $function = $caller['function'];

        $trait = substr($function, 4);

        return $method.$trait;
    }

    /**
     * Wrap hook method in a closure so it can be bound to the instance later.
     *
     * @param  string $hook
     * @return void
     */
    protected static function wrapHook($hook)
    {
        return function ($model) use ($hook) {
            return $model->{$hook}();
        };
    }

    /**
     * Determine whether a method has any hooks registered.
     *
     * @param  string  $method
     * @return boolean
     */
    public static function hasHook($method)
    {
        return array_key_exists($method, static::$wrappedHooks);
    }

    /**
     * Register hook for getAttribute.
     *
     * @codeCoverageIgnore
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $this->unwrapHooks(__FUNCTION__);

        $pipes = $this->unwrappedHooks[__FUNCTION__];

        return (new Pipeline)
                ->send(parent::getAttribute($key))
                ->with(new ArgumentBag(compact('key')))
                ->through($pipes)
                ->to(function ($attribute) {
                    return $attribute;
                });
    }

    /**
     * Register hook for setAttribute.
     *
     * @codeCoverageIgnore
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setAttribute($key, $value)
    {
        $this->unwrapHooks(__FUNCTION__);

        $pipes = array_reverse($this->unwrappedHooks[__FUNCTION__]);

        return (new Pipeline)
                ->send($value)
                ->with(new ArgumentBag(compact('key')))
                ->through($pipes)
                ->to(function ($value) use ($key) {
                    parent::setAttribute($key, $value);
                });
    }

    /**
     * Register hook for isset call.
     *
     * @codeCoverageIgnore
     *
     * @param  string  $key
     * @return boolean
     */
    public function __isset($key)
    {
        $this->unwrapHooks(__FUNCTION__);

        $pipes = array_reverse($this->unwrappedHooks[__FUNCTION__]);

        return (new Pipeline)
                ->send(parent::__isset($key))
                ->with(new ArgumentBag(compact('key')))
                ->through($pipes)
                ->to(function ($isset) {
                    return $isset;
                });
    }

    /**
     * Unwrap hooks in the instance context for a method.
     *
     * @param  string $method
     * @return void
     */
    protected function unwrapHooks($method)
    {
        if (array_key_exists($method, $this->unwrappedHooks)) {
            return;
        }

        $this->unwrappedHooks[$method] = [];

        if (static::hasHook($method)) {
            foreach (static::$wrappedHooks[$method] as $wrapped) {
                $this->unwrappedHooks[$method][] = call_user_func($wrapped, $this);
            }
        }
    }
}
