<?php

namespace Modules\Marketplace\Application\Contracts;

/** Marketplace's read interface for Orders (module-architecture.md §2.5). */
interface ProductCatalog
{
    /**
     * Resolve a product + optional variant. For physical products without an explicit
     * variant the default (or first active) variant is used.
     */
    public function line(string $productUuid, ?string $variantUuid = null): ?PurchasableLine;

    /**
     * @param  list<array{0: string, 1: ?string}>  $pairs  [productUuid, variantUuid]
     * @return array<string, PurchasableLine> keyed by "productUuid:variantUuid"
     */
    public function lines(array $pairs): array;

    /** Storage disk + path of a digital product's files, for delivering purchased downloads. */
    public function fileLocation(string $productUuid, string $fileUuid): ?array;

    /** @return list<array{uuid: string, name: string, size: int}> */
    public function files(string $productUuid): array;
}
