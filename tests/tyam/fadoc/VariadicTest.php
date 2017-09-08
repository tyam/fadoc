<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class Variadic {
    private $a;
    public function __construct(...$a) {
        $this->a = $a;
    }
    public function getA() {return $this->a;}
}
class VariadicRunner {
    public static function run0(int $i, Variadic $v) {}
    public static function runf(string $fmt, ...$args) {}
}

class VariadicTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();

        $form0 = ['i' => 7, 'v' => []];
        $cd0 = $c->objectize(['\tyam\fadoc\Tests\VariadicRunner', 'run0'], $form0);
        $this->assertTrue($cd0());
        $args0 = $cd0->get();
        $this->assertEquals(count($args0[1]->getA()), 0);

        $form1 = ['i' => 7, 'v' => [0 => 0, 1 => 1, 2 => 2]];
        $cd1 = $c->objectize(['\tyam\fadoc\Tests\VariadicRunner', 'run0'], $form1);
        $this->assertTrue($cd1());
        $args1 = $cd1->get();
        $this->assertEquals(count($args1[1]->getA()), 3);
        $this->assertEquals($args1[1]->getA()[2], 2);
    }

    public function testObjectize1() {
        $c = new Converter();

        $form = ['fmt' => '%d %s found... deletion: %s', 1 => '10', 2 => 'item', 3 => 'success'];
        $cd = $c->objectize(['\tyam\fadoc\Tests\VariadicRunner', 'runf'], $form);
        $this->assertTrue($cd());
        $args = $cd->get();
        $this->assertEquals(count($args), 4);
        $this->assertEquals($args[1], '10');
        $this->assertEquals($args[3], 'success');
    }

    public function testValidate0() {
        $c = new Converter();

        $form0 = [1 => [5 => 'abc']];
        $cd0 = $c->validate(['\tyam\fadoc\Tests\VariadicRunner', 'run0'], $form0);
        $this->assertTrue($cd0());

        $form1 = [5 => 'abc'];
        $cd1 = $c->validate(['\tyam\fadoc\Tests\VariadicRunner', 'runf'], $form1);
        $this->assertTrue($cd1());
    }

    public function testFormulize0() {
        $c = new Converter();

        $v = new Variadic(0, 1, 2, 3);
        $f = $c->formulize(['\tyam\fadoc\Tests\VariadicRunner', 'run0'], [4, $v]);
        $this->assertEquals($f[0], '4');
        $this->assertEquals($f['i'], '4');
        $this->assertEquals($f[1][1], '1');
        $this->assertEquals($f['v'][3], '3');

        $v2 = ['x:%d y:%d w:%d h:%d', 100, 30, 250, 200];
        $f2 = $c->formulize(['\tyam\fadoc\Tests\VariadicRunner', 'runf'], $v2);
        $this->assertEquals($f2[1], '100');
        $this->assertTrue(empty($f2['args']));
        $this->assertEquals($f2[4], '200');
    }
}