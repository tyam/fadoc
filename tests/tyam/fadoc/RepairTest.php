<?php

namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class UserId
{
    public function __construct(int $value) {}
}
class DomainRunner
{
    public function run(UserId $userId, bool $something) {}
}

class RepairTest extends TestCase
{
    public function testRepair0()
    {
        $c = new Converter([]);
        $form = [0 => '3', 1 => 'true'];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\DomainRunner', 'run'], $form, Converter::REPAIR);
        $this->assertTrue($cd0());
        $cd1 = $c->objectize(['tyam\fadoc\Tests\DomainRunner', 'run'], $form);
        $this->assertFalse($cd1());
    }
}