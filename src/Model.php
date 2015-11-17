<?php

namespace Sofa\Eloquence;

use Sofa\Eloquence\Contracts\CleansAttributes;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Sofa\Eloquence\Contracts\Validable as ValidableContract;

class Model extends Eloquent implements CleansAttributes, ValidableContract
{
    use Eloquence, Validable;
}
