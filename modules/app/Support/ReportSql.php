<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** SQL expressions use fixed, developer-owned column names only. */
class ReportSql
{
    public static function elapsed(string $start, string $end, string $unit = 'HOUR'): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            $factor = $unit === 'MINUTE' ? 1440 : 24;
            return "((julianday($end) - julianday($start)) * $factor)";
        }
        return "TIMESTAMPDIFF($unit, $start, $end)";
    }

    public static function responseDeadline(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "datetime(created_at, '+24 hours')" : 'DATE_ADD(created_at, INTERVAL 24 HOUR)';
    }

    public static function customerName(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "(COALESCE(customers.first_name, '') || ' ' || COALESCE(customers.last_name, ''))"
            : "CONCAT(COALESCE(customers.first_name, ''), ' ', COALESCE(customers.last_name, ''))";
    }
}
