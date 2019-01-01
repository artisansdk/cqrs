<?php

namespace ArtisanSdk\CQRS\Commands;

use ArtisanSdk\Contract\Command as Contract;
use ArtisanSdk\Contract\Eventable;
use ArtisanSdk\CQRS\Dispatcher;
use ArtisanSdk\CQRS\Traits\Handle;

/**
 * Evented Runnable Wrapper.
 */
class Evented implements Contract
{
    use Handle;

    /**
     * The underlying Eventable this class proxies to.
     *
     * @var \ArtisanSdk\Contract\Eventable
     */
    protected $eventable;

    /**
     * The eventable dispatcher.
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
        'ect'                => 'ecting',
        'ish'                => 'ishing',
        'it'                 => 'itting',
        'ive'                => 'iving',
        'n'                  => 'nning',
        'ost'                => 'osting',
        '([aeiou])d'         => '$1ding',
        '([aeiou][^aeiou])e' => '$1ing',
        '(n|dr)d'            => '$1ding',
        'e(ct|r|d|l)'        => 'e$1ing',
    ];

    /**
     * Map of present tense to past tense conjugations.
     *
     * @var array
     */
    protected $pastMap = [
        'ect'         => 'ected',
        'ind'         => 'ound',
        'ish'         => 'ished',
        'it'          => 'itted',
        'n'           => 'nned',
        'ost'         => 'osted',
        '([^aeiou])e' => '$1ed',
        '([aeiou])d'  => '$1ded',
        '(n|d|r)d'    => '$1ded',
        'e(ct|r|d|l)' => 'e$1ed',
    ];

    /**
     * Inject the underlying Eventable that this class proxies to.
     *
     * @param \ArtisanSdk\Contract\Eventable $eventable
     * @param \ArtisanSdk\CQRS\Dispatcher
     */
    public function __construct(Eventable $eventable, Dispatcher $dispather = null)
    {
        $this->eventable = $eventable;
        $this->dispatcher = $dispatcher ?? Dispatcher::make();
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
     * Run the eventable and emit before and after events.
     *
     * @return mixed
     */
    public function run()
    {
        $this->before();

        $response = $this->eventable->run();

        if ( ! method_exists($this->eventable, 'aborted') || ! $this->eventable->aborted()) {
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

        if (method_exists($this->eventable, 'beforeEvent')) {
            $name = $this->eventable->beforeEvent($this->arguments());
            $event = is_string($name)
                ? $event = (new $name($this->arguments()))->event($name)
                : $name;

            $this->dispatcher->until($event);

            return;
        }

        $method = $this->resolveProgressiveTense(class_basename($this->eventable));

        $this->dispatcher->{$method}($this->eventable);
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

        if (method_exists($this->eventable, 'afterEvent')) {
            $name = $this->eventable->afterEvent($response);
            $event = is_string($name)
                ? (new $name($response))->event($name)
                : $name;

            $this->dispatcher->event($event);

            return;
        }

        $method = $this->resolvePastTense(class_basename($this->eventable));

        $this->dispatcher->{$method}($response);
    }

    /**
     * Should the eventable command be silenced?
     *
     * @return bool
     */
    protected function shouldBeSilenced()
    {
        return method_exists($this->eventable, 'silenced')
            && $this->eventable->silenced();
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
                return camel_case(preg_replace('/'.$present.'$/i', $tense, $command));
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
                return camel_case(preg_replace('/'.$present.'$/i', $tense, $command));
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
        $response = call_user_func_array([$this->eventable, $method], $arguments);

        if ($response === $this->eventable) {
            return $this;
        }

        return $response;
    }

    /**
     * Invoke the eventable.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return $this->run();
    }
}
