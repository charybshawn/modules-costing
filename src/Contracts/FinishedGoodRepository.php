<?php

namespace Cultpantry\Costing\Contracts;

/**
 * Looks up FinishedGood records on the host app's behalf -- bound in the
 * host's own service provider to whatever model actually represents a
 * sellable/stockable item there (e.g. App\Models\Product). This package
 * never queries that table directly.
 */
interface FinishedGoodRepository
{
    /**
     * @return array<int, FinishedGood> up to $limit matches, ordered by
     *     relevance/label. An empty $query returns the first $limit results
     *     (used when a picker's dropdown opens with nothing typed yet).
     */
    public function search(string $query, int $limit = 20): array;

    public function find(int $id): ?FinishedGood;
}
