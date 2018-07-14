<?php

namespace ArtisanSdk\CQRS\Tests\Fakes\Database;

use Closure;
use Illuminate\Database\ConnectionInterface;

class Connection implements ConnectionInterface
{
    /**
     * The number of transactions.
     *
     * @var int
     */
    protected $transactions = 0;

    /**
     * Begin a fluent query against a database table.
     *
     * @param string $table
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function table($table)
    {
    }

    /**
     * Get a new raw query expression.
     *
     * @param mixed $value
     *
     * @return \Illuminate\Database\Query\Expression
     */
    public function raw($value)
    {
    }

    /**
     * Run a select statement and return a single result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     */
    public function selectOne($query, $bindings = [])
    {
    }

    /**
     * Run a select statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function select($query, $bindings = [])
    {
    }

    /**
     * Run an insert statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function insert($query, $bindings = [])
    {
    }

    /**
     * Run an update statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function update($query, $bindings = [])
    {
    }

    /**
     * Run a delete statement against the database.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function delete($query, $bindings = [])
    {
    }

    /**
     * Execute an SQL statement and return the boolean result.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement($query, $bindings = [])
    {
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param string $query
     *
     * @return bool
     */
    public function unprepared($query)
    {
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param array $bindings
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @param \Closure $callback
     * @param int      $attempts
     *
     * @throws \Throwable
     *
     * @return mixed
     */
    public function transaction(Closure $callback, $attempts = 1)
    {
        return $callback();
    }

    /**
     * Start a new database transaction.
     */
    public function beginTransaction()
    {
        ++$this->transactions;
    }

    /**
     * Commit the active database transaction.
     */
    public function commit()
    {
        --$this->transactions;
    }

    /**
     * Rollback the active database transaction.
     */
    public function rollBack()
    {
        --$this->transactions;
    }

    /**
     * Get the number of active transactions.
     *
     * @return int
     */
    public function transactionLevel()
    {
        $this->transactions;
    }

    /**
     * Execute the given callback in "dry run" mode.
     *
     * @param \Closure $callback
     *
     * @return array
     */
    public function pretend(Closure $callback)
    {
        return $callback();
    }
}
