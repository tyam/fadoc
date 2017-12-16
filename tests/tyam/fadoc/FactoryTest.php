<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class FooBar {
    private $foo, $bar;
    private function __construct(int $foo, bool $bar) {
        $this->foo = $foo;
        $this->bar = $bar;
    }
    public static function instantiate(int $foo, bool $bar, bool $mutate) {
        if ($mutate) {
            return new FooBar($foo + 1, !$bar);
        } else {
            return new FooBar($foo, $bar);
        }
    }
    public function getFoo() {return $this->foo;}
    public function getBar() {return $this->bar;}
    public static function extractForInstantiate($obj) {
        return ['foo' => $obj->getFoo(), 
                0 => $obj->getFoo(), 
                'bar' => $obj->getBar(), 
                1 => $obj->getBar(), 
                'mutate' => 'false', 
                2 => 'false'];
    }
}
class Factory {
    public static function getFooBar(bool $bar, int $foo) {
        return FooBar::instantiate($foo, $bar, false);
    }
    public static function extractForGetFooBar($obj) {
        return ['foo' => $obj->getFoo(), 
                1 => $obj->getFoo(), 
                'bar' => $obj->getBar(), 
                0 => $obj->getBar()];
    }
}
class FactoryRunner {
    public function requireFooBar(FooBar $s, int $i) {}
}

class FactoryTest extends TestCase {
    public function testObjectize0() {
        $ctrmap0 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\FooBar', 'instantiate']];
        $c0 = new Converter($ctrmap0);
        $form0 = [0 => ['foo' => '10', 'bar' => 'false', 'mutate' => 'true'], 1 => '2'];
        $cd0 = $c0->objectize(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $form0);
        $this->assertTrue($cd0());
        $s0 = $cd0->get()[0];
        $this->assertEquals($s0->getFoo(), 11);
        $this->assertEquals($s0->getBar(), true);

        $ctrmap1 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\Factory', 'getFooBar']];
        $c1 = new Converter($ctrmap1);
        $form1 = [0 => [0 => 'true', 1 => '7'], 1 => '-5'];
        $cd1 = $c1->objectize(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $form1);
        $this->assertTrue($cd1());
        $s1 = $cd1->get()[0];
        $this->assertEquals($s1->getFoo(), 7);
        $this->assertEquals($s1->getBar(), true);
    }

    public function testValidate0() {
        $ctrmap0 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\FooBar', 'instantiate']];
        $c0 = new Converter($ctrmap0);
        $form0 = [0 => ['bar' => 'false']];
        $cd0 = $c0->validate(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $form0);
        $this->assertTrue($cd0());
        $form01 = [1 => 'NaN'];
        $cd01 = $c0->validate(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $form01);
        $this->assertFalse($cd01());
        $this->assertEquals($cd01->describe(), 'invalid');

        $ctrmap1 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\Factory', 'getFooBar']];
        $c1 = new Converter($ctrmap1);
        $form1 = ['s' => [1 => '10']];
        $cd1 = $c1->validate(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $form1);
        $this->assertTrue($cd1());
    }

    public function testFormulize0() {
        $ctrmap0 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\FooBar', 'instantiate']];
        $c0 = new Converter($ctrmap0);
        $v0 = [FooBar::instantiate(10, true, false), 5];
        $f0 = $c0->formulize(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $v0);
        $this->assertEquals($f0[0][0], '10');
        $this->assertEquals($f0[0][1], 'true');
        $this->assertEquals($f0[0]['mutate'], 'false');

        $ctrmap1 = ['tyam\fadoc\Tests\FooBar' => ['tyam\fadoc\Tests\Factory', 'getFooBar']];
        $c1 = new Converter($ctrmap1);
        $v1 = [Factory::getFooBar(false, 20), 2];
        $f1 = $c1->formulize(['tyam\fadoc\Tests\FactoryRunner', 'requireFooBar'], $v1);
        $this->assertEquals($f1['s']['bar'], 'false');
        $this->assertEquals($f1['s']['foo'], '20');
        $this->assertTrue(empty($f1['s']['mutate']));
    }
}