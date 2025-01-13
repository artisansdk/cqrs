<?php

declare(strict_types=1);

namespace ArtisanSdk\CQRS\Concerns;

trait Silencer
{
    /**
     * The silenced status of the command.
     *
     * @var bool
     */
    protected $silenced = false;

    /**
     * Silence the command.
     *
     * @return self
     */
    public function silence()
    {
        $this->silenced = true;

        return $this;
    }

    /**
     * Was the command silenced?
     *
     * @return bool
     */
    public function silenced()
    {
        return $this->silenced;
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
}
