<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class Database
{
    public function all(string $sql, array $parameters = []): array
    {
        return array_map(static fn (object $row): array => (array) $row, DB::select($sql, $parameters));
    }

    public function first(string $sql, array $parameters = []): ?array
    {
        $row = DB::selectOne($sql, $parameters);

        return $row ? (array) $row : null;
    }

    public function scalar(string $sql, array $parameters = []): mixed
    {
        $row = $this->first($sql, $parameters);

        return $row === null ? null : reset($row);
    }

    public function execute(string $sql, array $parameters = []): int
    {
        return DB::affectingStatement($sql, $parameters);
    }

    public function insert(string $table, array $values): int
    {
        return (int) DB::table($table)->insertGetId($values);
    }

    public function update(string $table, int $id, array $values): int
    {
        return DB::table($table)->where('id', $id)->update($values);
    }

    public function transaction(callable $callback): mixed
    {
        return DB::transaction(fn () => $callback());
    }
}
