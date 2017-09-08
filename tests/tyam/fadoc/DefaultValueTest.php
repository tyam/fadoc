<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class Point {
    private $x, $y;
    public function __construct(int $x = 0, int $y = 0) {
        $this->x = $x; $this->y = $y;
    }
    public function getX() {return $this->x;}
    public function getY() {return $this->y;}
}
class Circle {
    private $p, $r;
    public function __construct(Point $p, int $r = 1) {
         $this->p = $p; $this->r = $r;
    }
    public function getP() {return $this->p;}
    public function getR() {return $this->r;}
}
class Line {
    private $p, $q;
    public function __construct(Point $p, Point $q) {
        $this->p = $p; $this->q = $q;
    }
    public function getP() {return $this->p;}
    public function getQ() {return $this->q;}
}
class DefaultValueRunner {
    public function run(Circle $c, Line $l, Point $p = null) {}
}

class DefaultValueTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();
        $form = ['c' => ['p' => ['x' => 5], 'r' => 2], 
                 'l' => ['p' => [], 'q' => ['x' => -3, 'y' => 8]]
                 ];
        $cd = $c->objectize(['\tyam\fadoc\Tests\DefaultValueRunner', 'run'], $form);
        $this->assertEquals($cd(), true);
        $args = $cd->get();
        $this->assertEquals($args[0]->getR(), 2);
        $this->assertEquals($args[0]->getP()->getX(), 5);
        $this->assertEquals($args[0]->getP()->getY(), 0);
        $this->assertEquals($args[1]->getP()->getX(), 0);
        $this->assertEquals($args[1]->getQ()->getY(), 8);
        $this->assertEquals($args[2], null);
    }
    
    public function testObjectize1() {
        $c = new Converter();
        $form = ['c' => ['p' => ['x' => 5], 'r' => 2], 
                 'l' => ['p' => [], 'q' => ['x' => -3, 'y' => 8]], 
                 'p' => ['x' => 1, 'y' => 6]];
        $cd = $c->objectize(['\tyam\fadoc\Tests\DefaultValueRunner', 'run'], $form);
        $this->assertEquals($cd(), true);
        $args = $cd->get();
        $this->assertEquals($args[0]->getR(), 2);
        $this->assertEquals($args[0]->getP()->getX(), 5);
        $this->assertEquals($args[0]->getP()->getY(), 0);
        $this->assertEquals($args[1]->getP()->getX(), 0);
        $this->assertEquals($args[1]->getQ()->getY(), 8);
        $this->assertEquals($args[2]->getX(), 1);
    }

    public function testValidate0() {
        $c = new Converter();
        $form = ['c' => [1 => '7']];
        $cd = $c->validate(['\tyam\fadoc\Tests\DefaultValueRunner', 'run'], $form);
        $this->assertTrue($cd());
    }
}

