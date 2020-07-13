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

namespace RQuadling\Console\Abstracts;

use DI\Container;
use Phpactor\ClassFileConverter\Domain\ClassName;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RQuadling\ClassFileConversion\Conversion;
use RQuadling\Environment\Environment;
use RQuadling\Reflection\ReflectionClass;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

abstract class AbstractApplication extends Application
{
    const APP_NAME = 'UNKNOWN';
    const APP_VERSION = 'UNKNOWN';
    const COMMANDS_DIRECTORY = null;
    const COMMANDS_NAMESPACE = null;

    protected InputInterface $input;
    protected OutputInterface $output;

    /**
     * @Inject
     */
    private Container $container;

    public function __construct()
    {
        parent::__construct(static::APP_NAME, static::APP_VERSION);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureIO(InputInterface $input, OutputInterface $output): void
    {
        parent::configureIO($input, $output);

        $this->input = $input;
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        // Attach the additional output styles.
        $styles = [
            'fire' => ['fg' => 'red', 'bg' => 'yellow', 'options' => ['bold']],
            'ice' => ['fg' => 'cyan', 'bg' => 'white', 'options' => ['bold']],
            'file' => ['fg' => 'green'],
            'setting' => ['fg' => 'white', 'options' => ['bold']],
            'file_error' => ['fg' => 'green', 'bg' => 'red'],
            'setting_error' => ['fg' => 'white', 'bg' => 'red', 'options' => ['bold']],
        ];
        $formatter = $this->output->getFormatter();
        foreach ($styles as $styleName => $style) {
            if (!$formatter->hasStyle($styleName)) {
                $formatter->setStyle(
                    $styleName,
                    new OutputFormatterStyle(
                        array_get($style, 'fg'),
                        array_get($style, 'bg'),
                        array_get($style, 'options', [])
                    )
                );
            }
        }

        // Load the commands for this application.
        $this->addCommands(\array_filter($this->getCommands()));

        return parent::doRun($input, $output);
    }

    /**
     * Get any commands for a single command container.
     *
     * @return array<int, AbstractCommand>
     */
    protected function getCommands(): array
    {
        foreach ([
                     'COMMANDS_DIRECTORY' => static::COMMANDS_DIRECTORY,
                     'COMMANDS_NAMESPACE' => static::COMMANDS_NAMESPACE,
                 ] as $name => $value) {
            if (empty($value)) {
                throw new RuntimeException(\sprintf('%s is not defined in %s', $name, \get_called_class()));
            }
        }

        $commands = [];
        /** @var SplFileInfo $commandFile */
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    Environment::getRoot().'/'.\trim(static::COMMANDS_DIRECTORY, '/'),
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            )
            as $commandFile) {
            if ($commandFile->getExtension() === 'php') {
                $command = $this->getCommandFromFile($commandFile->getPathname());
                if ($command !== false) {
                    $commands[] = $command;
                }
            }
        }

        return $commands;
    }

    /**
     * Tag specific commands' description.
     *
     * @return AbstractCommand|false
     */
    protected function getCommandFromFileAndClass(string $commandFile, ClassName $commandClass)
    {
        $command = false;
        /** @var class-string $commandClassFQN */
        $commandClassFQN = (string)$commandClass;

        try {
            $reflectedClass = new ReflectionClass($commandClassFQN);
            // Do not attempt to instantiate abstract classes.
            // Class must be an AbstractCommand.
            if (!$reflectedClass->isAbstract() && $reflectedClass->isSubclassOf(AbstractCommand::class)) {
                /** @var AbstractCommand $command */
                $command = $this->container->make($commandClassFQN, ['name' => $this->generateCommandName($commandClass)]);
            }
        } catch (Throwable $exception) {
            /** @var FormatterHelper $formatter */
            $formatter = $this->getHelperSet()->get('formatter');
            $this->output->writeln(
                $formatter->formatBlock(
                    [
                        \sprintf('Unable to load %s from %s', $commandClass ?? 'command', $commandFile),
                        ' ',
                        $exception->getMessage(),
                    ],
                    'error',
                    true
                )
            );
            $command = false;
        }

        return $command;
    }

    /**
     * @param class-string<AbstractCommand> $commandClass
     *
     * @return AbstractCommand|false
     */
    protected function getCommandFromClass(string $commandClass)
    {
        return $this->getCommandFromFileAndClass(
            (string)Conversion::getFilenameFromClassName($commandClass),
            ClassName::fromString($commandClass)
        );
    }

    /**
     * @return AbstractCommand|false
     */
    protected function getCommandFromFile(string $commandFile)
    {
        $class = Conversion::getClassNameFromFilename($commandFile);
        if (!\is_null($class)) {
            return $this->getCommandFromFileAndClass($commandFile, $class);
        }

        // @codeCoverageIgnoreStart
        throw new RuntimeException(\sprintf('Unable to generate command from %s', $commandFile));
        // @codeCoverageIgnoreEnd
    }

    protected function generateCommandName(ClassName $commandClass): string
    {
        /*
         * Command names are taken from the class name, taking into account the namespace.
         *
         * Examples:
         * A single command like \RQuadling\Console\Commands\UpdateHosts will become update-hosts.
         * A multiple command package like \RQuadling\Console\Commands\Package\UpdateHosts will become package:update-hosts.
         *
         * 1. Take the class name.
         * 2. Ignore the first n class name parts (COMMANDS_NAMESPACE)
         * 3. Join the remaining parts with a ':'
         * 4. Use a regex to get the first part (the package:), from the rest (the command).
         * 5. Remove the package name if the environment matches the package name.
         */
        \preg_match(
            '`(?P<package>([^:]++:)+|)(?P<command>.++)$`',
            \implode(
                ':',
                \array_slice(
                    \explode('\\', (string)$commandClass),
                    1 + \substr_count(
                        \trim(
                            static::COMMANDS_NAMESPACE,
                            '\\'
                        ),
                        '\\'
                    )
                )
            ),
            $match
        );

        return \sprintf(
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
}
