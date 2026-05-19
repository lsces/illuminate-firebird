<?php

namespace Firebird\Illuminate\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FirebirdGrammar extends Grammar
{
    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        // 'limit', - Handled in the compileColumns() method.
        // 'offset', - Handled in the compileColumns() method.
        'lock',
    ];

    /**
     * All of the available clause operators.
     *
     * @var array
     *
     * @link https://ib-aid.com/download/docs/firebird-language-reference-2.5/fblangref25-commons-predicates.html
     */
    protected $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'not between',
        'containing', 'not containing', 'starting with', 'not starting with',
        'similar to', 'not similar to', 'is distinct from', 'is not distinct from',
    ];

    /**
     * @param  Builder  $query
     * @param  array  $columns
     * @return string|null
     */
    protected function compileColumns(Builder $query, $columns)
    {
        // See superclass.
        if (! is_null($query->aggregate)) {
            return;
        }

        // In Firebird, the correct syntax for limiting and offsetting rows is
        // "select first [num_rows] skip [start_row] * from table". Laravel does
        // not support adding components between the "select" keyword and the
        // column names, so compile the limit and offset components here. Note
        // that they are commented out in the $selectComponents class variable.
        // Reference: http://mc-computing.com/Databases/Firebird/SQL.html

        $select = 'select ';

        if ($query->limit) {
            $select .= $this->compileLimit($query, $query->limit).' ';
        }

        if ($query->offset) {
            $select .= $this->compileOffset($query, $query->offset).' ';
        }

        if ($query->distinct) {
            $select .= 'distinct ';
        }

        return $select.$this->columnize($columns);
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        if (isset($query->joins) ) { // || isset($query->limit)) {
            return $this->compileDeleteWithJoinsOrLimit($query);
        }

        return parent::compileDelete($query);
    }

    /**
     * Compile a delete statement with joins or limit into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return string
     */
    protected function compileDeleteWithJoinsOrLimit(Builder $query)
    {
        $table = $this->wrapTable($query->from);
        $alias = last(preg_split('/\s+as\s+/i', $query->from));

        // Most tables follow the {table}_id convention; exceptions listed explicitly.
        $field = match ($alias) {
            'block_setting' => 'block_id',
            'places'        => 'p_id',
            default         => $alias . '_id',
        };

        $selectSql = $this->compileSelect($query->select($alias . '.' . $field));

        return "delete from {$table} where {$this->wrap($field)} in ({$selectSql})";
    }

    /**
     * Compile the "limit" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $limit
     * @return string
     */
    protected function compileLimit(Builder $query, $limit)
    {
        return 'first '.(int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  int  $offset
     * @return string
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return 'skip '.(int) $offset;
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RAND()';
    }

	/**
     * Compile an insert statement into SQL.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileInsert(Builder $query, array $values)
    {
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (! is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = (new Collection($values))
            ->map(fn ($record) => '('.$this->parameterize($record).')')
            ->implode(', ');

        return "insert into $table ($columns) values $parameters returning *";
	}

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        static $reserved = [
            'day', 'hour', 'index', 'minute', 'month', 'order', 'pending',
            'position', 'rows', 'second', 'type', 'value', 'version', 'year',
        ];

        if (in_array(strtolower($value), $reserved, true)) {
            return '"' . $value . '"';
        }

        return $value;
    }

    /**
     * Wrap a union subquery in parentheses.
     *
     * @param  string  $sql
     * @return string
     */
    protected function wrapUnion($sql)
    {
        return $sql;
    }

    /**
     * Compile a date based where clause.
     *
     * @param  string  $type
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function dateBasedWhere($type, Builder $query, $where)
    {
        $value = $this->parameter($where['value']);

        return 'EXTRACT('.$type.' FROM '.$this->wrap($where['column']).') '.$where['operator'].' '.$value;
    }

    /**
     * Compile SQL statement for a stored procedure.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $procedure
     * @param  array  $values
     * @return string
     */
    public function compileProcedure(Builder $query, $procedure, ?array $values = null)
    {
        $procedure = $this->wrap($procedure);

        return $procedure.' ('.$this->parameterize($values).')';
    }

    /**
     * Compile an aggregated select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        // Wrap `aggregate` in double quotes to ensure the resultset returns the
        // column name as a lowercase string. This resolves compatibility with
        // the framework's paginator.
        return Str::replaceLast(
            'as aggregate', 'as "aggregate"', parent::compileAggregate($query, $aggregate)
        );
    }
}
