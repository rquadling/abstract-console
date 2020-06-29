<?php

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

use josegonzalez\Dotenv\Loader;
use PHPUnit\Framework\TestCase;
use RQuadling\Console\Abstracts\AbstractApplication;
use RQuadling\DependencyInjection\ContainerFactory;
use RQuadlingTests\Console\Fixtures\Commands\Namespaced\NamespacedTestCommand;
use RQuadlingTests\Console\Fixtures\Commands\Namespaced\SubNamespaced\SubNamespacedTestCommand;
use RQuadlingTests\Console\Fixtures\Commands\TestCommand;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class AbstractCommandTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_ENV[AbstractApplication::COMMAND_DIRECTORY_ENVVAR], $_ENV[AbstractApplication::COMMAND_NAMESPACE_ENVVAR]);
        (new Loader(__DIR__.'/Fixtures/Commands/.env'))->parse()->toEnv(true);
    }

    public function testCommandIsCorrectlyNamed(): void
    {
        $command = ContainerFactory::build()->get(TestCommand::class);
        $this->assertEquals('test-command', $command->getName());
        $this->assertEmpty($command->namespacedTestCommand);
        $command->execute(new ArgvInput(), new ConsoleOutput());
        $this->assertInstanceOf(NamespacedTestCommand::class, $command->namespacedTestCommand);
    }

    public function testNamespacedCommandIsCorrectlyNamed(): void
    {
        $command = ContainerFactory::build()->get(NamespacedTestCommand::class);
        $this->assertEquals('namespaced:namespaced-test-command', $command->getName());
        $this->assertEmpty($command->testCommand);
        $command->execute(new ArgvInput(), new ConsoleOutput());
        $this->assertInstanceOf(TestCommand::class, $command->testCommand);
    }

    public function testSubNamespacedCommandIsCorrectlyNamed(): void
    {
        $command = ContainerFactory::build()->get(SubNamespacedTestCommand::class);
        $this->assertEquals('namespaced:sub-namespaced:sub-namespaced-test-command', $command->getName());
        $this->assertEmpty($command->testCommand);
        $this->assertEmpty($command->namespacedTestCommand);
        $command->execute(new ArgvInput(), new ConsoleOutput());
        $this->assertInstanceOf(TestCommand::class, $command->testCommand);
        $this->assertInstanceOf(NamespacedTestCommand::class, $command->namespacedTestCommand);
    }
}
