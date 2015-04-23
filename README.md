# Sofa/Eloquence

[![Build Status](https://travis-ci.org/jarektkaczyk/eloquence.svg?branch=0.4)](https://travis-ci.org/jarektkaczyk/eloquence) [![Coverage Status](https://coveralls.io/repos/jarektkaczyk/eloquence/badge.svg?branch=0.4)](https://coveralls.io/r/jarektkaczyk/eloquence?branch=0.4) [![Code Quality](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence/badges/quality-score.png?b=0.4)](https://scrutinizer-ci.com/g/jarektkaczyk/eloquence) [![Downloads](https://poser.pugx.org/sofa/eloquence/downloads.svg)](https://packagist.org/packages/sofa/eloquence)

Easy and flexible extensions for the Eloquent ORM.

:construction: **WIP** currently available extensions: 

1. `Metable` - meta attributes made easy
2. `Mappable` -map attributes to table fields and/or related models
3. `Mutable` - flexible attribute get/set mutators with quick setup

The package is under development and **currently doesn't follow semantic versioning**, thus **BC breaks are likely to happen**. If you are going to use it in production, then require specific version, eg. `"0.3"` instead of `"~0.3@dev"`.

If you want to know more about new extensions you can check our [Roadmap](#roadmap)!

# Table of Contents

* [Team Members](#team-members)
* [Requirements](#requirements)
* [Getting Started](#getting-started)
* [Mappable](#mappable)
  * [Explicit vs. Implicit mappings](#explicit-vs-implicit-mappings)
* [Mixing extensions](#mix)
* [Roadmap](#roadmap)

# <a name="team-members"></a>Team Members

* Jarek Tkaczyk ([SOFTonSOFA](http://softonsofa.com/)) <jarek@softonsofa.com>
* Romain Lanz (https://github.com/RomainLanz) <lanz.romain@gmail.com>

# <a name="requirements"></a>Requirements

* This package requires PHP 5.4+

# <a name="getting-started"></a>Getting Started

1. Require the package in your `composer.json`:
    ```
        "require": {
            ...
            "sofa/eloquence": "~0.3@dev",
            ...
        },

    ```

2. Add `Eloquence` trait to the model - it's entry point for other extensions and is required for them to work.
3. Add other traits, that you want to use.

# <a name="mappable"></a>Mappable

Define mappings on the protected `$maps` variable like bellow. Use this extension in order to map your 1-1 relations AND/OR simple column aliasing (eg. if you work with legacy DB with fields like `FIELD_01` or `somereallyBad_and_long_name` - inspired by [@treythomas123](https://github.com/laravel/framework/pull/8200))

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Mappable; // extension trait
use Sofa\Eloquence\Contracts\Mappable as MappableContract; // interface

class User extends \Eloquent implements MappableContract {

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
      return $this->belongsTo(Profile::class);
    }
```

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

// remember to save related model in order to persist the changes:
$user->profile->save();
// or simply use push:
$user->push();
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
```


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

# <a name="mix"></a>Mixing extensions

Feel free to mix the extensions, however mind that the **order of including traits matters**.

```php
<?php namespace App;

use Sofa\Eloquence\Eloquence; // base trait
use Sofa\Eloquence\Mappable; // extension trait
use Sofa\Eloquence\Formattable; // extension trait
use Sofa\Eloquence\Contracts\Mappable as MappableContract; // interface

class User extends \Eloquent implements MappableContract {

    use Eloquence, 
    Mappable, Formattable; // order of these traits matters!

    protected $maps = [
      'picture' => 'profile.picture_path',
    ];

    protected $formats = [
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


# <a name="roadmap"></a>Roadmap
- [ ] Set relations on an array. (e.g. `protected $relations = ['profile' => 'has_one'];`)

...and much more to come soon!
