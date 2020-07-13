<?php

declare(strict_types=1);

/**
 * RQuadling/AbstractConsole
 *
 * LICENSE
 *
 * This is free and unencumbered software released into the public domain.
 *
 * Anyone is free to copy, modify, publish, use, compile, sell, or distribute this software, either in source code form or
 * as a compiled binary, for any purpose, commercial or non-commercial, and by any means.
 *
 * In jurisdictions that recognize copyright laws, the author or authors of this software dedicate any and all copyright
 * interest in the software to the public domain. We make this dedication for the benefit of the public at large and to the
 * detriment of our heirs and successors. We intend this dedication to be an overt act of relinquishment in perpetuity of
 * all present and future rights to this software under copyright law.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS BE
 * LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT
 * OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * For more information, please refer to <https://unlicense.org>
 *
 */

namespace RQuadlingTests\Console;

use PHPUnit\Framework\TestCase;
use RQuadling\DependencyInjection\ContainerFactory;
use RQuadlingTests\Console\Fixtures\Application\BadApplication;
use RQuadlingTests\Console\Fixtures\Application\GoodApplication;
use RQuadlingTests\Console\Fixtures\Application\NoCommandsDirectoryApplication;
use RQuadlingTests\Console\Fixtures\Application\NoCommandsNamespaceApplication;
use RQuadlingTests\Console\Fixtures\Commands\Namespaced\SubNamespaced\SubNamespacedTestCommand;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

class AbstractApplicationTest extends TestCase
{
    public function testApplicationGetCommandsCorrectly(): void
    {
        /** @var GoodApplication $application */
        $application = ContainerFactory::build()->make(GoodApplication::class);
        $this->assertInstanceOf(GoodApplication::class, $application);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $helper = new ApplicationTester($application);
        $helper->run([]);
        $this->assertEquals(
            'Testing Good Application 1.2.3

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  help                                                   Displays help for a command
  list                                                   Lists commands
  test-command                                           Test Command
 namespaced
  namespaced:namespaced-test-command                     Sub Namespaced Test Command
  namespaced:sub-namespaced:sub-namespaced-test-command  Namespaced Test Command
',
            $helper->getDisplay()
        );

        $commands = $application->getCommands();
        $commandNames = \array_map(
            function (Command $command) {
                return $command->getName();
            },
            $commands
        );
        \sort($commandNames);
        $this->assertCount(3, $commands);
        $this->assertEquals(
            [
                'namespaced:namespaced-test-command',
                'namespaced:sub-namespaced:sub-namespaced-test-command',
                'test-command',
            ],
            $commandNames
        );

        $commandFromClass = $application->getCommandFromClass(SubNamespacedTestCommand::class);
        $this->assertInstanceOf(Command::class, $commandFromClass);
        $this->assertEquals('namespaced:sub-namespaced:sub-namespaced-test-command', $commandFromClass->getName());
    }

    public function testApplicationThrowsExceptionForBadCommands(): void
    {
        /** @var BadApplication $application */
        $application = ContainerFactory::build()->make(BadApplication::class);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $helper = new ApplicationTester($application);
        $helper->run([]);
        $this->assertStringContainsString(
            'Unable to load RQuadlingTests\\Console\\Fixtures\\BadCommand\\BadTestCommand from',
            $helper->getDisplay()
        );
    }

    public function testApplicationThrowsExceptionForMissingCommandsDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('COMMANDS_DIRECTORY is not defined in %s', NoCommandsDirectoryApplication::class));
        /** @var NoCommandsDirectoryApplication $application */
        $application = ContainerFactory::build()->make(NoCommandsDirectoryApplication::class);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $helper = new ApplicationTester($application);
        $helper->run([]);
    }

    public function testApplicationThrowsExceptionForMissingCommandsNamespace(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('COMMANDS_NAMESPACE is not defined in %s', NoCommandsNamespaceApplication::class));
        /** @var NoCommandsNamespaceApplication $application */
        $application = ContainerFactory::build()->make(NoCommandsNamespaceApplication::class);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $helper = new ApplicationTester($application);
        $helper->run([]);
    }
}
