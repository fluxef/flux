<?php
declare(strict_types=1);

namespace Logger;

use Flux\Logger\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class LoggerTest extends TestCase
{
    public function testLoggerClass(): void
    {
        $logger = new Logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testescapeSDValue_withPlainString(): void
    {
        $input = "JustATestTotallyPlain";
        $output = "JustATestTotallyPlain";
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }

    public function testescapeSDValue_withStringBackslash(): void
    {
        $input = 'JustABackslash'.chr(92).'Test';
        $output = 'JustABackslash'.chr(92).chr(92).'Test';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }

    public function testescapeSDValue_withStringClosingBracket(): void
    {
        $input = 'JustABackslash]Test';
        $output = 'JustABackslash'.chr(92).']Test';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }
    public function testescapeSDValue_withStringQuotationMark(): void
    {
        $input = 'JustABackslash"Test';
        $output = 'JustABackslash'.chr(92).'"Test';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }
    public function testescapeSDValue_Null(): void
    {
        $input = null;
        $output = '';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }

    public function testescapeSDValue_int(): void
    {
        $input = 4711;
        $output = '4711';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }

    public function testescapeSDValue_booltrue(): void
    {
        $input = true;
        $output = '1';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }
    public function testescapeSDValue_boolfalse(): void
    {
        $input = false;
        $output = '0';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }
    public function testescapeSDValue_float(): void
    {
        $input = 47.123;
        $output = '47.123';
        $logger = new Logger();
        $reflection = new ReflectionClass($logger);
        $method = $reflection->getMethod('escapeSDValue');
        $result = $method->invokeArgs($logger, array($input));

        $this->assertEquals($output, $result);

    }

}
