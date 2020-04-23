<?php

namespace ArtisanSdk\CQRS\Buses;

use ArtisanSdk\Contract\Command as Contract;
use ArtisanSdk\Contract\Invokable;
use ArtisanSdk\Contract\Runnable;
use ArtisanSdk\CQRS\Concerns\Handle;
use ArtisanSdk\CQRS\Dispatcher;
use Illuminate\Support\Str;

/**
 * Evented Runnable Wrapper.
 */
class Evented implements Contract
{
    use Handle;

    /**
     * The underlying runnable this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Runnable
     */
    protected $runnable;

    /**
     * The runnable dispatcher.
     *
     * @var \ArtisanSdk\CQRS\Dispatcher
     */
    protected $dispatcher;

    /**
     * Map of present tense to progressive tense conjugations.
     *
     * @var array
     */
    protected $progressiveMap = [
        'ate'                => 'ating',
        'ish'                => 'ishing',
        'it'                 => 'itting',
        'ive'                => 'iving',
        'mpt'                => 'mpting',
        'n'                  => 'nning',
        'ost'                => 'osting',
        '([aeiou])d'         => '$1ding',
        '([aeiou][^aeiou])e' => '$1ing',
        '(n|dr)d'            => '$1ding',
        'e(ct|pt|r|d|l)'     => 'e$1ing',
    ];

    /**
     * Map of present tense to past tense conjugations.
     *
     * @var array
     */
    protected $pastMap = [
        'ind'            => 'ound',
        'ish'            => 'ished',
        'it'             => 'itted',
        'mpt'            => 'mpted',
        'n'              => 'nned',
        'ost'            => 'osted',
        '([^aeiou])e'    => '$1ed',
        '([aeiou])d'     => '$1ded',
        '(n|d|r)d'       => '$1ded',
        'e(ct|pt|r|d|l)' => 'e$1ed',
    ];

    /**
     * Inject the underlying Eventable that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Runnable $runnable
     * @param \ArtisanSdk\CQRS\Dispatcher   $dispatcher
     */
    public function __construct(Runnable $runnable, Dispatcher $dispatcher = null)
    {
        $this->runnable = $runnable;
        $this->dispatcher = $dispatcher ?? Dispatcher::make();
    }

    /**
     * Get the base most runnable.
     *
     * @return \ArtisanSdk\Contract\Invokable
     */
    public function toBase(): Invokable
    {
        return $this->runnable->toBase();
    }

    /**
     * Run the command silently.
     *
     * @return mixed
     */
    public function silently()
    {
        return $this->silence()->__invoke();
    }

    /**
     * Run the runnable and emit before and after events.
     *
     * @return mixed
     */
    public function run()
    {
        $this->before();

        $response = $this->runnable->run();

        $runnable = $this->toBase();
        if ( ! method_exists($runnable, 'aborted') || ! $runnable->aborted()) {
            $this->after($response);
        }

        return $response;
    }

    /**
     * Fire the before event.
     */
    protected function before()
    {
        if ($this->shouldBeSilenced()) {
            return;
        }

        $runnable = $this->toBase();
        if (method_exists($runnable, 'beforeEvent')) {
            $name = $runnable->beforeEvent($this->arguments());
            $event = is_string($name)
                ? $event = (new $name($this->arguments()))->event($name)
                : $name;

            $this->dispatcher->until($event);

            return;
        }

        $method = $this->resolveProgressiveTense(class_basename($runnable));

        $this->dispatcher->{$method}($runnable);
    }

    /**
     * Fire the after event.
     *
     * @param mixed $response
     */
    protected function after($response)
    {
        if ($this->shouldBeSilenced()) {
            return;
        }

        $runnable = $this->toBase();
        if (method_exists($runnable, 'afterEvent')) {
            $name = $runnable->afterEvent($response);
            $event = is_string($name)
                ? (new $name($response))->event($name)
                : $name;

            $this->dispatcher->event($event);

            return;
        }

        $method = $this->resolvePastTense(class_basename($runnable));

        $this->dispatcher->{$method}($response);
    }

    /**
     * Should the runnable command be silenced?
     *
     * @return bool
     */
    protected function shouldBeSilenced()
    {
        $runnable = $this->toBase();

        return method_exists($runnable, 'silenced')
            && $runnable->silenced();
    }

    /**
     * Resolve the progressive tense variation.
     *
     * @param string $command in present tense
     *
     * @return string
     */
    public function resolveProgressiveTense($command)
    {
        foreach ($this->progressiveMap as $present => $tense) {
            if (preg_match('/'.$present.'$/i', $command)) {
                return Str::camel(preg_replace('/'.$present.'$/i', $tense, $command));
            }
        }

        return 'executing';
    }

    /**
     * Resolve the past tense variation.
     *
     * @param string $command in present tense
     *
     * @return string
     */
    public function resolvePastTense($command)
    {
        foreach ($this->pastMap as $present => $tense) {
            if (preg_match('/'.$present.'$/i', $command)) {
                return Str::camel(preg_replace('/'.$present.'$/i', $tense, $command));
            }
        }

        return 'executed';
    }

    /**
     * Proxy calls to the underlying Eventable instance.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($method, $arguments = [])
    {
        $response = call_user_func_array([$this->runnable, $method], $arguments);

        if ($response === $this->runnable) {
            return $this;
        }

        return $response;
    }

    /**
     * Invoke the runnable.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }
}
