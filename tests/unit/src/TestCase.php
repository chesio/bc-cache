<?php

namespace BlueChip\Cache\Tests\Unit;

use Brain\Monkey;

class TestCase extends \PHPUnit\Framework\TestCase
{
    // See: https://github.com/Brain-WP/BrainMonkey/issues/39
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;


    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Mock some more WordPress functions
        // https://brain-wp.github.io/BrainMonkey/docs/functions-stubs.html

        // Functions that always return their first argument.
        Monkey\Functions\stubs(
            [
                '__', // https://github.com/Brain-WP/BrainMonkey/issues/25
            ]
        );

        // Functions that always return false.
        Monkey\Functions\stubs(
            [
                'is_multisite',
            ],
            false
        );
    }


    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }


    /**
     * Run private or protected static $method from $class with given $args and pass its return value.
     */
    protected function runUnaccessibleStaticMethod(string $class, string $method, array $args)
    {
        $method = (new \ReflectionClass($class))->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs(null, $args);
    }


    /**
     * Return absolute path to a $file in /data folder.
     */
    protected function getDataFilePath(string $file): string
    {
        return sprintf('%s/%s/%s', dirname(__DIR__), 'data', $file);
    }
}
