<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;

class ReflectionTest extends TestCase
{

    public function funcBasic($s, Condition $c) {}

    public function testFuncBasic() {
        $c = new \ReflectionClass($this);
        $m = $c->getMethod('funcBasic');
        $ps = $m->getParameters();
        $p0 = $ps[0];
        $p1 = $ps[1];

        // getNumberOfParameters()
        $this->assertEquals($m->getNumberOfParameters(), 2);
        // getNumberOfRequiredParameters()
        $this->assertEquals($m->getNumberOfRequiredParameters(), 2);

        // allowsNull
        $this->assertEquals($p0->allowsNull(), true);
        $this->assertEquals($p1->allowsNull(), false);
        // isDefaultValueAvailable
        $this->assertEquals($p0->isDefaultValueAvailable(), false);
        $this->assertEquals($p1->isDefaultValueAvailable(), false);
        // getDefaultValue

        // isOptional
        $this->assertEquals($p0->isOptional(), false);
        $this->assertEquals($p1->isOptional(), false);
        // isVariadic
        $this->assertEquals($p0->isVariadic(), false);
        $this->assertEquals($p1->isVariadic(), false);
        // isArray
        $this->assertEquals($p0->isArray(), false);
        $this->assertEquals($p1->isArray(), false);
    }

    public function funcDefault(string $s, Condition $c = null, int $i = 0) {}

    public function testFuncDefault() {
        $c = new \ReflectionClass($this);
        $m = $c->getMethod('funcDefault');
        $ps = $m->getParameters();
        $p0 = $ps[0];
        $p1 = $ps[1];
        $p2 = $ps[2];

        // getNumberOfParameters()
        $this->assertEquals($m->getNumberOfParameters(), 3);
        // getNumberOfRequiredParameters()
        $this->assertEquals($m->getNumberOfRequiredParameters(), 1);

        // allowsNull
        $this->assertEquals($p0->allowsNull(), false);
        $this->assertEquals($p1->allowsNull(), true);
        $this->assertEquals($p2->allowsNull(), false);
        // isDefaultValueAvailable
        $this->assertEquals($p0->isDefaultValueAvailable(), false);
        $this->assertEquals($p1->isDefaultValueAvailable(), true);
        $this->assertEquals($p2->isDefaultValueAvailable(), true);
        // getDefaultValue
        $this->assertEquals($p1->getDefaultValue(), null);
        $this->assertEquals($p2->getDefaultValue(), 0);
        // isOptional
        $this->assertEquals($p0->isOptional(), false);
        $this->assertEquals($p1->isOptional(), true);
        $this->assertEquals($p2->isOptional(), true);
        // isVariadic
        $this->assertEquals($p0->isVariadic(), false);
        $this->assertEquals($p1->isVariadic(), false);
        $this->assertEquals($p2->isVariadic(), false);
        // isArray
        $this->assertEquals($p0->isArray(), false);
        $this->assertEquals($p1->isArray(), false);
        $this->assertEquals($p2->isArray(), false);
    }
    
    public function funcVariadic(string $s, $i = 0, ...$js) {}

    public function testFuncVariadic() {
        $c = new \ReflectionClass($this);
        $m = $c->getMethod('funcVariadic');
        $ps = $m->getParameters();
        $p0 = $ps[0];
        $p1 = $ps[1];
        $p2 = $ps[2];

        // getNumberOfParameters()
        $this->assertEquals($m->getNumberOfParameters(), 3);
        // getNumberOfRequiredParameters()
        $this->assertEquals($m->getNumberOfRequiredParameters(), 1);

        // allowsNull
        $this->assertEquals($p0->allowsNull(), false);
        $this->assertEquals($p1->allowsNull(), true);
        $this->assertEquals($p2->allowsNull(), true);
        // isDefaultValueAvailable
        $this->assertEquals($p0->isDefaultValueAvailable(), false);
        $this->assertEquals($p1->isDefaultValueAvailable(), true);
        $this->assertEquals($p2->isDefaultValueAvailable(), false);
        // getDefaultValue
        $this->assertEquals($p1->getDefaultValue(), 0);
        // isOptional
        $this->assertEquals($p0->isOptional(), false);
        $this->assertEquals($p1->isOptional(), true);
        $this->assertEquals($p2->isOptional(), true);
        // isVariadic
        $this->assertEquals($p0->isVariadic(), false);
        $this->assertEquals($p1->isVariadic(), false);
        $this->assertEquals($p2->isVariadic(), true);
        // isArray
        $this->assertEquals($p0->isArray(), false);
        $this->assertEquals($p1->isArray(), false);
        $this->assertEquals($p2->isArray(), false);
    }
}