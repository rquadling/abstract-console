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

namespace RQuadling\Console\Abstracts;

use LogicException;
use RQuadling\DependencyInjection\Traits\DelayedInjectionTrait;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractCommand.
 *
 * It provides the following features:
 *
 * 1. Access to the command line input interface via $this->input.
 * 2. Access to the command line output interface via $this->output.
 */
abstract class AbstractCommand extends SymfonyCommand
{
    use DelayedInjectionTrait;

    protected InputInterface $input;

    protected OutputInterface $output;

    /**
     * A list of all traits used to aid in handling of specific traits requirements.
     *
     * @var array<int, string>
     */
    private $usedTraits = [];

    /**
     * Configures the current command.
     *
     * @param ?string $commandName
     */
    protected function configure(string $commandName = null): void
    {
        if (\is_null($commandName)) {
            /*
             * Command names are taken from the class name, taking into account the namespace.
             *
             * Examples:
             * A single command like \RQuadling\Console\Commands\UpdateHosts will become update-hosts.
             * A multiple command package like \RQuadling\Console\Commands\Package\UpdateHosts will become package:update-hosts.
             *
             * 1. Take the class name.
             * 2. Ignore the first n class name parts ($_ENV['COMMAND_NAMESPACE'])
             * 3. Join the remaining parts with a ':'
             * 4. Use a regex to get the first part (the package:), from the rest (the command).
             * 5. Remove the package name if the environment matches the package name.
             */
            \preg_match(
                '`(?P<package>([^:]++:)+|)(?P<command>.++)$`',
                \implode(
                    ':',
                    \array_slice(
                        \explode('\\', \get_called_class()),
                        1 + \substr_count(
                            \trim(
                                $_ENV[AbstractApplication::COMMAND_NAMESPACE_ENVVAR],
                                '\\'
                            ),
                            '\\'
                        )
                    )
                ),
                $match
            );

            $commandName = \sprintf(
                '%s%s',
                \implode(
                    ':',
                    \array_map(
                        function ($packagePart) {
                            return str_to_kebab_case($packagePart);
                        },
                        \explode(':', $match['package'])
                    )
                ),
                str_to_kebab_case($match['command'])
            );
        }
        $this->setName($commandName);

        $this->usedTraits = class_uses_recursive(\get_called_class());
    }

    /**
     * Executes the current command.
     *
     * This method is not abstract because you can use this class
     * as a concrete class. In this case, instead of defining the
     * execute() method, you set the code to execute by passing
     * a Closure to the setCode() method.
     *
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int|null null or 0 if everything went fine, or an error code
     *
     * @throws LogicException When this abstract method is not implemented
     *
     * @see setCode()
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        $this->input = $input;
        $this->output = $output;

        // Handle '@DelayedInject'
        $this->handleDelayedInjection();

        return 0;
    }
}
