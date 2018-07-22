# CQRS

A foundational package for Command Query Responsibility Segregation (CQRS).

## Table of Contents

- [Installation](#installation)
- [Usage Guide](#usage-guide)
- [Running the Tests](#running-the-tests)
- [Licensing](#licensing)

## Installation

The package installs into a PHP application like any other PHP package:

```bash
composer require artisansdk/cqrs
```

TODO: These indirect dependencies need further explanation:

- app()
- dispatch()
- illuminate/container (for IoC resolution)
- illuminate/bus (for job dispatching)
- illuminate/events (for event dispatching)
- illuminate/database (for transactional commands and queries)
- illuminate/queue (serializesmodels for jobs and events, interactswithqueues for jobs)

## Usage Guide

The common use cases for this package should be documented including any troubleshooting.

## Running the Tests

The package is unit tested with 100% line coverage and path coverage. You can
run the tests by simply cloning the source, installing the dependencies, and then
running `./vendor/bin/phpunit`. Additionally included in the developer dependencies
are some Composer scripts which can assist with Code Styling and coverage reporting:

```bash
composer test
composer watch
composer fix
composer report
```

See the `composer.json` for more details on their execution and reporting output.
Note that `composer watch` relies upon [`watchman-make`](https://facebook.github.io/watchman/docs/install.html).
Additionally `composer report` assumes a Unix system to run line coverage reporting.
Configure the command setting the value for `min = 80` to set your minimum line
coverage requirements.

## Licensing

Copyright (c) 2018 [Artisans Collaborative](https://artisanscollaborative.com)

This package is released under the MIT license. Please see the LICENSE file
distributed with every copy of the code for commercial licensing terms.
