<?php namespace Sofa\Eloquence\Tests;

use Mockery as m;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\MessageBag;
use Sofa\Eloquence\Eloquence;
use Sofa\Eloquence\Validable;
use Sofa\Eloquence\Validable\Observer;

class ValidableTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $validator = m::mock('StdClass');
        $validator->shouldReceive('passes')->andReturn(true);

        $factory = m::mock('\Illuminate\Contracts\Validation\Factory');
        $factory->shouldReceive('make')->andReturn($validator);

        ValidableEloquentStub::setValidatorFactory($factory);
    }

    public function tearDown()
    {
        m::close();
    }

    /**
     * @test
     */
    public function rules_for_update_helper()
    {
        $rules = [
            'email' => 'required|email|unique:users',
            'name'  => ['required', 'max:10', 'unique:users,username,null,id,account_id,5'],
        ];

        $rulesAdjusted = [
            'email' => ['required', 'email', 'unique:users,email,10,primary_key'],
            'name'  => ['required', 'max:10', 'unique:users,username,10,primary_key,account_id,5'],
        ];

        $this->assertEquals($rulesAdjusted, rules_for_update($rules, 10, 'primary_key'));
    }

    /**
     * @test
     */
    public function validation_disabling_and_enabling()
    {
        $model = $this->getModel();
        $this->assertTrue($model->validationEnabled());

        $model->disableValidation();
        $this->assertFalse($model->validationEnabled());

        $model->enableValidation();
        $this->assertTrue($model->validationEnabled());
    }

    /**
     * @test
     */
    public function validation_skipping()
    {
        $model = $this->getModel();
        $model->skipValidation();
        $this->assertEquals(Observer::SKIP_ONCE, $model->skipsValidation());
    }

    /**
     * @test
     */
    public function it_adjusts_unique_rules_for_a_model_correctly()
    {
        $model = $this->getModel();
        $model->setRawAttributes(['id' => 10]);

        $emailRules = ['required', 'email', 'unique:users,email,10,id', 'max:255'];
        $nameRules  = ['required', 'max:10', 'unique:users,username,10,id,account_id,5', 'max:255'];

        $this->assertEquals($emailRules, $model->getUpdateRules()['email']);
        $this->assertEquals($nameRules,  $model->getUpdateRules()['name']);
    }

    /**
     * @test
     */
    public function it_gets_invalid_attributes_from_validator_instance()
    {
        $messageBag = new MessageBag(['name' => 'name is required']);
        $model = $this->getModel();
        $validator = $model->getValidator();
        $validator->shouldReceive('getMessageBag')->once()->andReturn($messageBag);

        $this->assertEquals(['name'], $model->getInvalidAttributes());
    }

    /**
     * @test
     */
    public function it_gets_error_messages_from_validator_instance()
    {
        $messageBag = new MessageBag(['name' => 'name is required']);
        $model = $this->getModel();
        $validator = $model->getValidator();
        $validator->shouldReceive('getMessageBag')->once()->andReturn($messageBag);

        $this->assertEquals(new MessageBag(['name' => 'name is required']), $model->getValidationErrors());
    }

    /**
     * @test
     */
    public function it_gathers_all_rules_from_all_groups()
    {
        $rulesMerged = [
            'email'     => ['required', 'email', 'unique:users', 'max:255'],
            'name'      => ['required', 'max:10', 'unique:users,username,null,id,account_id,5', 'max:255'],
            'age'       => ['min:5'],
            'last_name' => ['max:255'],
        ];

        $this->assertEquals($rulesMerged, $this->getModel()->getCreateRules());
    }

    /**
     * @test
     */
    public function it_checks_all_the_attributes()
    {
        $model = $this->getModel();
        $model->getValidator()->shouldReceive('setData')->with($model->getAttributes());
        $this->assertTrue($model->isValid());
    }

    /**
     * @test
     */
    public function it_uses_rules_from_all_groups()
    {
        $model = $this->getModel();
        $this->assertEquals(['email', 'name', 'age', 'last_name'], $model::getValidatedFields());
    }

    protected function getModel()
    {
        return new ValidableEloquentStub;
    }
}

class ValidableEloquentStub extends Model {
    use Eloquence, Validable;

    protected $table = 'users';

    protected static $rules = [
        'email' => 'required|email|unique',
        'name'  => ['required', 'max:10', 'unique:users,username,null,id,account_id,5'],
    ];

    protected static $businessRules = [
        'age'   => ['min:5']
    ];

    protected static $dataRules = [
        'email'     => 'max:255',
        'name'      => ['max:255'],
        'last_name' => ['max:255'],
    ];
}
