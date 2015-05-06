# Sofa/Eloquence

[![Build Status](https://travis-ci.org/jarektkaczyk/eloquence.svg?branch=0.4)](https://travis-ci.org/jarektkaczyk/eloquence) [![Coverage Status](https://coveralls.io/repos/jarektkaczyk/eloquence/badge.svg?branch=0.4)](https://coveralls.io/r/jarektkaczyk/eloquence?branch=0.4) [![Code Quality](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence/badges/quality-score.png?b=0.4)](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence) [![Downloads](https://poser.pugx.org/sofa/eloquence/downloads)](https://packagist.org/packages/sofa/eloquence) [![stable](https://poser.pugx.org/sofa/eloquence/v/stable.svg)](https://packagist.org/packages/sofa/eloquence)

Easy and flexible extensions for the Eloquent ORM.

:construction: **WIP** currently available extensions: 

1. `Validable` - self-validating models
2. `Mappable` -map attributes to table fields and/or related models
3. `Metable` - meta attributes made easy
4. `Mutable` - flexible attribute get/set mutators with quick setup
5. `Mutator` - pipe-based mutating

The package is under development and **currently doesn't follow semantic versioning**, thus **BC breaks are likely to happen**. If you are going to use it in production, then require specific version, eg. `"0.4"` instead of `"~0.4@dev"`.

If you want to know more about new extensions you can check our [Roadmap](#roadmap)!

# Table of Contents

* [Getting Started](#getting-started)
* [Validable](#validable)
  * [Easy integration with form requests](#easy-integration-with-form-requests)
  * [Less boilerplate thanks to Eloquence Model](#less-boilerplate-thanks-to-eloquence-model)
* [Mappable](#mappable)
  * [Explicit vs. Implicit mappings](#explicit-vs-implicit-mappings)
* [Metable](#metable)
* [Mutable](#mutable)
* [Mutator](#mutator)
* [Mixing extensions](#mixing-extensions)
* [Roadmap](#roadmap)
* [Contribution](#contribution)

# <a name="getting-started"></a>Getting Started

Package requires **PHP 5.4+** and works with **Laravel 5+**.


1. Require the package in your `composer.json`:
    ```
        "require": {
            ...
            "sofa/eloquence": "~0.4@dev",
            ...
        },

    ```

2. Add `Eloquence` trait to the model - it's entry point for other extensions and is required for them to work.
3. Add other traits, that you want to use.
4. Optionally add `Sofa\Eloquence\ServiceProvider` to your `config/app.php` providers array - it will register the `Mutator` as a service in the IoC Container.


# <a name="validable"></a>Validable

`Validable` is the one to grant your model super-cow powers :)

All you need to do is to define the rules, everything else will be done for you:

- validation on **creating** with provided rules.
- validation on **updating with auto-adjusted rules** for update (unique checks).
- auto-validation can be **skipped for next saving attempt** or **disabled completely** for given instance.
- rules can be defined as **one or many properties** for your convenience - see the example.
- additionally you will never have to worry about incorrect attributes set on your model (incorrect in that they are not referring to actual columns on the table, so would cause DB error) - they will be **cleaned on saving**, thanks to the `Eloquence` base trait, just implement `CleansAttributes` contract.

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Validable; // extension trait
use Sofa\Eloquence\Contracts\CleansAttributes;
use Sofa\Eloquence\Contracts\Validable as ValidableContract;

class User extends \Eloquent implements ValidableContract, CleansAttributes {

  use Eloquence, Validable;

  // You may use $rules and/or any number of $someRules, $otherRules etc
  // properties for your convenience - they will be all merged together.
  // 
  // Available rules as described in the laravel docs:
  // @link http://laravel.com/docs/5.0/validation#available-validation-rules
  protected static $businessRules = [
    'name'  => 'required|min:5', // rules passed as pipe-separated string
    'email' => ['required', 'email', 'unique:users'] // or as an array
  ];

  protected static $dataIntegrityRules = [
    'email' => 'max:255',
  ];

  // You can also pass custom attribute names and validation messages
  // just like when using Laravel Validator directly.
  protected static $validationAttributes = [
    'email' => 'EMAIL',
    'name'  => 'REAL NAME',
  ];

  protected static $validationMessages = [
    'required'       => 'Man, field :attribute must be filled!',
    'email.required' => 'Man, gimme your email?!',
  ];

}
```

Example usage:
```php
>>> User::getCreateRules()
// [
//     "email" => [
//         "required",
//         "email",
//         "unique:users",
//         "max:255"
//     ],
//     "name"  => [
//         "required",
//         "min:5",
//     ]
// ]
>>> User::getUpdateRulesForId(10)
// [
//     "email" => [
//         "required",
//         "email",
//         "unique:users,name,10,id",
//         "max:255"
//     ],
//     "name"  => [
//         "required",
//         "min:5",
//     ]
// ]
>>> User::getValidatedFields()
// [
//     "email",
//     "name"
// ]

>>> $user = new User
// <App\User #0000000018ab2ca40000000013cb0d59> {}

>>> $user->isValid()
// false

>>> $user->getValidationErrors()
// <Illuminate\Support\MessageBag #0000000018ab2c8d0000000013cb0d59> {}

>>> $user->getValidationErrors()->all()
// [
//   "Man, gimme your email?!",
//   "Man, field REAL NAME must be filled!"
// ]

>>> $user->email = 'invalid'
// "invalid"
>>> $user->name = 'foo'
// "foo"
>>> $user->save()
// false
>>> $user->getValidationErrors()->all()
// [
//   "The EMAIL must be a valid email address.",
//   "The REAL NAME must be at least 5 characters."
// ]

>>> $user->skipValidation()->save()
// true

>>> $user->getUpdateRules()
// [
//     "email" => [
//         "required",
//         "email",
//         "unique:users,name,224,id",
//         "max:255"
//     ],
//     "name"  => [
//         "required",
//         "min:5",
//     ]
// ]
```

### <a name="easy-integration-with-form-requests"></a>Easy integration with form requests

Chances are, you are using `Form Reuqests` extensively in your Laravel web app. That said, you might be afraid of the duplication of the rules in both model and corresponding form request, but worry no more.

Here's how easily you can integrate your validable model and form request:

```php
<?php namespace App\Http\Requests;

use App\Http\Requests\Request;
use App\User;

class UpdateUserRequest extends Request {

  public function authorize()
  {
    return true;
  }

  // get the rules
  public function rules()
  {
    $id = $this->users; // assuming resource controller and {users} uri param

    return User::getUpdateRulesForId($id);
  }

  // CreateUserRequest would be simply returning rules as defined in the model
  // public function rules()
  // {
  //   return User::getCreateRules();
  // }

  // pass custom messages if defined
  public function messages()
  {
    return User::getValidationMessages();
  }

  // and attributes as well
  public function attributes()
  {
    return User::getValidationAttributes();
  }
}
```

### <a name="less-boilerplate-thanks-to-eloquence-model"></a>Less boilerplate thanks to Eloquence Model

Most likely you will want to use auto validation on all your models, so instead of adding the traits and contracts manually, simply extend `Sofa\Eloquence\Model`. It provides `Validable` and `CleansAttributes` contracts implementation for you.



# <a name="mappable"></a>Mappable

Define mappings on the protected `$maps` variable like bellow. Use this extension in order to map your 1-1 relations (`BelongsTo`, `HasOne`, `MorphOne` and `MorphTo`) AND/OR simple column aliasing (eg. if you work with legacy DB with fields like `FIELD_01` or `somereallyBad_and_long_name` - inspired by [@treythomas123](https://github.com/laravel/framework/pull/8200))

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Mappable; // extension trait

class User extends \Eloquent {

    use Eloquence, Mappable;

    protected $maps = [
      // implicit relation mapping:
      'profile' => ['first_name', 'last_name'],

      // explicit relation mapping:
      'picture' => 'profile.picture_path',

      // simple alias
      'dev_friendly_name' => 'badlynamedcolumn',
    ];

    public function profile()
    {
      return $this->belongsTo(Profile::class); // *
    }
```

* `::class` is PHP5.5 constant, in PHP5.4 use full namespaced string instead.

You can also add mapped attributes to the array representation of your model, just like any accessor:

```php

    protected $maps = [
      'picture' => 'profile.picture_path'
    ];

    protected $appends = ['picture'];
```

You can get, as well as set, mapped attributes:

```php
$user->profile->first_name; // 'Jarek Tkaczyk'
$user->first_name = 'John Doe';

$user->profile->first_name; // 'John Doe'

// mapped models are saved automatically for you:
$user->save();
```

**You can also query the mappings**:

```php
// simple alias
User::where('dev_friendly_name', 'some_value')->toSql();
// select * from users where badlynamedcolumn = 'some_value'

// relation mapping
User::where('first_name', 'Romain Lanz')->toSql(); // uses whereHas
// select * from users where (
//   select count(*) from profiles
//    where users.profile_id = profiles.id and first_name = 'Romain Lanz'
// ) >= 1
 
// Order by related field
User::orderBy('first_name', 'Romain Lanz')->toSql(); // uses joins
// select users.* from users
//   left join profiles on users.profile_id = profiles.id
//   order by profiles.first_name asc
  
User::latest('users.created_at')->pluck('first_name'); // uses joins
// 'Romain Lanz'
```

Note that `MorphTo` mapping doesn't support join-based querying (`orderBy`, `pluck` and `aggregates`).

## <a name="explicit-vs-implicit-mappings"></a>Explicit vs. Implicit mappings

`Mappable` offers 2 ways of defining mappings for your convenience.

Let's compare equivalent mappings:
```php
// Assuming User belongsTo Profile
// and Profile hasOne Picture
// profiles table: id, first_name, last_name
// pictures table: id, profile_id, path


// User model
// explicit
protected $maps = [
  'first_name'   => 'profile.first_name',   // $user->first_name
  'last_name'    => 'profile.last_name',    // $user->last_name
  'picture_path' => 'profile.picture.path', // $user->picture_path
];

// implicit
protected $maps = [
  'profile'         => ['first_name', 'last_name'], // $user->first_name / ->last_name
  'profile.picture' => ['path'],                    // $user->path
];
```

As you can notice, behaviour is just the same. However, there is slight difference - explicit mapping offers more flexibility, in that you can define custom key for mapped value (`picture_path`), while with implicit mapping you have to use real attribute name defined in the related model (`path`).

Mappings work also with **form model binding**.

**Important**: Mind that each mapping call requires the relation to be loaded, so you may need to use [eager loading](http://laravel.com/docs/5.0/eloquent#eager-loading) in order to avoid n+1 query issue.

# <a name="mixing-extensions"></a>Mixing extensions

Feel free to mix the extensions, however mind that the **order of including traits matters**.

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Mappable; // extension trait
use Sofa\Eloquence\Mutable; // extension trait

class User extends \Eloquent {

    use Eloquence, 
    Mappable, Mutable; // order of these traits matters!

    protected $maps = [
      'picture' => 'profile.picture_path',
    ];

    protected $setterMutators = [
      'picture' => 'strtolower',
    ];

    public function profile()
    {
      return $this->belongsTo(Profile::class);
    }
    // ...
}
```

```php
$user = User::first();
$user->picture; // some/path/to/file.jpg
$user->picture = 'Path/To/Another/file.JPG'; // value formatted then mapped
$user->profile->picture_path; // path/to/another/file.jpg
```

# <a name="metable"></a>Metable

Use it to add meta-attributes to your model, if it has arbitrary number of attributes. For example `Hotel` or `Product`:

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Metable; // extension trait

class Hotel extends \Eloquent {

    use Eloquence, Metable;
}
```

It's just like working with normal attributes - see it in action:

```php
// Assign attributes as usually..
>>> $beachHotel = new App\Hotel
>>> $beachHotel->name = 'Sea View'
>>> $beachHotel->beach_type = 'sandy'
>>> $beachHotel->beach_distance = 50
>>> $beachHotel->aquapark = true

// ..then simply save the model
>>> $beachHotel->save()

// Now let's check it:
>>> $beachHotel = App\Hotel::latest()->first()
=> <App\Hotel #0000000057b8af2b000000000f50d29e> {
       id: 106,
       name: "Sea View",
       deleted_at: null,
       created_at: "2015-04-30 14:30:51",
       updated_at: "2015-04-30 14:30:51"
   }

// meta attributes are appended to the array representation
>>> $beachHotel->toArray()
=> [
       "id"             => 106,
       "name"           => "Sea View",
       "deleted_at"     => null,
       "created_at"     => "2015-04-30 14:30:51",
       "updated_at"     => "2015-04-30 14:30:51",
       "beach_distance" => "50",
       "beach_type"     => "sandy",
       "aquapark"       => "1"
   ]


>>> $skiHotel = new App\Hotel
>>> $skiHotel->name = 'Alexander'
>>> $skiHotel->lifts_distance = 300
>>> $skiHotel->sauna = true
>>> $skiHotel->gym = true
>>> $skiHotel->activities = ['trekking', 'cross-country', 'husky-village']
>>> $skiHotel->garage = 'yes, 10EUR/day'
>>> $skiHotel->save()
>>> $skiHotel = Product::latest()->first()
=> <App\Hotel #0000000057b8af54000000000f50d29e> {
       id: 107,
       name: "Alexander",
       deleted_at: null,
       created_at: "2015-04-30 14:39:20",
       updated_at: "2015-04-30 14:39:20"
   }
>>> $skiHotel->toArray()
=> [
      "id"             => 107,
      "name"           => "Alexander",
      "deleted_at"     => null,
      "created_at"     => "2015-04-30 14:39:20",
      "updated_at"     => "2015-04-30 14:39:20",
      "lifts_distance" => "300",
      "sauna"          => "1",
      "gym"            => "1",
      "activities"     => "[\"trekking\",\"cross-country\",\"husky-village\"]",
      "garage"         => "yes, 10EUR/day"
   ]


// You can fill meta attributes as well (fillable/guarded properties still apply)
>>> $shirt = new App\Product(['name' => 'Hipster T-shirt', 'price' => 125, 'color'=> 'green-white'])
// name and color are fillable, but price is not, so it doesn't get filled:
=> <App\Product #000000006a37bcb20000000031dd9b05> {
       name: "Hipster T-shirt",
       metaAttributes: <Sofa\Eloquence\Metable\AttributeBag #000000006a37bc890000000031dd9b05> [
           "color" => <Sofa\Eloquence\Metable\Attribute #000000006a37bc8d0000000031dd9b05> {
               meta_key: "color",
               meta_type: "string",
               meta_value: "green-white"
           }
       ]
   }


// Values are stored as strings and automatically cast/mutated for you:
>>> $shirt->available_from = Carbon::today()->addMonth();
>>> $shirt->price = 125.5
>>> $shirt->save()

>>> $shirt = App\Product::latest()->first()
>>> $shirt->price
=> 125.5 // float
>>> $shirt->available_from
=> <Carbon\Carbon #000000006a37bcb50000000031dd8a65> {
       date: "2015-05-30 00:00:00.000000",
       timezone_type: 3,
       timezone: "Europe/Warsaw"
   }



// You can also query meta attributes:
$hotels = App\Hotel::orderBy('beach_distance', 'asc')->take(10)->get();
$closestToTheBeach = App\Hotel::min('beach_distance');
$hotelsWithSauna = App\Hotel::whereNotNull('sauna')->get();
```

# <a name="mutable"></a>Mutable
  docs soon...

# <a name="mutator"></a>Mutator
  docs soon...

# <a name="roadmap"></a>Roadmap
- [x] Easy model validation.

...and much more to come soon!

# <a name="contribution"></a>Contribution

All contributions are welcome, PRs must be **tested** and **PSR-2 compliant**.

Thanks to [contributors](https://github.com/jarektkaczyk/eloquence/graphs/contributors).
