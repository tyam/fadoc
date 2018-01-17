<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;


class Arg0 {
    public function __construct() {}
}
class Arg0Woc { // Arg 0 w/o constructor
}
class Arg1 {
    private $val0;
    public function __construct(Arg0 $val0) {
        $this->val0 = $val0;
    }
    public function getVal0() {return $this->val0;}
}
class Arg2 {
    private $val0, $val1;
    public function __construct(Arg0 $val0, Arg1 $val1) {
        $this->val0 = $val0;
        $this->val1 = $val1;
    }
    public function getVal0() {return $this->val0;}
    public function getVal1() {return $this->val1;}
}
class FormatRunner {
    public static function run(Arg2 $val0, Arg1 $val1, Arg0 $val2) {}
    public static function run2(Arg0Woc $val0) {}
}

class FormatTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();
        $form = [
            0 => [ // Arg2
                0 => [],  // Arg0
                1 => [  // Arg1
                    0 => []  // Arg0
                ]
            ], 
            1 => [0 => []], 
            2 => []
        ];
        $cd = $c->objectize(['\tyam\fadoc\Tests\FormatRunner', 'run'], $form);
        $this->assertEquals($cd(), true);
        $args = $cd->get();
        $this->assertEquals(is_array($args), true);
        $this->assertEquals(get_class($args[0]), 'tyam\fadoc\Tests\Arg2');
        $this->assertEquals(get_class($args[0]->getVal1()->getVal0()), 'tyam\fadoc\Tests\Arg0');
        $this->assertEquals(get_class($args[2]), 'tyam\fadoc\Tests\Arg0');
    }

    public function testObjectize1() {
        $c = new Converter();
        $form = [
            0 => [ // Arg2
                0 => []  // Arg0
                // the second args is absent.
            ], 
            1 => [0 => []], 
            2 => []
        ];
        $cd = $c->objectize(['\tyam\fadoc\Tests\FormatRunner', 'run'], $form);
        $this->assertEquals($cd(), false);
        $es = $cd->describe();
        $this->assertEquals($es[0][1], 'arrayRequired');
        $this->assertEquals(empty($es[1]), true);
        $this->assertEquals(empty($es[0][0]), true);
    }

    public function testObjectize2()
    {
        $c = new Converter();
        $form = [0 => []];
        $cd = $c->objectize(['\tyam\fadoc\Tests\FormatRunner', 'run2'], $form);
        $this->assertTrue($cd());
    }

    public function testFormulize0() {
        $c = new Converter();

        $arg0 = new Arg0();
        $arg1 = new Arg1($arg0);
        $arg2 = new Arg2($arg0, $arg1);
        $form = $c->formulize(['\tyam\fadoc\Tests\FormatRunner', 'run'], [$arg2, $arg1, $arg0]);
        $this->assertEquals(count($form), 3+3);
        $this->assertTrue(is_array($form[0]));
        $this->assertTrue(is_array($form[2]));
        $this->assertEquals(count($form[2]), 0);
    }

    public function testFormulize2()
    {
        $c = new Converter();
        $a0 = new Arg0Woc();
        $form = $c->formulize(['\tyam\fadoc\Tests\FormatRunner', 'run2'], [$a0]);
        $this->assertTrue(is_array($form));
        $this->assertEquals(count($form), 1+1); // $form[0] and $form['val0']
        $this->assertTrue(is_array($form[0]));
        $this->assertEquals(count($form[0]), 0);
    }
}