<?php

namespace App\Database\Connections;

use App\Database\Query\Grammars\AS400Grammar;
use Illuminate\Database\Connection;

class AS400Connection extends Connection
{
    protected function getDefaultQueryGrammar(): AS400Grammar
    {
        $grammar = new AS400Grammar($this);
        $grammar->setTablePrefix($this->getTablePrefix());

        return $grammar;
    }
}
