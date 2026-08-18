<?php

namespace App\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;

class AS400Grammar extends Grammar
{
    protected function compileAnsiOffset(Builder $query, $components): string
    {
        $limit = (int) $query->limit;
        $offset = (int) $query->offset;

        if ($limit > 0 && $offset <= 0) {
            return "fetch first {$limit} rows only";
        }

        if ($offset > 0) {
            $sql = "offset {$offset} rows";
            if ($limit > 0) {
                $sql .= " fetch next {$limit} rows only";
            }

            return $sql;
        }

        return '';
    }
}
