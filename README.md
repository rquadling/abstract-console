# RQuadling/AbstractConsole

[![Build Status](https://img.shields.io/travis/rquadling/abstract-console.svg?style=for-the-badge&logo=travis)](https://travis-ci.org/rquadling/abstract-console)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/rquadling/abstract-console.svg?style=for-the-badge&logo=scrutinizer)](https://scrutinizer-ci.com/g/rquadling/abstract-console/)
[![GitHub issues](https://img.shields.io/github/issues/rquadling/abstract-console.svg?style=for-the-badge&logo=github)](https://github.com/rquadling/abstract-console/issues)

[![PHP Version](https://img.shields.io/packagist/php-v/rquadling/abstract-console.svg?style=for-the-badge)](https://github.com/rquadling/abstract-console)
[![Stable Version](https://img.shields.io/packagist/v/rquadling/abstract-console.svg?style=for-the-badge&label=Latest)](https://packagist.org/packages/rquadling/abstract-console)

[![Total Downloads](https://img.shields.io/packagist/dt/rquadling/abstract-console.svg?style=for-the-badge&label=Total+downloads)](https://packagist.org/packages/rquadling/abstract-console)
[![Monthly Downloads](https://img.shields.io/packagist/dm/rquadling/abstract-console.svg?style=for-the-badge&label=Monthly+downloads)](https://packagist.org/packages/rquadling/abstract-console)
[![Daily Downloads](https://img.shields.io/packagist/dd/rquadling/abstract-console.svg?style=for-the-badge&label=Daily+downloads)](https://packagist.org/packages/rquadling/abstract-console)

Abstract Application and Command with Input and Output helpers used by RQuadling's projects

## Installation

Using Composer:

```sh
composer require rquadling/abstract-console
```

## Dependency Injection

Add the following entries to your `di.php`

```php
    // Symfony Console Input wrapper to allow potential operation via a web based controller
    \Symfony\Component\Console\Input\InputInterface::class => function () {
        return new \RQuadling\Console\Input\Input(array_get($_SERVER, 'argv', []));
    },
    // Symfony Console Output wrapper to allow potential operation via a web based controller
    \Symfony\Component\Console\Output\OutputInterface::class => function (\Psr\Container\ContainerInterface $c) {
        return PHP_SAPI == 'cli'
            ? $c->get(\Symfony\Component\Console\Output\ConsoleOutput::class)
            : $c->get(\Symfony\Component\Console\Output\BufferedOutput::class);
    },
```

## Environment variables

1. `COMMAND_DIRECTORY` - Define the location of the Commands.
2. `COMMAND_NAMESPACE` - Define the namespace for the Commands.
