<?php

namespace Sofa\Eloquence\Tests;

use Mockery;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        $this->addToAssertionCount(Mockery::getContainer()->mockery_getExpectationCount());
        Mockery::close();
    }
}
