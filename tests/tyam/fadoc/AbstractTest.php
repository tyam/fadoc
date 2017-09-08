<?php
namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;

interface Auth {}
class IpAuth implements Auth {
    private $ip;
    public function __construct(string $ip) {
        $this->ip = $ip;
    }
    public function getIp() {return $this->ip;}
}
class SessionAuth implements Auth {
    private $secret;
    public function __construct(string $secret) {
        $this->secret = $secret;
    }
    public function getSecret() {return $this->secret;}
}
class AbstractRunner {
    public function run(Auth $auth) {}
}

class AbstractTest extends TestCase {
    public function testObjectize0() {
        $c = new Converter();

        $form0 = [0 => ['__selection' => 'SessionAuth', 
                        'SessionAuth' => [0 => 'abrakadabra'], 
                        'IpAuth' => [0 => '192.168.100.1']]];
        $cd0 = $c->objectize(['tyam\fadoc\Tests\AbstractRunner', 'run'], $form0);
        $this->assertTrue($cd0());
        $auth = $cd0->get()[0];
        $this->assertEquals(get_class($auth), 'tyam\fadoc\Tests\SessionAuth');

        $form1 = [0 => ['__selection' => 'HeaderAuth', 
                        'SessionAuth' => [0 => 'abrakadabra'], 
                        'IpAuth' => [0 => '192.168.100.1']]];
        $cd1 = $c->objectize(['tyam\fadoc\Tests\AbstractRunner', 'run'], $form1);
        $this->assertFalse($cd1());
        $es = $cd1->describe();
        $this->assertEquals($es[0]['__selection'], 'invalid');
    }

    public function testValidate0() {
        $c = new Converter();

        $form0 = [0 => ['SessionAuth' => [0 => 'abc']]];
        $cd0 = $c->validate(['tyam\fadoc\Tests\AbstractRunner', 'run'], $form0);
        $this->assertTrue($cd0());

        $form1 = [0 => ['HeaderAuth' => [0 => 'abrakadabra']]];
        $cd1 = $c->validate(['tyam\fadoc\Tests\AbstractRunner', 'run'], $form1);
        $this->assertFalse($cd1());
    }

    public function testFormulize0() {
        $c = new Converter();

        $v0 = [new SessionAuth('abc')];
        $f0 = $c->formulize(['tyam\fadoc\Tests\AbstractRunner', 'run'], $v0);
        $this->assertEquals($f0[0]['__selection'], 'SessionAuth');
        $this->assertEquals($f0['auth']['SessionAuth']['secret'], 'abc');
        $this->assertEquals($f0[0]['SessionAuth'][0], 'abc');
    }
}