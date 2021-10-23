# CQRS

A foundational package for Command Query Responsibility Segregation (CQRS) compatible with Laravel.

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
        - [How to Queue a Command as a Job](#how-to-queue-a-command-as-a-job)
        - [How to Run a Command on a Queue as a Job](#how-to-run-a-command-on-a-queue-as-a-job)
        - [How to Invalidate Queries from Commands](#how-to-invalidate-queries-from-commands)
    - [Queries](#queries)
        - [How to Create a Query](#how-to-create-a-query)
        - [How to Get Query Results](#how-to-get-query-results)
        - [How to Create an Evented Query](#how-to-create-an-evented-query)
        - [How to Create a Cached Query](#how-to-create-a-cached-query)
        - [How to Bust a Cached Query](#how-to-bust-a-cached-query)
    - [Events](#events)
        - [How Auto-resolution of Events Work](#how-auto-resolution-of-events-work)
        - [How to Customize the Before and After Events](#how-to-customize-the-before-and-after-events)
        - [Recommended Conventions for Command and Event Naming](#recommended-conventions-for-command-and-event-naming)
    - [Concerns](#concerns)
        - [Using CQRS in Your Classes](#using-cqrs-in-your-classes)
        - [Using Argument Validators](#using-argument-validators)
        - [Using Option Defaults](#using-option-defaults)
        - [Saving Models Within Commands](#saving-models-within-commands)
        - [Using the Silencer](#using-the-silencer)
    - [Extending](#extending)
        - [Using Macros on the Builder](#using-macros-on-the-builder)
        - [Using Mixins on the Builder](#using-mixins-on-the-builder)
- [Running the Tests](#running-the-tests)
- [Licensing](#licensing)

# Installation

The package installs into a PHP application like any other PHP package:

```bash
composer require artisansdk/cqrs
```

## Peer Dependencies

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

- `illuminate/events`: Using the `ArtisanSdk\CQRS\Buses\Transaction` command wrapper
  will require this package which ships with Laravel. Essentially the dependency
  relies on the ability for the framework to dispatch the events to the framework
  layers and back down to the CQRS package level.

- `illuminate/database`: Using the `ArtisanSdk\CQRS\Buses\Transaction` or
  `ArtisanSdk\CQRS\Query` classes will require this database package to
  provide database transactions and querying statements.

- `illuminate/pagination`: Using `ArtisanSdk\CQRS\Query::paginate()` method
  will require the use of this package to return the paginated results.

- `illuminate/queue`: Using any queueing functions of the Laravel framework will
  require this package. This would include any serialization of the models for jobs
  and events or for interacting with queues from jobs and commands.

- `illuminate/validation`: You only need to install this peer-dependency if you
  wish to have arguments passed to queries and command automatically validated
  against an array of validation rules or against a custom passed validator. The
  CQRS package provides support for this Laravel validation package but it is
  not strictly required.

## Framework Helper Functions

Laravel includes several `helpers.php` files which expose global functions that
technically any framework could implement. This further decouples this package
from Laravel. If this package is therefore use outside of Laravel you will need
to implement these helpers (much like this package did for testing purposes):

- `app()` is used to resolve dependencies to make static calls like `Command::make()`
  able to auto-resolve commands out of an IoC container. When passed a string that
  references a class name bound in the container, the function should return a
  built instance of that class.

- `dispatch()` is used primarily used by chainable, queued commands via the
  `ArtisanSdk\CQRS\Concerns\Queueable` trait helper `dispatchNextJobInChain()`.
  The function should accept a class and pass it along the framework's command
  bus. For Laravel-based applications this can be met by installing `illuminate\bus`
  which provides `Illuminate\Bus\Dispatcher` as the command bus.

# Usage Guide

## Commands

A command implements the `ArtisanSdk\Contract\Runnable` interface which
makes it both invokable and runnable. The intended use of a command is to perform
some sort of "write" operation or complete a unit of work and return its results.
An asynchronous command would return a promise while a synchronous command would
return the result itself or nothing at all.

### How to Create a Command

A basic example of using a command is to create a class that extends the
`ArtisanSdk\CQRS\Command` class and implementing the `run()` method
returning whatever value you want after the command is ran. You can use the constructor
method to inject any command dependencies. Argument dependencies are implicitly
required and the caller must satisfy the requirements or else the developer must
throw an exception to ensure all required arguments are passed and validated
prior to execution of critical command logic.

```php
namespace App\Commands;

use App\User;
use ArtisanSdk\CQRS\Command;

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

### How to Run a Command

There are multiple ways to dispatch a command. The first way is to simply create
an instance of the `ArtisanSdk\CQRS\Dispatcher` and then call `command()` on it
which will return an new instance of the command wrapped inside of an arguments
builder class. You can then chain any arbitrary arguments onto the command before
calling `run()` or invoking the builder directly. You could also call `arguments()`
on the builder passing an array of arguments.

#### Run a Command Using the Dispatcher

```php
$user = ArtisanSdk\CQRS\Dispatcher::make()
    ->command(App\Commands\SaveUser::class)
    ->email('johndoe@example.com')
    ->run();
```

#### Run a Command Statically

Alternatively you could just make the command statically which will also create
an instance of the command builder:

```php
$user = App\Commands\SaveUser::make()
    ->email('johndoe@example.com')
    ->run();
```

#### Run a Command From Anywhere

Using `ArtisanSdk\CQRS\Concerns\CQRS` helper trait on any class (e.g.: a controller)
allows you to dispatch commands directly by simply calling `$this->dispatch()`
or `$this->command()` passing the command's class name as the argument. This will
return an instance of the command builder. The base `ArtisanSdk\CQRS\Command`
uses this trait and therefore subcommands can be executed within a command in
the same way:

```php
namespace App\Http\Controllers;

use App\Commands\SaveUser;
use App\Http\Controllers\Controller;
use ArtisanSdk\CQRS\Concerns\CQRS;
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

#### Run a Command Manually (Without the Command Bus)

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

### How to Create an Evented Command

Sometimes you want the rest of your code to be made aware of the processing of a
particular command. You may want to execute some code before the command or after
the command based on the result of the command. Using the dispatcher this is
trivially done by simply implementing the `ArtisanSdk\Contract\Eventable`
interface on any command that should be evented:

```php
namespace App\Commands;

use App\User;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\CQRS\Command;

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

#### Silencing an Evented Command

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

### How to Run a Command in a Transaction

Often you'll create a command that performs multiple database writes to different
tables or multiple records. Alternatively you may have a command that executes
multiple subcommands and there needs to be a certain level of atomicity relating
the command's overall execution. If a sucommand or secondary write fails, you'll
want to roll back the command. This boilerplate logic is annoying to have to
write into each command so this package provides a trivial way to do this by
implementing the `ArtisanSdk\Contract\Buses\Transactional` interface on any
command that should be transactional:

```php
namespace App\Commands;

use ArtisanSdk\Contract\Buses\Transactional;
use ArtisanSdk\CQRS\Command;

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

#### Aborting a Transactional Command

Sometimes you want to rollback your transaction without throwing an exception and
yet still return a result that satisfies your caller's response expectations. For
such cases the command should call `abort()` and then return the result. The
transactional wrapper will still rollback but will not bubble any exception:

```php
namespace App\Commands;

use App\User;
use ArtisanSdk\Contract\Buses\Transactional;
use ArtisanSdk\CQRS\Command;

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

#### Silencing After Events With Abort

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

#### Checking If a Command Was Aborted

The `abort()` and `aborted()` methods are public methods of the command and can
also be used in circumstances where you might want to abort multiple commands in
a command pool based on when one command in the pool is aborted. You can also use
the `aborted()` method to check if a command has been aborted to better determine
what to do with the command's result.

### How to Use a Command as an Event Handler

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
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
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
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\CQRS\Command;

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

    public function afterEvent()
    {
        return UserSaved::class;
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

use ArtisanSdk\CQRS\Command;

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

### How to Queue a Command as a Job

In the case above, we're sending an email and this is often considered a background
process that is not critical to response success. Usually a queued job would be
used in this case. If you think about it though, a job is really just the definition
of an event and it's handler which is queued for later execution rather than
immediate execution. Since commands can be these self-executing event handlers,
the handler can also be queued as a job instead. This package makes it trivial to
queue the handler by simply implementing the `ArtisanSdk\Contract\CQRS\Queueable` interface
and adding the `ArtisanSdk\CQRS\Concerns\Queues` trait on the command you want to
be queued and support queue interactions:

```php
namespace App\Commands;

use ArtisanSdk\CQRS\Command;
use ArtisanSdk\CQRS\Triats\Queue;
use ArtisanSdk\Contract\CQRS\Queuable;

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

### How to Run a Command on a Queue as a Job

While it's more common to have an event handler be queued since events are by
nature asynchronous, some commands also lend themselves to background processing.
These commands are not ran but rather queued. So this package makes it trivial
to explicitly queue a command that implements `ArtisanSdk\Contract\CQRS\Queueable`:

```php
$job = App\Commands\SendUserWelcomeEmail::make()
    ->email('johndoe@example.com')
    ->queue();
```

Instead of calling `run()` on the command, you simply call `queue()`. The magic
of this method is that the `ArtisanSdk\CQRS\Builder` class is wrapping the queueable
command to pass the arguments to a generic `ArtisanSdk\Contract\Event\Event`
implementation. This event is a polyfill for the real underlying
`ArtisanSdk\Contract\CQRS\Queueable::queue($event)` method.

While the dispatcher handles all the indirection automatically, it can be summarized
as having accomplished the same as manually constructing and calling the following:

```php
$job = (new App\Commands\SendUserWelcomeEmail())
    ->queue(new ArtisanSdk\CQRS\Events\Event([
        'email' => 'johndoe@example.com',
    ]));
```

The pending job is returned and the framework dispatcher will push to the queue
when the object is destructed. Having access to the job allows for further
customization of the job prior to dispatch including calling familiar methods
like `onConnection`, `onQueue`, `delay`, and `chain`.

### How to Invalidate Queries from Commands

<span style="color:red">Documenation in progress. Please excuse the mess and consider contributing a pull request to improve the documentation.</span>

## Queries

A query implements the `ArtisanSdk\Contract\Query` interface which makes it
both invokable and runnable, therefore indistinguishable from a command. The
intended use of a query is to perform some sort of "read" operation or get a
result from a data store. An asynchronous query would return a promise while a
synchronous command would block program execution until the result is returned.

### How to Create a Query

A basic example of using a query is to create a class that extends the
`ArtisanSdk\CQRS\Query` class. This abstract class forwards `__invoke()` to
`run()`. The class also includes a shorthand `get()` method which forwards to
`run()` to make it feel more similar to working with the `DB::table()->get()` or
`Eloquent::query()->get()` method. implementing the `builder()` method returning
whatever query builder you want to be executed by the `get()` method. So
implementing a `run()` method that returns query results is all that is
necessary to take advantage of the query bus.

You can use the constructor method to inject any query dependencies such as an
Eloquent model, a service class, etc. Argument dependencies are implicitly
required and the caller must satisfy the requirements or else the developer must
throw an exception to ensure all required arguments are passed and validated
prior to execution of critical query logic.

> **Important:** While using Eloquent ORM may sanitize or escape arguments, this
> package makes no assumptions that the arguments passed to the query class are
> safe. Make sure you validate and sanitize values before executing against the
> data backend.

The abstract `ArtisanSdk\CQRS\Query` class actually assumes you are using
Laravel's Database ORM and query builder. The `run()` method therefore calls to
an abstract `builder()` to get the SQL builder. You will need to implement this
`builder()` method or stub it out if you are using, for example a RESTful API as
the query backend.

#### Flat File Implemenation

Assuming you had a `resources/lang/en/states.php` file containing a PHP array
of state abbreviations and names then the following query would be the minimal
implementation required. Note that the `builder()` method is stubbed out to satisfy
the parent class's abstract definition. Also note that we do not need to use a
database as the results can be loaded from a flat file on the system disk.

```php
namespace App\Queries;

use ArtisanSdk\CQRS\Query;

class GetStates extends Query
{
    public function builder()
    {
        // required to satisfy abstract parent
    }

    public function run()
    {
        return trans('states');
    }
}
```

Here's how you could call this query to get the states:

```php
$states = App\Queries\GetStates::make()->get();
```

#### HTTP API Implemenation

Again instead of a database, you could have your data backed by an HTTP API and
use an HTTP client like Guzzle to fetch the results:

```php
namespace App\Queries;

use ArtisanSdk\CQRS\Query;
use GuzzleHttp\Client as Guzzle;

class GeocodeIP extends Query
{
    protected $http;

    public function __construct(Guzzle $http)
    {
        $this->http = $http;
    }

    public function builder()
    {
        // required to satisfy abstract parent
    }

    public function run()
    {
        // Require the argument and validate as an IPv4 address
        $ip = $this->argument('ip', ['ipv4']);

        // Generate a URL to injected with the IP address
        $url = sprintf('https://freegeoip.app/json/%s', $ip);

        // Use Guzzle to get the geocoded response
        $response = $this->http->get($url);

        // Parse the JSON body of the response
        return json_decode($response->getBody()->getContent());
    }
}
```

In this example we use a dynamic query argument to build up the HTTP request when
we make the `get()` call to execute the request. Remember `get()` is forwarded to
our custom `run()` implementation so it all just works. Everything you know about
how fluently building up command arguments applies to queries as well.

```php
$result = App\Queries\GeocodeIP::make()
    ->ip('104.131.182.33')
    ->get();

echo $result->zip_code; // 07014
```

#### Database Implemenation

Back to that `builder()` method though. As mentioned, the package assumes you will
be using Eloquent ORM or at minimum a database abstraction and so the `builder()`
method is intended to be used to return a query builder. Therefore an implementation
of a model backed query would look like this:

```php
namespace App\Queries;

use App\User;
use ArtisanSdk\CQRS\Query;

class ListUsers extends Query
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function builder()
    {
        $query = $this->model->query();

        $order = $this->option('order', 'id', ['in:id,name,email,created_at,updated_at']);
        $sort = $this->option('sort', 'desc', ['in:asc,desc']);

        $query->orderBy($order, $sort);

        if( $keyword = $this->option('keyword', null, ['string', 'max:64']) ) {
            $this->scopeKeyword($query, $keyword);
        }

        return $query;
    }

    // This method could be called anything, but naming it similar to Eloquent
    // helps clarify the intent of such builder abstractions to protected methods.
    protected scopeKeyword($query, string $keyword)
    {
        $wildcard = sprintf('%%s%', $keyword);

        return $query->where(function($query) use ($wildcard) {
            return $query
                ->orWhere('name', 'LIKE', $wildcard)
                ->orWhere('email', 'LIKE', $wildcard);
        });
    }
}
```

We don't need to define the `run()` method because the parent class
automatically executes the required `$this->builder()->get()` call to return the
result of the query when we run it. Passing arguments to the query lets you
customize the results at call time.

```php
// Get the users with default arguments: sort desc by name
$users = App\Queries\ListUsers::make()->get();

// Get the users using custom arguments which are validated in the builder
$users = App\Queries\ListUsers::make()
    ->order('name')
    ->sort('asc')
    ->keyword('john')
    ->get();
```

### How to Get Query Results

The base query implements `get()` but also implements the convenient method of `paginate()`.

```php
// Get the ?page=# results of users with only the name and email columns
$paginator = App\Queries\ListUsers::make()->paginate(10, ['name', 'email']);
```

Furthermore if you need to inspect the query you can call `toSql()` instead of `get()`
or `builder()` directly to customize the query further for one-off query executions:

```php
// select * from `users` order by `name` desc
$sql = App\Queries\ListUsers::make()
    ->order('name')
    ->toSql();

// Bypass the run() method and execute against the builder directly
$users = App\Queries\ListUsers::make()
    ->order('name')
    ->builder()
    ->limit(10)
    ->get();

// Customize the builder outside of the query
$query = App\Queries\ListUsers::make();
$query->order('name');
$builder = $query->builder(); // get the builder outside of the query
$builder->whereIn('id', [1, 2, 3]); // a customization to the query
$users = $query->get(); // since $builder is referenced, query executes against customized builder
```

It is common practice to create base classes to help with common queries
involving just one result including expanding the interface to include `first()`
or `firstOrFail()` among other query execution methods.

```php
namespace App\Queries;

use App\User;
use ArtisanSdk\CQRS\Query;

class FindUserByEmail extends Query
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function builder()
    {
        return $this->model->query()
            ->where('email', $this->argument('email', ['email']));
    }

    public function run()
    {
        return $this->builder()->first();
    }

    public function firstOrFail()
    {
        return $this->builder()->firstOrFail();
    }

    public static function find(string $email)
    {
        return static::make()->email($email)->run();
    }

    public static function findOrFail(string $email)
    {
        return static::make()->email($email)->firstOrFail();
    }
}
```

There are a lot of ways to run this query including:

```php
$user = ArtisanSdk\CQRS\Dispatcher::make()
    ->query(App\Queries\FindUserByEmail::class)
    ->email('johndoe@example.com')
    ->run(); // or get()

$user = App\Queries\FindUserByEmail::make()
    ->email('johndoe@example.com')
    ->get();

// Throw Illuminate\Database\Eloquent\ModelNotFoundException if not found
$user = App\Queries\FindUserByEmail::make()
    ->email('johndoe@example.com')
    ->firstOrFail();

// Returns null if not found
$user = App\Queries\FindUserByEmail::find('johndoe@example.com');

// Throw Illuminate\Database\Eloquent\ModelNotFoundException if not found
$user = App\Queries\FindUserByEmail::findOrFail('johndoe@example.com');
```

A query can also be executed from within a controller or any service that includes the uses the `ArtisanSdk\CQRS\Concerns\CQRS` trait:

```php
namespace App\Http\Controllers;

use App\Commands\FindUserByEmail;
use App\Http\Controllers\Controller;
use ArtisanSdk\CQRS\Concerns\CQRS;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use CQRS;

    public function show(Request $request, string $email)
    {
        return $this->query(FindUserByEmail::class)
            ->email($email)
            ->firstOrFail();
    }
}
```

### How to Create an Evented Query

Sometimes you want the rest of your code to be made aware that a particular
query was executed. You may want to execute some code before the query or after
the query based on the result of the query. Using the dispatcher this is
trivially done by simply implementing the `ArtisanSdk\Contract\Eventable`
interface on any query that should be evented:

```php
namespace App\Queries;

use App\Post;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\CQRS\Query;

class MostPopularPosts extends Query implements Eventable
{
    protected $model;

    public function __construct(Post $model)
    {
        $this->model = $model;
    }

    public function builder()
    {
        return $this->query()
            ->orderBy('views', 'desc')
            ->take($this->option('limit', 10, 'is_integer'));
    }
}
```

With the addition of the eventable contract implemented, an event will be fired
before and another after the command is ran. The before event will be given the
arguments passed to the query while the after event will be given the results of
the query itself. The event fired is an instance of `ArtisanSdk\CQRS\Events\Event`.

As an example use case for the above query, the post authors could be notified
that their post is now being featured on the website using an after event
handler. Alternatively instrumentation could be started prior to the execution
and then captured after in the after event as the elapsed time the query took to
execute.

All other event bus behaviors relating to eventable commands also apply to commands.
See documentation on eventable commands for more details.

### How to Create a Cached Query

Maybe you want the results of a query to be cached since the result does not
change very often given the same query arguments. This package makes that a
trivial effort by simply implementing `ArtisanSdk\Contract\Cacheable` and
setting a `public $ttl` property, set in seconds, on the query class. The query
bus will handle all the cache key creation and cache busting using the default
cache drivers of Laravel.

```php
namespace App\Queries;

use App\Post;
use ArtisanSdk\Contract\Cacheable;
use ArtisanSdk\CQRS\Query;

class MostPopularPosts extends Query implements Cacheable
{
    public $ttl = 60 * 60 * 24 * 7; // 1 week cache

    // ... same logic as above
}
```

You can also dynamically call `->ttl($seconds)` on the query builder to customize
the TTL of the cache results when querying. You can customize `public $key` property
to set a custom key for the query but by default the value will be generated based
on a hash of the query itself. This makes unique queries cacheable under separate
auto-generated keys.

### How to Bust a Cached Query

While caching is great, sometimes you need to bypass the cache or clear the cache.

```php
// Get the results and cache them for future query execution
$posts = MostPopularPosts::make()->get();

// Secondary calls return the cached results
$cached = MostPopularPosts::make()->get();

// Bust the cache then get the results
$busted = MostPopularPosts::make()->busted()->get();

// This is shorthand for cache busted results
$busted = MostPopularPosts::make()->fresh();
```

See the `ArtisanSdk\CQRS\Buses\Cached` class for more public methods that can be
used to customize the query's caching mechanisms including bypassing cache,
setting a custom key, using tag based caches, and using a different cache
driver. The cache bus is probably the most compelling reason to use the query
bus when using an Eloquent model because while Eloquent models are Active Record
implementations with lots of query builder capabilities, they don't handle
domain argument validation nor caching out of the box and with ease.

## Events

### How Auto-resolution of Events Work

Event naming follows a convention of conjugating the present imperative tense
of the command name into a progressive future tense before event and a past tense
after event name. This is handled by the `Evented` wrapper and specifically by
the `resolveProgressiveTense()` and `resolvePastTense()` methods. Using a regex
mapping between common endings for action verbs, the command name can be transformed
fairly reliably. For example "create" becomes "creating" and "created". If no
conjugation can be found to map to then the resolver will default to "executing"
and "executed" as generic event names.

> **Help Wanted:** If you come across a conjugation case that could use improving
please take a look at `Evented::$progressiveMap` and `Evented::$pastMap` and
submit an issue or pull request with recommended changes for your use case.

The auto-resolution logic is not perfect, so you'll still need to customize
your event names from time to time and this package provides that functionality.

### How to Customize the Before and After Events

Sometimes you'll use a command name that is non-conventional or is simply hard
to conjugate event names for because of the weirdness of the English language.
In these cases (and in all cases where explicitness is preferred) you can add
the `beforeEvent` and `afterEvent` methods to an `Eventable` command. The following
illustrates how to customize the before and after events for a custom event
naming convention:

```php
namespace App\Commands;

use App\Events\NewPasswordSet;
use App\Events\ChangingPassword;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\CQRS\Command;

class ChangePassword extends Command implements Eventable
{
    public function beforeEvent(array $arguments)
    {
        return ChangingPassword::class;
    }

    public function run()
    {
        $user = $this->argument('user');
        $user->password = $this->argument('password');

        return $this->save($user);
    }

    public function afterEvent($result)
    {
        return NewPasswordSet::class;
    }
}
```

All it takes to modify the event used is to return the class name for the event
as a string. Alternatively if you want to construct the event yourself or need
to perform event switching based on the result of the command's execution, then
you can inspect the `$arguments` passed to the before event or the `$result` passed
to the after event and simply return an event object. If you do not compose the
event objects yourself then the convention is that the arguments and results are
injected into the constructor of the event class referenced.

Again this all illustrates that it's possible to customize the events. In practice
it would be recommended instead to follow a simpler naming convention such that
the command would be `App\Commands\Password\Change` and the events would be
`App\Events\Password\Changing` and `App\Events\Password\Changed` instead.

### Recommended Conventions for Command and Event Naming

While you are free to use the `beforeEvent()` and `afterEvent()` methods to customize
the dispatching of any event to suit a namespace or naming convention of your choice,
it is often easier to follow a reasonable convention and let the auto-resolution
do its thing. The following is a recommended convention for naming your commands
and before and after events using namespacing to delineate the classes:

- Commands should be one word action verbs written in present imperative tense
- Queries can be worded like commands or as a noun that defines the result set
- Events should be progressive tense (before events) and past tense (after
  events) conjugates of the command name
- Namespaces should be used for uniqueness when multiple classes otherwise have
  the same name.

These basic rules are further explained below:

Commands should be worded in a present imperative tense following the form of
an action. The command should be the action only and the namespace should organize
the logical use of the action. For example naming a command that registers the user
`RegisterUser` consider naming it simply `Register`. The arguments to the command
are a name and email and not a user model after all so if anything it would be
`RegisterWithNameAndEmail` and the returned value would be the user model.

Now to distinguish this register command from say registering a team or other domain
model, consider using the namespace. You could use `App\Commands\User` as the name
space which results in `App\Commands\User\Register` which follows a logical grouping
of related classes under the `App\Commands` namespace. Or you could use a service
oriented grouping of the class under `App\User\Commands\Register` which creates
a service boundary under the `App\User` namespace.

Now it follows that query gets something so it likewise is a command but can instead
be worded as a more concrete synonym for abstract `Query` and generic "get" such
as `App\Queries\User\Find` or `App\Queries\User\Search`. You are after all finding
the one user or searching for a collection of users and getting the results of that
query. You can also organize under a service boundary like `App\User\Queries\Find`
and `App\User\Queries\Search` if you rather.

You may also find it more fluent to name your queries based on the result. For
example you might want to get the recently registered users as a standard query.
This could be `App\Queries\User\RecentlyRegistered` or `App\User\Queries\RecentlyRegistered`.
You might decide to parametize the column such that it's just `Recent` instead
so then it can be used for recently registered, recently updated, etc. In following
this convention you might consider the queries as `GetRecentlyRegistered` and
instead just drop the prefix `Get` from the queries since the fluent code would
be written with `get()` in the syntax: `$this->query(RecentlyRegistered::class)->get()`.

If a command or query is evented then the events are auto resolved unless customized
with the `beforeEvent()` and `afterEvent()` methods. To help auto-resolution of
events, first make sure that the command is a single action word such as `Create`,
`Register`, `Modify`, etc. Again make sure it's worded in the present tense. Since
a before event is indicative of something about to happen, or in an async system
that is happening, it makes sense that before events are transformed from the
present tense command name to a progressive tense event name. So the `Register`
command fires a `Registering` event when it starts.

Furthermore when the command is done, the action is complete and the after event
should therefore represent what happened. The after events are transformed from
the present tense command name to a past tense event name. So the `Register`
command fires a `Registered` event when it is done. The same goes for strange
commands like `Modify` which transforms into before and after events of `Modifying`
and `Modified`. The auto-resolution logic works pretty well but doesn't always
get the event names right so always log your events during development to verify
what is being fired and that your commands are firing the right events.

Sometimes you'll have awkward command names like `App\Commands\User\SetStatus`.
While you could try to figure out how to namespace it such that the command were
`App\Commands\User\Status\Set` that often leads to unnecessary and artificial
expansion of the code base in a way the domain doesn't really care about. Plus
auto-resolution will get the past tense sort of wrong with `Setted` anyways.
Commands like `SetStatus` are still worded in the present tense and so the natural
progressive tense event name would be `SettingStatus` and the past tense event
would be `StatusSet`. As you can see the before event conjugates the verb
and keeps it in front of the noun, while the after event places it after the noun.
The weirdness of English makes the past and present tense verb form of "set" the
same and that is something auto-resolution cannot work out. Therefore you'll need
to define these events if needed yourself using `beforeEvent()` and `afterEvent()`
methods on your command.

A past event can therefore become argument to another command such that domain
logic can be encoded with "When [past event] then [present command]" rules.
For example, "When user registered then send activation email". This aids in
understanding that just because an event has been fired (e.g.: user registered)
doesn't mean that the command handler for the event (e.g.: send activation email)
has to be executed immediately. This delayed or deferred (technically queued)
command still takes the past event payload as present argument to it's own
deferred execution. It is therefore possible to completely ignore the return
value of a command and build an evented system that relies on the after events
instead of the command responses to continue program execution in an async style.

You may also find that you would prefer to name the actual root or aggregate model
simply `Model` which then requires further separation of the namespace to indicate
which model it is. For example instead of `App\User` you might organize as
`App\Models\User` or `App\User\Models\User`. When you have a lot of models however
it can be hard to see where the service boundary is between the `User` model aggregate
and all the related models. Therefore it would be better to organize as
`App\Models\User\User` and to remove the redundancy for `User` simplify to
`App\Models\User\Model`. In the `App\User\Models` case the aggregate and all
related models are organized under the `App\User\Models` service boundary so the
only reason to rename `App\User\Models\User` to `App\User\Models\Model` is to
highlight that that model is the root aggregate for the `App\User` service boundary.

Using a logical grouping of classes into namespaces (a common web app architecture
convention) would look like:

```
App
├─ Commands
    └─ User
        └─ Register
├─ Events
    └─ User
        ├─ Registered
        └─ Registering
├─ Models
    └─ User
        └─ Model
└─ Queries
    └─ User
        ├─ Find
        └─ Search
```

Alternatively a grouping of classes into namespaces around the service boundary
(a service-oriented architecture convention) would look like:

```
App
└─ User
    ├─ Commands
        └─ Register
    ├─ Events
        ├─ Registered
        └─ Registering
    ├─ Models
        └─ Model
    └─ Queries
        ├─ Find
        └─ Search
```

This package doesn't care how you organize things but you might find that organizing
into service boundaries will help reduce naming and organizing decisions and give
you clean separation for later service packaging.

## Concerns

The package's primary functionality is exposed as a set of base classes but these
classes are composed from a set of base traits. You can use these traits directly
in your application code even where CQRS may not be fully needed but the traits
prove to be a useful and consistent API for your application.

### Using CQRS in Your Classes

`ArtisanSdk\CQRS\Concerns\Arguments` is a trait that provides arguments and options
to a class including all the relevant validation logic and default resolvers.
The public methods of the trait are:

- `Arguments::arguments($arguments)` to get or set the arguments fluently
- `Arguments::argument($name, $validator)` to get an argument and validate it
- `Arguments::option($name, $default, $validator)` to get an optional argument and provide a default
- `Arguments::hasOption($name)` to check if the optional argument is present

`ArtisanSdk\CQRS\Concerns\CQRS` is the trait that provides the main interactive
API for the CQRS pattern. This is the trait that is typically included on a controller,
console command, or other class in order to directly dispatch commands using the
command builder and dispatcher. The usable methods (most are protected) of the trait are:

- `CQRS::dispatcher()` gets an instance of the `Dispatcher`. Instances are not singletons
  so every command that is dispatched is ran through an unique dispatcher (command bus).
  This is typically used like `$this->dispatcher()->dispatch($class)->run()` to compose
  the runnable class then run it. It can also be used to dynamically forward events
  like `$this->dispatcher()->creating($user)` which will fire a `Creating` event
  with the user as argument.
- `CQRS::call($class, $arguments)` directly composes then runs the class with the
  passed arguments.
- `CQRS::command($class)` to compose a command using the dispatcher but not run it (use `call()` instead).
- `CQRS::query($class)` to compose a query using the dispatcher but not run it (use `call()` instead).
- `CQRS::event($event, $payload)` to compose an after event with the payload and fire it using the dispatcher.
- `CQRS::until($event, $payload)` to compose a before event with the payload and fire it using the dispatcher.

`ArtisanSdk\CQRS\Concerns\Handle` is a trait that can be used by commands to implement
the `ArtisanSdk\Contract\CQRS\Handler` interface such that an event object may be passed
to the `handle()` method of a command and the command be ran through the command
dispatcher using the properties of the event as the arguments. Additionally if the
command is queueable then the execution of the command will be deferred as a queued
job instead. When the job is resolved out of the queue, the command will be directly
invoked, bypassing the handler yet still using the event properties as arguments.

`ArtisanSdk\CQRS\Concerns\Queues` is a wrapper trait for Laravel compatibility of
making an event or command behave like a queued job. It also lets the command interact
with the command much like a queued job can. The intended use for this trait is
to make the class it is used on a queuable job. See Laravel's documentation on
how to customize properties such as `$connection`, `$queue`, and `$delay` or
to perform chaining of commands as queued jobs.

`ArtisanSdk\CQRS\Concerns\Save` is a trait that helps with saving of Eloquent models,
especially self-validating models like [`artisansdk\model`](http://github.com/artisansdk/model) provides. It simply provides
a `save($model)` public method which ensures that the model is saved or throws an
exception and if saved will return the saved model. See also [Saving Models Within Commands](#saving-models-within-commands).

`ArtisanSdk\CQRS\Concerns\Silencer` is a trait that the prevents the firing of events
when a command or query is ran. The public methods of the trait are:

- `Silencer::silence()`: set the silence flag on the command so that events are
  not fired.
- `Silence::silenced()`: a boolean check to see if the command is silenced. This
  is used by the evented command wrapper to determine if events should be fired.
- `Silence::silently()`: a shorthand method for `$command->silence()->run()` such
  that you can silently run a command with just `$command->silently()`.

### Using Argument Validators

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
use ArtisanSdk\CQRS\Command;
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

### Using Option Defaults

The following code demonstrates the use of an option instead of an argument. Based
on the presence of the option alone (a flag essentially) you could perform some
guarded code or based on explicit check of the option's value if present. In the
following example, the default behavior if the option is not set is that the
invoice is not saved:

```php
namespace App\Commands;

use App\Invoice;
use ArtisanSdk\CQRS\Command;

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
use ArtisanSdk\CQRS\Command;

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
use ArtisanSdk\CQRS\Command;

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

### Saving Models Within Commands

If you use the `ArtisanSdk\CQRS\Concerns\Save` trait or the `ArtisanSdk\CQRS\Command`
which includes this trait, then you can quickly save Eloquent models including
self-validating models like those provided by [`artisansdk\model`](http://github.com/artisansdk/model).
Simply call `save()` from within the command or controller and pass in the model
that should be saved. If the model does not save because it cannot be validated,
then an exception will be raised. If the model can be saved then the saved instance
is returned. The use of this helper trait can streamline commands considerably
and ensure that saves are being performed consistently.

```php
namespace App\Commands;

use ArtisanSdk\CQRS\Command;

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

### Using the Silencer

Sometimes you just don't want your evented commands to fire events. As an example,
say that you were sending out an email using `SendPasswordResetEmail` command which
is normally triggered by the `UserPasswordReset` event. Let's say however that during
user registration, the `ResetUserPassword` command is called and yet you do not
want to send out the normal email for password resets. Instead you wish to trigger
the logic of resetting a password for an account and instead use `SendAccountActivationEmail`
command to send an account activation in response to `UserRegistered` event. This
is all possible using the `ArtisanSdk\CQRS\Concerns\Silencer` trait which is already
used by the base `ArtisanSdk\CQRS\Command` class.

In order to accomplish the above example you might write the following:

```php
namespace App\Commands;

use App\User;
use App\Commands\ResetUserPassword;
use App\Events\UserPasswordReset;
use App\Events\UserRegistered;
use ArtisanSdk\CQRS\Command;
use ArtisanSdk\Contract\Eventable;

class RegisterUser extends Command implements Eventable
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function run()
    {
        $user = new User();
        $user->email = $this->argument('email');
        $this->save($user);

        return $this->command(ResetUserPassword::class)
            ->user($user)
            ->silently();
    }

    public function afterEvent()
    {
        return UserRegistered::class;
    }
}

class ResetUserPassword extends Command implements Eventable
{
    public function run()
    {
        $user = $this->argument('user');
        $user->password = null;

        return $this->save($user);
    }

    public function afterEvent()
    {
        return ResetUserPassword::class;
    }
}
```

## Extending

### Using Macros on the Builder

In the `App\Providers\AppServiceProvider@boot` (a Laravel default location):

```php
ArtisanSdk\CQRS\Builder::macro('attempt');
```

Now you can call `attempt()` on any invokable that supports the method and any arguments
passed will be forwarded.

The `ArtisanSdk\CQRS\Builder::macro()` method supports a second argument however
that accepts a callable or closure. Closures passed have `$this` bound into the
context of the builder and therefore behave exactly as if they were a method
on the builder already with access to other protected methods of the builder
such as `forwardToBase()`. You can therefore customize the builder using closure
based macros:

```php
ArtisanSdk\CQRS\Builder::macro('attempt', function(...$arguments) {
    try {
        return $this->run();
    } catch (Exception $error) {
        throw new App\Exceptions\Error(sprintf($arguments[0], $error->getMessage()));
    }
});
```

From your app code you would then be able to attempt execution of any command and
return a contextual exception without writing ugly try/catch logic everywhere:

```php
$user = App\Commands\SaveUser::make()
    ->email('johndoe@example.com')
    ->attempt('User could not be saved: %s');
```

### Using Mixins on the Builder

<span style="color:red">Documenation in progress. Please excuse the mess and consider contributing a pull request to improve the documentation.</span>

# Running the Tests

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

# Licensing

Copyright (c) 2018-2019 [Artisans Collaborative](https://artisanscollaborative.com)

This package is released under the MIT license. Please see the LICENSE file
distributed with every copy of the code for commercial licensing terms.
