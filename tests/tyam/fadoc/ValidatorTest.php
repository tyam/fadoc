<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

class Query {
    private $keyword, $amount;
    public function __construct(string $keyword, int $amount = 20) {
        $this->keyword = $keyword;
        $this->amount = $amount;
    }
    public static function validateAmount(int $amount) {
        if ($amount > 0 && $amount <= 500) {
            return Condition::fine($amount);
        } else {
            return Condition::poor('invalid');
        }
    }
    public function getKeyword() {return $this->keyword;}
    public function getAmount() {return $this->amount;}
}
class ValidatorRunner {
    public static function requireQuery(Query $q, string $mode = 'array') {}
    public static function validateModeForRequireQuery(string $mode) {
        if ($mode == 'array' || $mode == 'class') {
            return Condition::fine($mode);
        } else {
            return Condition::poor('invalid');
        }
    }
}

class ValidatorTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();

        $form0 = [0 => ['keyword' => 'invest', 'amount' => '100'], 1 => 'class'];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\ValidatorRunner', 'requireQuery'], $form0);
        $this->assertTrue($cd0());
        $args0 = $cd0->get();
        $this->assertEquals($args0[0]->getKeyword(), 'invest');
        $this->assertEquals($args0[0]->getAmount(), 100);
        $this->assertEquals($args0[1], 'class');

        $form1 = [0 => ['keyword' => 'fadoc', 'amount' => '1000'], 1 => 'assoc'];
        $cd1 = $c->objectize(['tyam\fadoc\Tests\ValidatorRunner', 'requireQuery'], $form1);
        $this->assertFalse($cd1());
        $es = $cd1->describe();
        $this->assertEquals($es[0]['amount'], 'invalid');
        $this->assertEquals($es[1], 'invalid');
    }

    public function testValidate0() {
        $c = new Converter();
        
        $form0 = ['q' => ['amount' => '100']];
        $cd0 = $c->validate(['tyam\fadoc\Tests\ValidatorRunner', 'requireQuery'], $form0);
        $this->assertTrue($cd0());

        $form1 = ['mode' => 'assoc'];
        $cd1 = $c->validate(['tyam\fadoc\Tests\ValidatorRunner', 'requireQuery'], $form1);
        $this->assertFalse($cd1());
    }
}