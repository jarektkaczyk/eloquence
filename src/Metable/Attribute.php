<?php namespace Sofa\Eloquence\Metable;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Sofa\Eloquence\Contracts\Attribute as AttributeContract;

class Attribute extends Model implements AttributeContract
{
    /**
     * Custom table name.
     *
     * @var string
     */
    protected static $customTable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'meta_attributes';

    /**
     * @var array
     */
    protected $getMutators = [
        'array'         => 'json_decode',
        'StdClass'      => 'json_decode',
        Model::class    => 'unserialize',
        DateTime::class => 'asDateTime',
    ];

    /**
     * @var array
     */
    protected $setMutators = [
        'array'         => 'json_encode',
        'StdClass'      => 'json_encode',
        Model::class    => 'serialize',
        DateTime::class => 'fromDateTime',
    ];

    /**
     * The attributes included in the model's JSON and array form.
     *
     * @var array
     */
    protected $visible = ['key', 'value', 'type', 'created_at', 'updated_at'];

    public function __construct($key = null, $value = '', $attributes = [])
    {
        parent::__construct([]);

        $this->set($key, $value);
    }

    /**
     * Set the meta attribute.
     *
     * @param string $key
     * @param mixed  $value
     */
    protected function set($key, $value)
    {
        $this->setKey($key);
        $this->setValue($value);
    }

    /**
     * Create new AttributeBag.
     *
     * @param  array  $models
     * @return \Sofa\Eloquence\Metable\AttributeBag
     */
    public function newCollection(array $models = [])
    {
        return new AttributeBag($models);
    }

    /**
     * Get the meta attribute value.
     *
     * @return mixed
     */
    public function getValue()
    {
        if ($this->hasMetaGetMutator()) {
            return $this->mutateValue($this->value, 'get');
        }

        return $this->castValue();
    }

    /**
     * Get the meta attribute key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Cast value to proper type.
     *
     * @return mixed
     */
    protected function castValue()
    {
        $value = $this->value;

        $validTypes = ['bool', 'int', 'float', 'double', 'array', 'object', 'null'];

        if (in_array($this->type, $validTypes)) {
            settype($value, $this->type);
        }

        return $value;
    }

    /**
     * Set key of the meta attribute.
     *
     * @param string $key
     */
    protected function setKey($key)
    {
        $this->attributes['key'] = $key;
    }

    /**
     * Set type of the meta attribute.
     *
     * @param mixed $value
     */
    protected function setType($value)
    {
        $this->attributes['type'] = $this->hasMetaSetMutator($value)
            ? $this->getMutatedType($value, 'set')
            : $this->getValueType($value);
    }

    /**
     * Set value of the meta attribute.
     *
     * @param mixed $value
     *
     * @throws \Sofa\Eloquence\Exceptions\InvalidTypeException
     */
    public function setValue($value)
    {
        $this->setType($value);

        if ($this->hasMetaSetMutator($value)) {
            $value = $this->mutateValue($value, 'set');

        } elseif (!$this->isStringable($value) && !is_null($value)) {
            throw new InvalidTypeException(
                "Unsupported meta value type [{$this->getValueType($value)}]."
            );
        }

        $this->attributes['value'] = $value;
    }

    /**
     * Mutate attribute value.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return mixed
     */
    protected function mutateValue($value, $dir = 'set')
    {
        $mutator = $this->getMutator($value, $dir, $this->type);

        if (method_exists($this, $mutator)) {
            $mutator = [$this, $mutator];
        }

        if (!is_callable($mutator)) {
            throw new InvalidMutatorException("[{$mutator}] function not found.");
        }

        return call_user_func_array($mutator, [$value]);
    }

    /**
     * Determine whether the value type can be set to string.
     *
     * @param  mixed   $value
     * @return boolean
     */
    protected function isStringable($value)
    {
        return is_scalar($value);
    }

    /**
     * Determine whether a get mutator exists for the value type.
     *
     * @param  mixed   $value
     * @return boolean
     */
    public function hasMetaGetMutator()
    {
        return $this->hasMutator($this->value, 'get', $this->type);
    }

    /**
     * Determine whether a set mutator exists for the value type.
     *
     * @param  mixed   $value
     * @return boolean
     */
    public function hasMetaSetMutator($value)
    {
        return $this->hasMutator($value, 'set');
    }

    /**
     * Get the value type.
     *
     * @param  mixed  $value
     * @return string
     */
    protected function getValueType($value)
    {
        $type = is_object($value) ? get_class($value) : gettype($value);

        // use float instead of deprecated double
        return ($type == 'double') ? 'float' : $type;
    }

    /**
     * Get the mutated type.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return string
     */
    protected function getMutatedType($value, $dir = 'set')
    {
        foreach ($this->{"{$dir}Mutators"} as $mutated => $mutator) {
            if ($this->getValueType($value) == $mutated || $value instanceof $mutated) {
                $type = $mutated;
            }
        }

        return $type;
    }

    /**
     * Determine whether a mutator exists for the value type.
     *
     * @param  mixed   $value
     * @param  string  $dir
     * @return boolean
     */
    protected function hasMutator($value, $dir = 'set', $type = null)
    {
        return (bool) $this->getMutator($value, $dir, $type);
    }

    /**
     * Get mutator for the type.
     *
     * @param  mixed  $value
     * @param  string $dir
     * @return string
     */
    protected function getMutator($value, $dir = 'set', $type = null)
    {
        $type = $type ?: $this->getValueType($value);

        foreach ($this->{"{$dir}Mutators"} as $mutated => $mutator) {
            if ($type == $mutated || $value instanceof $mutated) {
                return $mutator;
            }
        }
    }

    /**
     * Allow custom table name for meta attributes via config.
     *
     * @return string
     */
    public function getTable()
    {
        return isset(static::$customTable) ? static::$customTable : parent::getTable();
    }

    /**
     * Set custom table for the meta attributes. Allows doing it only once
     * in order to mimic protected behaviour, most likely in the service
     * provider, which in turn gets the table name from configuration.
     *
     * @param string $table
     */
    public static function setCustomTable($table)
    {
        if (!isset(static::$customTable)) {
            static::$customTable = $table;
        }
    }

    /**
     * Handle casting value to string.
     *
     * @return string
     */
    public function castToString()
    {
        if ($this->type == 'array') {
            return $this->value;
        }

        $value = $this->getValue();

        if ($this->isStringable($value) || is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Handle dynamic casting to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->castToString();
    }
}
