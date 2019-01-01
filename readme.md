# CQRS

A foundational package for Command Query Responsibility Segregation (CQRS).

## Table of Contents

- [Installation](#installation)
    - [Peer Dependencies](#peer-dependencies)
    - [Framework Helpers Functions](#framework-helpers-functions)
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
        - [Recommended Conventions for Command and Event Naming](#recommended-conventions-for-command-and-event-naming)
    - [Traits](#traits)
        - [Using CQRS in Your Classes](#using-cqrs-in-your-classes)
        - [Using Argument Validators](#using-argument-validators)
        - [Using Option Defaults](#using-option-defaults)
        - [Saving Models Within Commands](#saving-models-within-commands)
        - [Using the Silencer](#using-the-silencer)
- [Running the Tests](#running-the-tests)
- [Licensing](#licensing)

## Installation

The package installs into a PHP application like any other PHP package:

```bash
composer require artisansdk/cqrs
```

### Peer Dependencies

This package has some peer dependencies on Laravel packages. Rather than depending
on the entire framework, it is up to the developer to meet the peer dependencies
if the dependent features are going to be used. While Laravel does provide out the
box packages for these dependencies, if you install outside of Laravel then you
may need to configure your application to implement the dependent interfaces.

The following explains which packages you should additionally install should you
need the corresponding features outside of Laravel:

- `illuminate/container`: An IoC container must be provided by the framework and
injected into the `ArtisanSdk\CQRS\Dispatcher`. Laravel will do this automatically
via a typehinted interface in the constructor, but the `Dispatcher` technically
relies directly on `Illuminate\Container\Container` if you use `Dispatcher::make()`
manually or rely on `Command::make()` or similar static functions.

- `illuminate/bus`: Queueable jobs that get chained rely upon a command bus within
  Laravel. While not strictly needed, if you intend to do sophisticated queueing then
  you will need this peer dependency for the actual job dispatching. See also
  [Framework Helper Functions](#framework-helper-functions).

- `illuminate/events`: Using the `ArtisanSdk\CQRS\Commands\Evented` command wrapper
  will require this package which ships with Laravel. Essentially the dependency
  relies on the ability for the framework to dispatch the events to the framework
  layers and back down to the CQRS package level.

- `illuminate/database`: Using the `ArtisanSdk\CQRS\Commands\Transaction` or
  `ArtisanSdk\CQRS\Queries\Query` classes will require this database package to
  provide database transactions and querying statements.

- `illuminate/pagination`: Using `ArtisanSdk\CQRS\Queries\Query::paginate()` method
  will require the use of this package to return the paginated results.

- `illuminate/queue`: Using any queueing functions of the Laravel framework will
  require this package. This would include any serialization of the models for jobs
  and events or for interacting with queues from jobs and commands.

- `illuminate/validation`: You only need to install this peer-dependency if you
  wish to have arguments passed to queries and command automatically validated
  against an array of validation rules or against a custom passed validator. The
  CQRS package provides support for this Laravel validation package but it is
  not strictly required.

### Framework Helper Functions

Laravel includes several `helpers.php` files which expose global functions that
technically any framework could implement. This further decouples this package
from Laravel. If this package is therefore use outside of Laravel you will need
to implement these helpers (much like this package did for testing purposes):

- `app()` is used to resolve dependencies to make static calls like `Command::make()`
  able to auto-resolve commands out of an IoC container. When passed a string that
  references a class name bound in the container, the function should return a
  built instance of that class.

- `dispatch()` is used primarily used by chainable, queued commands via the
  `ArtisanSdk\CQRS\Traits\Queueable` trait helper `dispatchNextJobInChain()`.
  The function should accept a class and pass it along the framework's command
  bus. For Laravel-based applications this can be met by installing `illuminate\bus`
  which provides `Illuminate\Bus\Dispatcher` as the command bus.

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
interface on any command that should be evented:

```php
namespace App\Commands;

use App\User;
use ArtisanSdk\Contracts\Commands\Eventable;
use ArtisanSdk\CQRS\Commands\Command;

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

use App\User;
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
        if( ! $user = $this->user($email) ) {
            $this->abort();

            return false;
        }

        $user->password = $this->argument('password');
        $user->save();

        return $user;
    }

    protected function user() : User
    {
        return $this->model
            ->where('email', $this->argument('email'))
            ->first();
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

use App\User;
use ArtisanSdk\CQRS\Events\Event;

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

use App\Events\UserSaved;
use ArtisanSdk\Contracts\Commands\Eventable;
use ArtisanSdk\CQRS\Commands\Command;

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

The package's primary functionality is exposed as a set of base classes but these
classes are composed from a set of base traits. You can use these traits directly
in your application code even where CQRS may not be fully needed but the traits
prove to be a useful and consistent API for your application.

#### Using CQRS in Your Classes

`ArtisanSdk\CQRS\Traits\Arguments` is a trait that provides arguments and options
to a class including all the relevant validation logic and default resolvers.
The public methods of the trait are:

- `Arguments::arguments($arguments)` to get or set the arguments fluently
- `Arguments::argument($name, $validator)` to get an argument and validate it
- `Arguments::option($name, $default, $validator)` to get an optional argument and provide a default
- `Arguments::hasOption($name)` to check if the optional argument is present

`ArtisanSdk\CQRS\Traits\CQRS` is the trait that provides the main interactive
API for the CQRS pattern. This is the trait that is typically included on a controller,
console command, or other class in order to directly dispatch commands using the
command builder and dispatcher. The usable methods (most are protected) of the trait are:

- `CQRS::dispatcher()` gets an instance of the `Dispatcher`. Instances are not singletons
  so every command that is dispatched is ran through an unique dispatcher (command bus).
  This is typically used like `$this->dispatcher()->dispatch($class)->run()` to compose
  the runnable class then run it. It can also be used to dynamically forward events
  like `$this->dispatcher()->creating($user)` which will fire a `Creating` event
  with the user model as argument.
- `CQRS::call($class, $arguments)` directly composes then runs the class with the
  passed arguments.
- `CQRS::command($class)` to compose a command using the dispatcher but not run it (use `call()` instead).
- `CQRS::query($class)` to compose a query using the dispatcher but not run it (use `call()` instead).
- `CQRS::event($event, $payload)` to compose an after event with the payload and fire it using the dispatcher.
- `CQRS::until($event, $payload)` to compose a before event with the payload and fire it using the dispatcher.

`ArtisanSdk\CQRS\Traits\Handle` is a trait that can be used by commands to implement
the `ArtisanSdk\Contracts\Handler` interface such that an event object may be passed
to the `handle()` method of a command and the command be ran through the command
dispatcher using the properties of the event as the arguments. Additionally if the
command is queueable then the execution of the command will be deferred as a queued
job instead. When the job is resolved out of the queue, the command will be directly
invoked, bypassing the handler yet still using the event properties as arguments.

`ArtisanSdk\CQRS\Traits\Queues` is a wrapper trait for Laravel compatibility of
making an event or command behave like a queued job. It also lets the command interact
with the command much like a queued job can. The intended use for this trait is
to make the class it is used on a queuable job. See Laravel's documentation on
how to customize properties such as `$connection`, `$queue`, and `$delay` or
to perform chaining of commands as queued jobs.

`ArtisanSdk\CQRS\Traits\Save` is a trait that helps with saving of Eloquent models,
especially self-validating models like [`artisansdk\model`](http://github.com/artisansdk/model) provides. It simply provides
a `save($model)` public method which ensures that the model is saved or throws an
exception and if saved will return the saved model. See also [Saving Models Within Commands](#saving-models-within-commands).

`ArtisanSdk\CQRS\Traits\Silencer` is a trait that the prevents the firing of events
when a command or query is ran. The public methods of the trait are:

- `Silencer::silence()`: set the silence flag on the command so that events are
  not fired.
- `Silence::silenced()`: a boolean check to see if the command is silenced. This
  is used by the evented command wrapper to determine if events should be fired.
- `Silence::silently()`: a shorthand method for `$command->silence()->run()` such
  that you can silently run a command with just `$command->silently()`.

#### Using Argument Validators

Commands and queries that require arguments often have a lot of boilerplate code
that handles validating the values of the arguments passed. To abstract this away,
the package includes a simple way to inline common validators and pass more
domain-specific validators using callables. You can use a simple closure that
returns a boolean value, a class or interface name to check the argument matches,
an array of Laravel validation rules for the argument, or a pre-built Laravel
validator instance.

```php
namespace App\Commands;

use App\Invoice;
use App\Coupon;
use ArtisanSdk\CQRS\Commands\Command;
use Illuminate\Validation\Factory as Validator;

class CalculateInvoice extends Command
{
    public function run()
    {
        // Validate the argument is simply set with a non empty value
        $number = $this->argument('number');

        // Validate the argument matches the Invoice class
        $invoice = $this->argument('invoice', Invoice::class);

        // Validate the argument against a rule of validation rules...
        $subtotal = $this->argument('subtotal', ['integer', 'min:0'])

        // ...or construct it manually yourself for something more complicated
        $subtotal = $this->argument('subtotal', Validator::make($this->arguments(), [
            'subtotal' => ['integer', 'min:0', 'lte:total'],
        ]));

        // Validate the argument against a custom callable...
        $coupon = $this->argument('coupon', function(string $code, string $argument) {
            return $this->couponExists($code, $argument);
        });

        // ... or just reference a method on a callable class
        $coupon = $this->argument('coupon', [$this, 'couponExists']);
    }

    public function couponExists(string $code, string $argument)
    {
        return Coupon::where('code', $code)->exists();
    }
}
```

#### Using Option Defaults

The following code demonstrates the use of an option instead of an argument. Based
on the presence of the option alone (a flag essentially) you could perform some
guarded code or based on explicit check of the option's value if present. In the
following example, the default behavior if the option is not set is that the
invoice is not saved:

```php
namespace App\Commands;

use App\Invoice;
use ArtisanSdk\CQRS\Commands\Command;

class CalculateInvoice extends Command
{
    public function run()
    {
        $invoice = $this->argument('invoice', Invoice::class);

        if( $this->hasOption('save') && true === $this->option('save')) {
            $invoice->save();
        }

        return $invoice;
    }
}
```

The default value for an option is `null` by default. You can also set an explicit
default value for an option that is not present in the list of arguments. This
is demonstrated below using the same example as above. The result is that the
invoice is always saved unless explicitly set to false.

```php
namespace App\Commands;

use App\Invoice;
use ArtisanSdk\CQRS\Commands\Command;

class CalculateInvoice extends Command
{
    public function run()
    {
        $invoice = $this->argument('invoice', Invoice::class);

        if( $this->option('save', true) ) {
            $invoice->save();
        }

        return $invoice;
    }
}
```

Occasionally you will want to perform some logical work that is more expensive
when an option is not set and the default value needs to be resolved. For example
you may want to default to the authenticated user when no user is passed to a
command or query as an option. In Laravel this incurs a hit agains the database
which is considered expensive and unnecessary if the default option is not actually
used. Therefore it's preferred to defer this expensive work. This
package supports a resolver callable for the default option which ensures that
the work is lazily deferred until indeed the default is needed.

```php
namespace App\Commands;

use App\Invoice;
use App\User;
use ArtisanSdk\CQRS\Commands\Command;

class CalculateInvoice extends Command
{
    public function run()
    {
        $invoice = $this->argument('invoice', Invoice::class);

        // This is wasteful since you have to resolve the user even when not used
        // $editor = $this->option('editor', auth()->user());

        // Resolve the authenticated user as the default using a closure...
        $editor = $this->option('editor', function(string $option) {
            return auth()->user();
        });

        // ... or just reference a method on a callable class
        $editor = $this->option('editor', [$this, 'resolveUser']);

        $invoice->editor()->associate($user);

        $invoice->save();

        return $invoice;
    }

    public function resolveUser(string $option) : User
    {
        return auth()->user();
    }
}
```

#### Saving Models Within Commands

If you use the `ArtisanSdk\CQRS\Traits\Save` trait or the `ArtisanSdk\CQRS\Commands\Command`
which includes this trait, then you can quickly save Eloquent models including
self-validating models like those provided by [`artisansdk\model`](http://github.com/artisansdk/model).
Simply call `save()` from within the command or controller and pass in the model
that should be saved. If the model does not save because it cannot be validated,
then an exception will be raised. If the model can be saved then the saved instance
is returned. The use of this helper trait can streamline commands considerably
and ensure that saves are being performed consistently.

```php
namespace App\Commands;

use ArtisanSdk\CQRS\Commands\Command;

class CalculateInvoice extends Command
{
    public function run()
    {
        $invoice = $this->argument('invoice');
        $invoice->total = 100;

        return $this->save($invoice);
    }
}
```

In addition to simply saving the models, the trait also formats the errors for
CLI applications like Artisan commands and PHPUnit so they are more readable.

#### Using the Silencer

Sometimes you just don't want your evented commands to fire events. As an example,
say that you were sending out an email using `SendPasswordResetEmail` command which
is normally triggered by the `UserPasswordReset` event. Let's say however that during
user registration, the `ResetUserPassword` command is called and yet you do not
want to send out the normal email for password resets. Instead you wish to trigger
the logic of resetting a password for an account and instead use `SendAccountActivationEmail`
command to send an account activation in response to `UserRegistered` event. This
is all possible using the `ArtisanSdk\CQRS\Traits\Silencer` trait which is already
used by the base `ArtisanSdk\CQRS\Commands\Command` class.

In order to accomplish the above example you might write the following:

```php
namespace App\Commands;

use App\User;
use App\Commands\ResetUserPassword;
use App\Events\UserPasswordReset;
use ArtisanSdk\CQRS\Commands\Command;

class RegisterUser extends Command
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function afterEvent()
    {
        return UserPasswordReset::class;
    }

    public function run()
    {
        $user = new User();
        $user->email = $this->argument('email');
        $this->save($user);

        return $this->command(ResetUserPassword::class)
            ->user($user)
            ->silence()
            ->run();
    }
}
```

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

Copyright (c) 2018-2019 [Artisans Collaborative](https://artisanscollaborative.com)

This package is released under the MIT license. Please see the LICENSE file
distributed with every copy of the code for commercial licensing terms.
