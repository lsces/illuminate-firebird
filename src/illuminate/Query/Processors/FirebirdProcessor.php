<?php

namespace Firebird\Illuminate\Query\Processors;

use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Database\Query\Builder;

class FirebirdProcessor extends Processor
{
    /**
     * Process the results of a column listing query.
     *
     * @param  array  $results
     * @return array
     */
    public function processColumns($results)
	{
		return array_map(function ($result) {
			return (object) [
				'name' => strtolower($result->name ),
//				'type_name' => strtolower($result['type_name'] ?? ''),
//				'type' => $this->getColumnType($result),
//				'collation' => $result['collation'] ?? null,
//				'nullable' => (bool) ($result['nullable'] ?? true),
//				'default' => $result['default'] ?? null,
//				'auto_increment' => (bool) ($result['auto_increment'] ?? false),
//				'comment' => $result['comment'] ?? null,
			];
		}, $results);
	}

    /**
     * Process an  "insert get ID" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  string  $sql
     * @param  array  $values
     * @param  string|null  $sequence
     * @return int
     */
    public function processInsertGetId(Builder $query, $sql, $values, $sequence = null)
    {
        $query->getConnection()->insert($sql, $values);

      	$id = $query->getConnection()->getLastInsertId();

        return is_numeric($id) ? (int) $id : $id;
    }
}
