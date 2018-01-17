<?php

namespace tyam\fadoc\Tests;

use \PHPUnit\Framework\TestCase;
use tyam\condition\Condition;
use tyam\fadoc\Converter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\PsrLogMessageProcessor;

interface Auth2 {}
class User
{
    private $id;
    private $auth;
    public function __construct(string $id, Auth2 $auth)
    {
        $this->id = $id;
        $this->auth = $auth;
    }
    public function getId(): string {return $this->id;}
    public function getAuth(): Auth2 {return $this->auth;}
}
class PwAuth implements Auth2
{
    private $pw;
    public function __construct(string $pw)
    {
        $this->pw = $pw;
    }
    public function getPw(): string {return $pw;}
}

class LoggerTest extends TestCase
{
    public function testLogger()
    {
        $logger = new Logger('fadoc.test');
        $logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        $logger->pushProcessor(new PsrLogMessageProcessor());
        $conv = new Converter([], $logger);
        $form = ['id' => '123', 
                 'auth' => [
                     '__selection' => 'PwAuth', 
                     'PwAuth' => ['pw' => '12345']
                 ]];
        $cd = $conv->objectize(['tyam\fadoc\Tests\User', '__construct'], $form);
        $this->assertTrue($cd());
    }
}