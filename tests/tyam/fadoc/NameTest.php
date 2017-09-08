<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;


class Sex {
    private $value;
    public function __construct(string $value) {
        $this->value = $value;
    }
    public function getValue() {return $this->value;}
}
class Person {
    private $name;
    private $age;
    private $sex;
    public function __construct(string $name, int $age, Sex $sex) {
        $this->name = $name;
        $this->age = $age;
        $this->sex = $sex;
    }
    public function getName() {return $this->name;}
    public function getAge() {return $this->age;}
    public function getSex() {return $this->sex;}
}
class NameRunner {
    public static function run(Person $p) {}
}

class NameTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();
        $form = ['p' => ['age' => '18', 'name' => 'Joh', 'sex' => ['value' => 'male']]];
        $cd = $c->objectize(['\tyam\fadoc\Tests\NameRunner', 'run'], $form);
        $this->assertEquals($cd(), true);
        $args = $cd->get();
        $this->assertEquals(count($args), 1);
        $this->assertEquals($args[0]->getName(), 'Joh');
        $this->assertEquals($args[0]->getSex()->getValue(), 'male');
    }

    public function testObjectize1() {
        $c = new Converter();
        $form = ['p' => ['age' => '18', /* no name */ 'sex' => ['value' => 'male']]];
        $cd = $c->objectize(['\tyam\fadoc\Tests\NameRunner', 'run'], $form);
        $this->assertEquals($cd(), false);
        $es = $cd->describe();
        $this->assertEquals($es['p']['name'], 'required');
        $this->assertEquals($es['p'][0], 'required');
        $this->assertEquals(empty($es['p']['age']), true);
        $this->assertEquals(empty($es['p'][1]), true);
    }

    public function testValidate0() {
        $c = new Converter();

        $form0 = ['p' => ['age' => '18']];
        $cd0 = $c->validate(['\tyam\fadoc\Tests\NameRunner', 'run'], $form0);
        $this->assertEquals($cd0(), true);

        $form1 = [0 => [2 => [0 => 'male']]];
        $cd1 = $c->validate(['\tyam\fadoc\Tests\NameRunner', 'run'], $form1);
        $this->assertEquals($cd1(), true);

        $form2 = [0 => [1 => 'notInt']];
        $cd2 = $c->validate(['\tyam\fadoc\Tests\NameRunner', 'run'], $form2);
        $this->assertFalse($cd2());
        $this->assertEquals($cd2->describe(), 'invalid');

        $form4 = [0 => [1 => '']];
        $cd4 = $c->validate(['\tyam\fadoc\Tests\NameRunner', 'run'], $form4);
        $this->assertFalse($cd4());
        $this->assertEquals($cd4->describe(), 'required');
    }

    public function testFormulize0() {
        $c = new Converter();

        $p0 = new Person('John', 34, new Sex('male'));
        $f0 = $c->formulize(['\tyam\fadoc\Tests\NameRunner', 'run'], [$p0]);
        $this->assertEquals(count($f0), 1+1);
        $this->assertEquals($f0['p']['name'], 'John');
        $this->assertEquals($f0['p']['sex']['value'], 'male');
    }
}