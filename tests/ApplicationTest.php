<?php

namespace Caphpe\Tests;

use Caphpe\Application;
use Caphpe\Cli\Arguments;

class ApplicationTest extends \PHPUnit_Framework_TestCase
{
    function testItCanBeInstantiated ()
    {
        $app = new Application(new Arguments([]));

        $this->assertInstanceOf(Application::class, $app);
    }
}
