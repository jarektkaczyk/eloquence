# Sofa/Eloquence

(WIP currently only `Mappable` - inspired by https://github.com/RomainLanz/laravel-attributes-mapper, much more to come soon!)

Useful extensions for the Eloquent ORM.

## Requirements

* This package requires PHP 5.4+

## Usage

### 1. Require the package in your `composer.json`:

```
    "require": {
        ...
        "sofa/eloquence": "~0.1@dev",
        ...
    },

```

### 2. Add trait to the model and define mappings:

```
<?php namespace App;

use Sofa\Eloquence\Mappable; // trait

class User extends \Eloquent {

    use Mappable;

    protected $maps = [
      // implicit mapping:
      'profile' => ['first_name', 'last_name'],

      // explicit mapping:
      'picture' => 'profile.piture_path'
    ];

    public function profile()
    {
      return $this->belongsTo(Profile::class);
    }
```

### You can also add mapped attributes to the array representation of your model, just like any accessor:

```
<?php namespace App;

use Sofa\Eloquence\Mappable; // trait

class User extends \Eloquent {

    use Mappable;

    protected $maps = [
      'picture' => 'profile.piture_path'
    ];

    protected $appends = ['picture'];
}
```

### You can get as well as set mapped attributes:

```
$user->profile->first_name; // 'Jarek Tkaczyk'
$user->first_name = 'John Doe';

$user->profile->first_name; // 'John Doe'

// remember to save related model in order to save the changes:
$user->profile->save();
// or simply use push:
$user->push();
```


## Explicit vs. Implicit mappings

`Mappable` offers 2 ways of defining mappings for your convenience.

Let's compare equivalent mappings:
```
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

As you notice behaviour is just the same. However there is slight difference - explicit mapping offers more flexibility, in that you can define custom key for mapped value (`picture_path`), while with implicit mapping you have to use real attribute name defined in the related model (`path`).
