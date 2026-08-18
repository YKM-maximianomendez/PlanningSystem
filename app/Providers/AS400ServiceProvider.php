<?php

namespace App\Providers;

use App\Database\Connections\AS400Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PDO;

class AS400ServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        DB::extend('as400', function ($config) {
            $dsn = $config['dsn'];

            if (! empty($config['libl'])) {
                $dsn .= 'DBQ='.str_replace(',', ' ', $config['libl']).';';
            }

            $pdo = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_CASE => PDO::CASE_UPPER,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::ATTR_PERSISTENT => true,
                ]
            );

            return new AS400Connection(
                $pdo,
                $config['database'] ?? '',
                $config['prefix'] ?? '',
                $config
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
