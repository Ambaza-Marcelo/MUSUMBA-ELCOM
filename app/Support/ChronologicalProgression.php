<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Tri chronologique stable + conversions numériques/dates
 * pour stocks et comptabilité (colonnes souvent en string).
 */
class ChronologicalProgression
{
    /**
     * Cast sûr vers float (virgule décimale, espaces, null).
     *
     * @param  mixed  $value
     */
    public static function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = str_replace([' ', "\xC2\xA0", ','], ['', '', '.'], trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }

        return (float) $normalized;
    }

    /**
     * @param  mixed  $value
     */
    public static function toInt($value): int
    {
        return (int) round(self::toFloat($value));
    }

    /**
     * @param  mixed  $value
     */
    public static function parseDateTime($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clé de tri YmdHis (14 chiffres). Absent => 000... pour placer en tête,
     * ou 999... si $missingLast = true.
     */
    public static function timestampKey($value, bool $missingLast = false): string
    {
        $date = self::parseDateTime($value);
        if (!$date) {
            return $missingLast ? '99999999999999' : '00000000000000';
        }

        return $date->format('YmdHis');
    }

    /**
     * Clé composite : date opération → created_at → id.
     *
     * @param  object|array  $row
     */
    public static function sortKey($row, string $dateAttribute = 'date', string $createdAttribute = 'created_at'): string
    {
        $dateValue = self::attr($row, $dateAttribute);
        $createdValue = self::attr($row, $createdAttribute);

        // Si pas de date métier, basculer sur created_at comme horodatage principal.
        if ($dateValue === null || $dateValue === '') {
            $primary = self::timestampKey($createdValue, false);
            $secondary = '00000000000000';
        } else {
            $primary = self::timestampKey($dateValue, false);
            $secondary = self::timestampKey($createdValue, false);
        }

        $id = self::toInt(self::attr($row, 'id'));

        return sprintf('%s|%s|%012d', $primary, $secondary, $id);
    }

    /**
     * @param  Collection|iterable  $rows
     */
    public static function sort($rows, string $direction = 'asc', string $dateAttribute = 'date', string $createdAttribute = 'created_at'): Collection
    {
        $collection = $rows instanceof Collection ? $rows : collect($rows);

        $sorted = $collection->sortBy(function ($row) use ($dateAttribute, $createdAttribute) {
            return self::sortKey($row, $dateAttribute, $createdAttribute);
        })->values();

        if (strtolower($direction) === 'desc') {
            return $sorted->reverse()->values();
        }

        return $sorted;
    }

    /**
     * Ordre d'insertion réel en base : created_at → id.
     *
     * @param  Collection|iterable  $rows
     */
    public static function sortByInsertion($rows, string $direction = 'asc'): Collection
    {
        $collection = $rows instanceof Collection ? $rows : collect($rows);

        $sorted = $collection->sortBy(function ($row) {
            $created = self::timestampKey(self::attr($row, 'created_at'), false);
            $id = self::toInt(self::attr($row, 'id'));

            return sprintf('%s|%012d', $created, $id);
        })->values();

        if (strtolower($direction) === 'desc') {
            return $sorted->reverse()->values();
        }

        return $sorted;
    }

    /**
     * Ordre SQL chronologique (date → created_at → id).
     * Si la colonne date n'existe pas / est créée_at, passer date_column = created_at.
     */
    public static function applyQueryOrder(
        Builder $query,
        string $direction = 'asc',
        string $dateColumn = 'date',
        string $createdColumn = 'created_at',
        string $idColumn = 'id'
    ): Builder {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        if ($dateColumn === $createdColumn) {
            return $query
                ->orderBy($createdColumn, $direction)
                ->orderBy($idColumn, $direction);
        }

        return $query
            ->orderBy($dateColumn, $direction)
            ->orderBy($createdColumn, $direction)
            ->orderBy($idColumn, $direction);
    }

    /**
     * Ordre SQL d'insertion : created_at → id.
     */
    public static function applyInsertionQueryOrder(
        Builder $query,
        string $direction = 'asc',
        string $createdColumn = 'created_at',
        string $idColumn = 'id'
    ): Builder {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderBy($createdColumn, $direction)
            ->orderBy($idColumn, $direction);
    }

    /**
     * @param  object|array  $row
     * @param  mixed  $default
     * @return mixed
     */
    public static function attr($row, string $key, $default = null)
    {
        if (is_array($row)) {
            return array_key_exists($key, $row) ? $row[$key] : $default;
        }

        return isset($row->{$key}) ? $row->{$key} : $default;
    }

    /**
     * @param  object|array  $row
     * @param  mixed  $value
     */
    public static function setAttr($row, string $key, $value)
    {
        if (is_array($row)) {
            $row[$key] = $value;

            return $row;
        }

        $row->{$key} = $value;

        return $row;
    }
}
