# CQRS

A foundational package for Command Query Responsibility Segregation (CQRS).

## Table of Contents

- [Installation](#installation)
- [Usage Guide](#usage-guide)
    - [Commands](#commands)
        - [How to Create a Custom Command](#how-to-create-a-command)
        - [How to Run a Command](#how-to-run-a-command)
        - [How to Create an Evented Command](#how-to-create-an-evented-command)
        - [How to Run a Command in a Transaction](#how-to-run-a-command-in-a-transaction)
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

### Commands

A command implements the `ArtisanSdk\Contracts\Commands\Runnable` interface which
makes it both invokable and runnable. The intended use of a command is to perform
some sort of "write" operation or complete a unit of work and return its results.
An asynchronous command would return a promise while a synchronous command would
return the result itself or nothing at all.

#### How to Create a Custom Command

A basic example of using a command is to create a class that extends the
`ArtisanSdk\CQRS\Commands\Command` class and implementing the `run()` method
returning whatever value you want after the command is ran. You can use the constructor
method to inject any command dependencies. Argument dependencies are implicitly
required and the caller must satisfy the requirements or else the developer must
throw an exception to ensure all required arguments are passed and validated
prior to execution of critical command logic.

```
namespace App\Commands\SaveUser;

use App\User;
use ArtisanSdk\CQRS\Commands\Command;

class SaveUser extends Command
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function run()
    {
        $user = $this->model;
        $user->email = $this->argument('email');
        $user->save();

        return $user;
    }
}
```

#### How to Run a Command

There are multiple ways to dispatch a command. The first way is to simply create
an instance of the `ArtisanSdk\CQRS\Dispatcher` and then call `command()` on it
which will return an new instance of the command wrapped inside of an arguments
builder class. You can then chain any arbitrary arguments onto the command before
calling `run()` or invoking the builder directly. You could also call `arguments()`
on the builder passing an array of arguments.

##### Run a Command Using the Dispatcher

```
$user = ArtisanSdk\CQRS\Dispatcher::make()
    ->command(App\Commands\SaveUser::class)
    ->email('johndoe@example.com')
    ->run();
```

##### Run a Command Statically

Alternatively you could just make the command statically which will also create
an instance of the command builder:

```
$user = App\Commands\SaveUser::make()
    ->email('johndoe@example.com')
    ->run();
```

##### Run a Command From Anywhere

Using `ArtisanSdk\CQRS\Traits\CQRS` helper trait on any class (e.g.: a controller)
allows you to dispatch commands directly by simply calling `$this->dispatch()`
or `$this->command()` passing the command's class name as the argument. This will
return an instance of the command builder. The base `ArtisanSdk\CQRS\Commands\Command`
uses this trait and therefore subcommands can be executed within a command in
the same way:

```
namespace App\Http\Controllers;

use App\Commands\SaveUser;
use App\Http\Controllers\Controller;
use ArtisanSdk\CQRS\Traits\CQRS;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use CQRS;

    public function post(Request $request)
    {
        return $this->command(SaveUser::class)
            ->email($request->input('email'))
            ->run();
    }
}
```

##### Run a Command Manually (Without the Command Bus)

Commands executed like the above examples all end up routing the command through
the dispatcher which implements a basic command bus for a few support scenarios
that many command-based applications need including eventing, queueing, and
transactions. While you can and probably should always dispatch a command, you can
also manually execute a command by simply constructing it either using auto-resolution
from the container or manually and then calling the `run()` method on the command
or directly invoking the class:

```
$user = (new App\Commands\SaveUser(new App\User))
    ->email('johndoe@example.com')
    ->run();
```

This will bi-pass the command bus setup by the dispatcher and therefore skip any
added wrapper functionality the dispatcher offers.

#### How to Create an Evented Command

Sometimes you want the rest of your code to be made aware of the processing of a
particular command. You may want to execute some code before the command or after
the command based on the result of the command. Using the dispatcher this is
trivially done by simply implementing the `ArtisanSdk\Contracts\Commands\Eventable`
interface on any command that should be invented:

```
namespace App\Commands;

use ArtisanSdk\Contracts\Commands\Eventable;
use ArtisanSdk\CQRS\Commands\Command;
use App\User;

class SaveUser extends Command implements Eventable
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function run()
    {
        $user = $this->model;
        $user->email = $this->argument('email');
        $user->save();

        return $user;
    }
}
```

With the addition of the eventable contract implemented, an event will be fired
before and another after the command is ran. The before event will be given the
arguments passed to the command while the after event will be given the results
of the command itself. The event fired is an instance of `ArtisanSdk\CQRS\Events\Event`.

@todo: describe the auto-resolution behavior for event names
@todo: describe how to overwrite the command's default class names using `beforeEvent` and `afterEvent` methods on the command

#### How to Run a Command in a Transaction

Often you'll create a command that performs multiple database writes to different
tables or multiple records. Alternatively you may have a command that executes
multiple subcommands and there needs to be a certain level of atomicity relating
the command's overall execution. If a sucommand or secondary write fails, you'll
want to roll back the command. This boilerplate logic is annoying to have to
write into each command so this package provides a trivial way to do this by
implementing the `ArtisanSdk\Contracts\Commands\Transactional` interface on any
command that should be transactional:

```
namespace App\Commands;

use ArtisanSdk\Contracts\Commands\Transactional;
use ArtisanSdk\CQRS\Commands\Command;

class SaveUser extends Command implements Transactional
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function run()
    {
        $user = $this->model;
        $user->email = $this->argument('email');
        $user->save();

        return $user;
    }
}
```

Now if for any reason the command throws an exception, the queries executed within
the command or subcommands will be rolled back. If everything works as expected then
the transactions are committed like normal. The benefit of this approach is that it
makes it easy to bypass the transactional model for testing purposes by simply
invoking the commands manually which bypasses the transactional wrapper.

##### Aborting a Transactional Command

Sometimes you want to rollback your transaction without throwing an exception and
yet still return a result that satisfies your caller's response expectations. For
such cases the command should call `abort()` and then return the result. The
transactional wrapper will still rollback but will not bubble any exception:

```
namespace App\Commands;

use ArtisanSdk\Contracts\Commands\Transactional;
use ArtisanSdk\CQRS\Commands\Command;

class ChangePassword extends Command implements Transactional
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function run()
    {
        $email = $this->argument('email');
        $user = $this->model->where('email', $email)->first();
        if( ! $user ) {
            $this->abort();
            return false;
        }

        $user->password = $this->argument('password');
        $user->save();

        return $user;
    }
}
```

The above example changes the password of the user that matches the email address.
If the email does not match any known user, rather than throwing an exception, we
just abort and return `false` instead. Had we performed any other write queries
then those would have been rolledback.

##### Silencing After Events With Abort

The main benefit of aborting a command however is that the after events are not
fired if the command is aborted. This is handy when a command is actually queued
as a job and the job has already been handled or is no longer needed and therefore
should not fire an exception and risk being marked as a failed job but can instead
simply be aborted and still be treated as a successful job by the worker. Imagine
for example that an email was queued to be sent out 15 minutes later but within that
15 minutes an action occurred that would make such an email irrelevant or redundant:
then when the command is being executed as a queued job to send the email out,
a pre-check could be performed to determine if the command should still be ran
and if not, the command could be aborted.

##### Checking If a Command Was Aborted

The `abort()` and `aborted()` methods are public methods of the command and can
also be used in circumstances where you might want to abort multiple commands in
a command pool based on when one command in the pool is aborted. You can also use
the `aborted()` method to check if a command has been aborted to better determine
what to do with the command's result.

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
