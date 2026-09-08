<?php

namespace Cultpantry\Costing\Contracts;

/**
 * One resolved finished-good record (e.g. a storefront product), as seen by
 * this package. The package never knows what concrete model backs this --
 * only the host app's FinishedGoodRepository implementation does -- so it
 * can credit/debit stock without ever referencing a stock_quantity column,
 * a stock-mutation action, or any host-specific Eloquent model.
 */
interface FinishedGood
{
    public function getId(): int;

    public function getLabel(): string;

    /**
     * Credits (adds) $units to this finished good's on-hand stock via
     * whatever the host app's own stock-mutation funnel is -- locking,
     * audit trail, and broadcasting are entirely the host's responsibility.
     *
     * @param  mixed  $actor  Opaque -- passed straight through to the host's
     *     own audit trail, never inspected by this package.
     * @param  array<string, mixed>  $metadata
     * @return int the resulting stock level
     */
    public function credit(int $units, string $reason, mixed $actor = null, array $metadata = [], ?string $note = null): int;

    /**
     * Debits (subtracts) $units. Same semantics as credit(), inverse
     * direction -- a separate named method rather than a signed delta so
     * call sites can't get the sign backwards.
     */
    public function debit(int $units, string $reason, mixed $actor = null, array $metadata = [], ?string $note = null): int;
}
