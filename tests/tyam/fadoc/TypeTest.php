<?php

namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

class Something 
{
    public function foo(int $x, bool $y) {}
}

class TypeTest extends TestCase
{
    public function testObjectize()
    {
        $logger = new Logger('fadoc.test');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $c = new Converter([], $logger);
        $json = ['x' => 1, 'y' => true];
        $cd = $c->objectize(['tyam\fadoc\tests\Something', 'foo'], $json);
        $this->assertTrue($cd());
    }
}