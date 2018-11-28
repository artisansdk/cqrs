# CQRS

A foundational package for Command Query Responsibility Segregation (CQRS).

## Table of Contents

- [Installation](#installation)
- [Usage Guide](#usage-guide)
    - [Commands](#commands)
        - [How to Create a Command](#how-to-create-a-command)
        - [How to Run a Command](#how-to-run-a-command)
        - [How to Create an Evented Command](#how-to-create-an-evented-command)
        - [How to Run a Command in a Transaction](#how-to-run-a-command-in-a-transaction)
        - [How to Use a Command as an Event Handler](#how-to-use-a-command-as-an-event-handler)
        - [How to Queue a Command](#how-to-queue-a-command)
    - [Queries](#queries)
        - [How to Create a Query](#how-to-create-a-query)
        - [How to Get Query Results](#how-to-get-query-results)
        - [How to Create an Evented Query](#how-to-create-an-evented-query)
    - [Events](#events)
        - [How Auto-resolution of Events Work](#how-auto-resolution-of-events-work)
        - [How to Customize the Before and After Events](#how-to-customize-the-before-and-after-events)
        - [Recommended Conventions for Command and Event Naming](#recommended-conventions-for-command-event-naming)
    - [Traits](#traits)
        - [Using CQRS in Your Classes](#using-cqrs-in-your-classes)
        - [Saving Models Within Commands](#saving-models-within-commands)
        - [Using the Silencer](#using-the-silencer)
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

TODO: These features need to be added to initial release:

- `Traits\Arguments::argument(string key, default = null)` should be `argument(string key, callable validator = null)` and throw `InvalidArgument` exception if argument is not set or optional validator fails
- `Traits\Arguments::option(string key, default = null)` and `hasOption(string key)` should be added to compensate for change in `argument()` signature

## Usage Guide

### Commands

A command implements the `ArtisanSdk\Contracts\Commands\Runnable` interface which
makes it both invokable and runnable. The intended use of a command is to perform
some sort of "write" operation or complete a unit of work and return its results.
An asynchronous command would return a promise while a synchronous command would
return the result itself or nothing at all.

#### How to Create a Command

A basic example of using a command is to create a class that extends the
`ArtisanSdk\CQRS\Commands\Command` class and implementing the `run()` method
returning whatever value you want after the command is ran. You can use the constructor
method to inject any command dependencies. Argument dependencies are implicitly
required and the caller must satisfy the requirements or else the developer must
throw an exception to ensure all required arguments are passed and validated
prior to execution of critical command logic.

```php
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

```php
$user = ArtisanSdk\CQRS\Dispatcher::make()
    ->command(App\Commands\SaveUser::class)
    ->email('johndoe@example.com')
    ->run();
```

##### Run a Command Statically

Alternatively you could just make the command statically which will also create
an instance of the command builder:

```php
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

```php
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

```php
$user = (new App\Commands\SaveUser(new App\User))
    ->arguments([
        'email' => 'johndoe@example.com',
    ])
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

```php
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

##### Silencing an Evented Command

While firing events before and after a command is executed can be useful, sometimes
you want to run an evented command silently so listeners are not fired. Evented
commands have helper methods on the command and also the command builder to make
this use case easier. You can call `silence()` to silence the command, `silenced()`
to check if a command is silenced, and `silently()` to run silently.

```php
$user = App\Commands\SaveUser::make()
    ->email('johndoe@example.com')
    ->silently();
```

#### How to Run a Command in a Transaction

Often you'll create a command that performs multiple database writes to different
tables or multiple records. Alternatively you may have a command that executes
multiple subcommands and there needs to be a certain level of atomicity relating
the command's overall execution. If a sucommand or secondary write fails, you'll
want to roll back the command. This boilerplate logic is annoying to have to
write into each command so this package provides a trivial way to do this by
implementing the `ArtisanSdk\Contracts\Commands\Transactional` interface on any
command that should be transactional:

```php
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

```php
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
then those would have been rolled back.

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

#### How to Use a Command as an Event Handler

When the application fires events, event subscribers can broadcast the event to
all bound event listeners. Each listener provides a `handle()` method which receives
the event as argument and then executes some arbitrary logic. This handler is
essentially the same as a command and therefore commands can be used as command
handlers. The default behavior of `handle()` is to extract the `payload` property
from the event object and pass that as arguments to a command builder and then
self-execute by invoking the command's `run()` method.

First you'll need to create a custom event that should fire. These events need
to extend `ArtisanSdk\CQRS\Events\Event` which provides the payload of arguments
that will be passed to the command. In our example event we accept a type hinted
`App\User` model as the only argument to the constructor to ensure that the event
is created with the right kind of payload. We then assign this model to the `user`
key in an array that is passed to the parent constructor. This parent will correctly
assign to this argument to the payload property.

```php
namespace App\Events;

use ArtisanSdk\CQRS\Events\Event;
use App\User;

class UserSaved extends Event
{
    public function __construct(User $user)
    {
        parent::__construct(compact('user'));
    }
}
```

Next, we'll need to create a command that fires this event when it is done running.
For a non-conventional event name, you'll need to provide the dispatcher with the
custom event name in the `beforeEvent()` and `afterEvent()` methods of the command.
In our case we just return the class name as a string which the dispatcher will
construct and pass the `App\User` returned by `run()` to the event's constructor.

```php
namespace App\Commands;

use ArtisanSdk\Contracts\Commands\Eventable;
use ArtisanSdk\CQRS\Commands\Command;
use App\Events\UserSaved;

class SaveUser extends Command implements Eventable
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function afterEvent()
    {
        return UserSaved::class;
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

While the dispatcher handles all the indirection automatically, it can be summarized
as having accomplished the same as manually constructing and calling the following:

```php
$command = (new App\Commands\SaveUser(new App\User()));
$builder = new ArtisanSdk\CQRS\Builder($command);
$user = $builder->email('johndoe@example.com')->run();
$event = new App\Events\UserSaved($user);
```

Next we'll need create another command which we will bind as the event handler
for any `App\Events\UserSaved` events that are fired:

```php
namespace App\Commands;

use ArtisanSdk\CQRS\Commands\Command;

class SendUserWelcomeEmail extends Command
{
    public function run()
    {
        $user = $this->argument('user');

        // ... the $user is an instance of `App\User` and can be used in a Mailable
    }
}
```

The actual logic of sending the email has been omitted but as you can see it is
possible to get the `App\User` model from the arguments that will be automatically
passed to the command when the `handle()` method is called. This is accomplished
by simply wiring up a listener. It's recommended that you follow Laravel's documentation
on wiring up listeners within the `App\Providers\EventServiceProvider` class using
the `$listen` property but the following demonstrates manually subscribing a event
handler to an event as an event listener:

```php
event()->listen(App\Events\UserSaved::class, App\Commands\SendUserWelcomeEmail::class);
```

Now whenever the `App\Events\UserSaved` event is fired the `App\Commands\SendUserWelcomeEmail`
command's `handle()` method will be called with the event passed as argument. This
in turn will unwrap the event and provide the event's payload as arguments to the
command and then self-execute. Firing the event is the equivalent of manually calling:

```php
$command = (new App\Commands\SaveUser(new App\User()));
$builder = new ArtisanSdk\CQRS\Builder($command);
$user = $builder->email('johndoe@example.com')->run();
$event = new App\Events\UserSaved($user);
$handler = (new App\Commands\SendUserWelcomeEmail());
$result = $handler->handle($event);
```

#### How to Queue a Command

In the case above, we're sending an email and this is often considered a background
process that is not critical to response success. Usually a queued job would be
used in this case. If you think about it though, a job is really just the definition
of an event and it's handler which is queued for later execution rather than
immediate execution. Since commands can be these self-executing event handlers,
the handler can also be queued as a job instead. This package makes it trivial to
queue the handler by simply implementing the `ArtisanSdk\Contract\Queueable` interface
and adding the `ArtisanSdk\CQRS\Traits\Queues` trait on the command you want to
be queued and support queue interactions:

```php
namespace App\Commands;

use ArtisanSdk\CQRS\Commands\Command;
use ArtisanSdk\CQRS\Triats\Queue;
use ArtisanSdk\Contracts\Queuable;

class SendUserWelcomeEmail extends Command implements Queueable
{
    use Queues;

    // ... same as before but it'll now be queued
}
```

Now whenever the `App\Events\UserSaved` event is fired, the `App\Commands\SendUserWelcomeEmail`
command will be queued and then executed by a queue worker. All the same methods
and properties like `$connection`, `$queue`, and `$delay` are supported on the
command now and you can therefore configure your commands with defined defaults
or let the caller decide via `onConnection()`, `onQueue()`, etc.

### Queries

#### How to Create a Query

#### How to Get Query Results

#### How to Create an Evented Query

### Events

#### How Auto-resolution of Events Work

#### How to Customize the Before and After Events

#### Recommended Conventions for Command and Event Naming

### Traits

#### Using CQRS in Your Classes

#### Saving Models Within Commands

#### Using the Silencer

## Running the Tests

The package is unit tested with 94% line coverage and path coverage. You can
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
