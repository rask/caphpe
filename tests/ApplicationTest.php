<?php declare(strict_types = 1);

namespace Caphpe\Tests;

use Caphpe\Application;
use Caphpe\Cli\Arguments;
use PHPUnit\Framework\TestCase;

/**
 * Class ApplicationTest
 *
 * @package Caphpe\Tests
 */
class ApplicationTest extends TestCase
{
    /**
     *
     */
    public function test_it_can_be_instantiated()
    {
        $app = new Application(new Arguments([]));

        $this->assertInstanceOf(Application::class, $app);
    }
}
