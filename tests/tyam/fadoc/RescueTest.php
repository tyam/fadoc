<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class Arity0 {
    public function __construct() {}
}
class RescueRunner {
    public function requireArray(Arity0 $ari, array $arr) {}
    public function requireBool(bool $b) {}
}

class RescueTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();
        
        $form0 = [];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\RescueRunner', 'requireArray'], $form0);
        $this->assertTrue($cd0());
        $args0 = $cd0->get();
        $this->assertEquals(get_class($args0[0]), 'tyam\fadoc\Tests\Arity0');
        $this->assertTrue(is_array($args0[1]));
        $this->assertEquals(count($args0[1]), 0);

        $form1 = [];
        $cd1 = $c->objectize(['tyam\fadoc\Tests\RescueRunner', 'requireBool'], $form1);
        $this->assertTrue($cd1());
        $args1 = $cd1->get();
        $this->assertEquals($args1[0], false);
    }

    public function testValidate0() {
        $c = new Converter();

        $form1 = [1 => [3 => 'something']];
        $cd1 = $c->validate(['tyam\fadoc\Tests\RescueRunner', 'requireArray'], $form1);
        $this->assertTrue($cd1());

        $form2 = [0 => 'true'];
        $cd2 = $c->validate(['tyam\fadoc\Tests\RescueRunner', 'requireBool'], $form2);
        $this->assertTrue($cd2());
    }
}