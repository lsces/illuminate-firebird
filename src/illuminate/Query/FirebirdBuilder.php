<?php

namespace Firebird\Illuminate\Query;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

class FirebirdBuilder extends QueryBuilder
{
    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return parent::count() > 0;
    }

    /**
     * Add a from stored procedure clause to the query builder.
     *
     * @param string $procedure
     * @param array  $values
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function fromProcedure(string $procedure, array $values = [])
    {
        $compiledProcedure = $this->grammar->compileProcedure($this, $procedure, $values);

        // Remove any expressions from the values array, as they will have
        // already been evaluated by the grammar's parameterize() function.
        $values = array_filter($values, function($value) {
            return ! $this->grammar->isExpression($value);
        });

        $this->fromRaw($compiledProcedure, array_values($values));

        return $this;
    }

    /**
     * Insert new records into the database.
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Firebird's RETURNING clause only supports single-row inserts, so loop for multi-row.
        if (count($values) > 1 && is_array(reset($values))) {
            $results = [];
            foreach ($values as $row) {
                $results[] = parent::insert($row);
            }
            return end($results);
        }

        return parent::insert($values);
    }
    public function where($column, $operator = NULL, $value = NULL, $boolean = 'and')
    {
        // Not sure what this was intended to fix, but target hidden for now
        if (! str($operator)->contains('hide', true)) {
            return parent::where($column, $operator, $value, $boolean);
        }

        // when is search covert to upper case column and value at database level
        $wrapped = $this->grammar->wrap($column);
        return $boolean === 'and'
            ? parent::whereRaw("UPPER($wrapped) LIKE UPPER(?)", [$value])
            : parent::orWhereRaw("UPPER($wrapped) LIKE UPPER(?)", [$value]);
    }

    /**
     * Retrieve column values from rows represented as objects.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     * @return Collection
     */
    protected function pluckFromObjectColumn($queryResult, $column, $key)
    {
        $results = [];
        $column  = str_replace('"', '', $column);

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row->$column;
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row->$key] = $row->$column;
            }
        }

        return new Collection($results);
    }

    /**
     * Retrieve column values from rows represented as arrays.
     *
     * @param  array  $queryResult
     * @param  string  $column
     * @param  string  $key
     * @return Collection
     */
    protected function pluckFromArrayColumn($queryResult, $column, $key)
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row[$column];
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row[$key]] = $row[$column];
            }
        }

        return new Collection($results);
    }
}
