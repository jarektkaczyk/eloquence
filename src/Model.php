<?php namespace Sofa\Eloquence;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Sofa\Eloquence\Contracts\Validable as ValidableContract;
use Sofa\Eloquence\Contracts\CleansAttributes;

class Model extends Eloquent implements CleansAttributes, ValidableContract
{
    use Eloquence, Validable;
}
