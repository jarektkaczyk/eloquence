<?php namespace Sofa\Eloquence;

use Sofa\Eloquence\Validable\Observer;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Eloquent\Model;

trait Validable
{
    /**
     * Validator instance.
     *
     * @var \Illuminate\Contracts\Validation\Validator
     */
    protected $validator;

    /**
     * Validator factory instance.
     *
     * @var \Illuminate\Contracts\Validation\Factory
     */
    protected static $validatorFactory;

    /**
     * All the validation rules for this model.
     *
     * @var array
     */
    protected static $rulesMerged;

    /**
     * Register hooks for the trait.
     *
     * @codeCoverageIgnore
     *
     * @return void
     */
    public static function bootValidable()
    {
        static::observe(new Observer);

        if (!static::$validatorFactory) {
            if (function_exists('app') && isset(app()['validator'])) {
                static::setValidatorFactory(app('validator'));
            }
        }
    }

    /**
     * Determine whether all the attributes on this instance pass validation.
     *
     * @return boolean
     */
    public function isValid()
    {
        if ($this->exists && !$this->isDirty()) {
            return true;
        }

        $this->getValidator()->setData($this->getAttributes());

        return $this->getValidator()->passes();
    }

    /**
     * Retrieve validation error messages.
     *
     * @return \Illuminate\Support\MessageBag
     */
    public function getValidationErrors()
    {
        return $this->getValidator()->errors();
    }

    /**
     * Get names of the attributes that didn't pass validation.
     *
     * @return array
     */
    public function getInvalidAttributes()
    {
        return array_keys($this->getValidationErrors()->toArray());
    }

    /**
     * Get the validator instance.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function getValidator()
    {
        if (!$this->validator) {
            $this->validator = static::$validatorFactory->make(
                [],
                static::getCreateRules(),
                static::getValidationMessages(),
                static::getValidationAttributes()
            );
        }

        return $this->validator;
    }

    /**
     * Get custom validation messages.
     *
     * @return array
     */
    public static function getValidationMessages()
    {
        return (property_exists(get_called_class(), 'validationMessages'))
            ? static::$validationMessages
            : [];
    }

    /**
     * Get custom validation attribute names.
     *
     * @return array
     */
    public static function getValidationAttributes()
    {
        return (property_exists(get_called_class(), 'validationAttributes'))
            ? static::$validationAttributes
            : [];
    }

    /**
     * Get all the validation rules for this model.
     *
     * @return array
     */
    public static function getCreateRules()
    {
        if (!static::$rulesMerged) {
            static::$rulesMerged = static::gatherRules();
        }

        return static::$rulesMerged;
    }

    /**
     * Gather all the rules for the model and store it for easier use.
     *
     * @return array
     */
    protected static function gatherRules()
    {
        // This rather gnarly looking logic is just for developer convenience
        // so he can define multiple rule groups on the model for clarity
        // and now we simply gather all rules and merge them together.
        $keys = static::getValidatedFields();

        $result = array_fill_keys($keys, []);

        foreach ($keys as $key) {
            foreach (static::getRulesGroups() as $groupName) {
                $group = static::getRulesGroup($groupName);

                if (isset($group[$key])) {
                    $rule = is_array($group[$key])
                            ? $group[$key]
                            : explode('|', $group[$key]);

                    $result[$key] = array_unique(array_merge($result[$key], $rule));
                }
            }
        }

        return $result;
    }

    /**
     * Get all validation rules for update on this model.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string $id
     * @param  string $primaryKey
     * @return array
     */
    public function getUpdateRules()
    {
        return static::getUpdateRulesForId($this);
    }

    /**
     * Get all validation rules for update for given id.
     *
     * @param  \Illuminate\Database\Eloquent\Model|string $id
     * @param  string $primaryKey
     * @return array
     */
    public static function getUpdateRulesForId($id, $primaryKey = 'id')
    {
        return rules_for_update(static::getCreateRules(), $id, $primaryKey);
    }

    /**
     * Get array of attributes that have validation rules defined.
     *
     * @return array
     */
    public static function getValidatedFields()
    {
        $fields = [];

        foreach (static::getRulesGroups() as $groupName) {
            $fields = array_merge($fields, array_keys(static::getRulesGroup($groupName)));
        }

        return array_values(array_unique($fields));
    }

    /**
     * Get all the rules groups defined on this model.
     *
     * @return array
     */
    protected static function getRulesGroups()
    {
        $groups = [];

        foreach (get_class_vars(get_called_class()) as $property => $val) {
            if (preg_match('/^.*rules$/i', $property)) {
                $groups[] = $property;
            }
        }

        return $groups;
    }

    /**
     * Get rules from given group.
     *
     * @param  string $group
     * @return
     */
    protected static function getRulesGroup($group)
    {
        return static::$$group;
    }

    /**
     * Set validation factory instance for this model.
     *
     * @param \Illuminate\Contracts\Validation\Factory
     */
    public static function setValidatorFactory(Factory $factory)
    {
        static::$validatorFactory = $factory;
    }
}
