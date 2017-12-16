<?php

namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class LenientRunner
{
    public function run(int $p0, bool $p1) {}
}

class LenientTest extends TestCase
{
    public function testLenient0()
    {
        $c = new Converter([]);
        $form = [0 => '3', 1 => 'T'];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\LenientRunner', 'run'], $form, Converter::LENIENT);
        $this->assertTrue($cd0());
        $cd1 = $c->objectize(['tyam\fadoc\Tests\DomainRunner', 'run'], $form);
        $this->assertFalse($cd1());
    }

    public function testLenient1()
    {
        $c = new Converter([]);
        $form = [0 => '3.4', 1 => 'true'];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\LenientRunner', 'run'], $form, Converter::LENIENT);
        $this->assertTrue($cd0());
        $cd1 = $c->objectize(['tyam\fadoc\Tests\DomainRunner', 'run'], $form);
        $this->assertFalse($cd1());
    }
}