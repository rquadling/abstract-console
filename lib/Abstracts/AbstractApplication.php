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

use DI\Container;
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
    const COMMAND_DIRECTORY_ENVVAR = 'COMMAND_DIRECTORY';
    const COMMAND_NAMESPACE_ENVVAR = 'COMMAND_NAMESPACE';

    /**
     * @var InputInterface
     * @Inject
     */
    protected $input;

    /**
     * @var OutputInterface
     * @Inject
     */
    protected $output;

    /**
     * @var Container
     * @Inject
     */
    private $container;

    /**
     * {@inheritdoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        return parent::run($input ?? $this->input, $output ?? $this->output);
    }

    /**
     * {@inheritdoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

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
     * @return AbstractCommand[]
     */
    protected function getCommands(): array
    {
        $this->validateEnvironment();

        $commands = [];
        /** @var SplFileInfo $commandFile */
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    Environment::getRoot().'/'.\trim($_ENV[static::COMMAND_DIRECTORY_ENVVAR], '/'),
                    RecursiveDirectoryIterator::SKIP_DOTS
                )
            )
            as $commandFile) {
            if ($commandFile->getExtension() === 'php') {
                $command = $this->getCommandFromFile(\realpath($commandFile));
                if ($command) {
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
    protected function getCommandFromFileAndClass(string $commandFile, string $commandClass)
    {
        $command = false;

        try {
            $reflectedClass = new ReflectionClass($commandClass);
            // Do not attempt to instantiate abstract classes.
            // Class must be an AbstractCommand.
            if (!$reflectedClass->isAbstract() && $reflectedClass->isSubclassOf(AbstractCommand::class)) {
                /** @var AbstractCommand $command */
                $command = $this->container->make($commandClass);
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
     * @return AbstractCommand|false
     */
    protected function getCommandFromClass(string $commandClass)
    {
        return $this->getCommandFromFileAndClass(Conversion::getFilenameFromClassName($commandClass), $commandClass);
    }

    /**
     * @return AbstractCommand|false
     */
    protected function getCommandFromFile(string $commandFile)
    {
        return $this->getCommandFromFileAndClass($commandFile, Conversion::getClassNameFromFilename($commandFile));
    }

    protected function validateEnvironment()
    {
        foreach ([AbstractApplication::COMMAND_DIRECTORY_ENVVAR, AbstractApplication::COMMAND_NAMESPACE_ENVVAR] as $envVar) {
            if (!\array_key_exists($envVar, $_ENV)) {
                throw new RuntimeException(\sprintf('%s is not defined in your .env file', $envVar));
            }
        }
    }
}
