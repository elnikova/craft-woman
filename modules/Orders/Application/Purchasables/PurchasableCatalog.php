<?php

namespace Modules\Orders\Application\Purchasables;

use Modules\Education\Application\Contracts\CourseCatalog;
use Modules\Education\Application\Contracts\CourseLine;
use Modules\Marketplace\Application\Contracts\ProductCatalog;
use Modules\Marketplace\Application\Contracts\PurchasableLine;
use Modules\Orders\Infrastructure\Persistence\CartItem;
use Modules\Workshops\Application\Contracts\WorkshopCatalog;
use Modules\Workshops\Application\Contracts\WorkshopLine;

/** One door for Orders to the catalogs of other modules (products, courses, workshops). */
final class PurchasableCatalog
{
    public function __construct(
        private readonly ProductCatalog $products,
        private readonly CourseCatalog $courses,
        private readonly WorkshopCatalog $workshops,
    ) {}

    public function product(string $productUuid, ?string $variantUuid): ?Purchasable
    {
        $line = $this->products->line($productUuid, $variantUuid);

        return $line ? $this->fromProduct($line) : null;
    }

    public function course(string $courseUuid): ?Purchasable
    {
        $line = $this->courses->line($courseUuid);

        return $line ? $this->fromCourse($line) : null;
    }

    public function workshop(string $workshopUuid): ?Purchasable
    {
        $line = $this->workshops->line($workshopUuid);

        return $line ? $this->fromWorkshop($line) : null;
    }

    public function forItem(CartItem $item): ?Purchasable
    {
        return match (true) {
            $item->course_uuid !== null => $this->course($item->course_uuid),
            $item->workshop_uuid !== null => $this->workshop($item->workshop_uuid),
            default => $this->product((string) $item->product_uuid, $item->product_variant_uuid),
        };
    }

    /** Hold workshop seats for a placed order line (inside the checkout transaction). */
    public function reserveSeats(string $workshopUuid, string $userUuid, string $orderItemUuid, int $seats): void
    {
        $this->workshops->reserve($workshopUuid, $userUuid, $orderItemUuid, $seats);
    }

    /** @param list<string> $orderItemUuids */
    public function releaseSeats(array $orderItemUuids, string $reason): void
    {
        $this->workshops->release($orderItemUuids, $reason);
    }

    public function hasLifetimeAccess(string $userUuid, string $courseUuid): bool
    {
        return $this->courses->hasLifetimeAccess($userUuid, $courseUuid);
    }

    /**
     * @param  iterable<CartItem>  $items
     * @return array<string, Purchasable> keyed by CartItem::lineKey()
     */
    public function forItems(iterable $items): array
    {
        $pairs = [];
        $courseUuids = [];
        $workshopUuids = [];
        foreach ($items as $item) {
            if ($item->course_uuid) {
                $courseUuids[] = $item->course_uuid;
            } elseif ($item->workshop_uuid) {
                $workshopUuids[] = $item->workshop_uuid;
            } else {
                $pairs[] = [(string) $item->product_uuid, $item->product_variant_uuid];
            }
        }

        $result = [];
        foreach ($pairs ? $this->products->lines($pairs) : [] as $line) {
            $p = $this->fromProduct($line);
            $result[Purchasable::key($p->kind, $p->itemUuid, $p->variantUuid)] = $p;
        }
        foreach ($this->courses->lines($courseUuids) as $line) {
            $p = $this->fromCourse($line);
            $result[Purchasable::key($p->kind, $p->itemUuid)] = $p;
        }
        foreach ($this->workshops->lines($workshopUuids) as $line) {
            $p = $this->fromWorkshop($line);
            $result[Purchasable::key($p->kind, $p->itemUuid)] = $p;
        }

        return $result;
    }

    private function fromProduct(PurchasableLine $l): Purchasable
    {
        return new Purchasable(
            kind: Purchasable::PRODUCT,
            itemUuid: $l->productUuid,
            variantUuid: $l->variantUuid,
            makerUuid: $l->makerUuid,
            isDigital: $l->isDigital,
            name: $l->productName,
            variantName: $l->variantName,
            slug: $l->slug,
            imageUrl: $l->imageUrl,
            unitPriceMinor: $l->unitPriceMinor,
            currency: $l->currency,
            availableStock: $l->availableStock,
            isPurchasable: $l->isPurchasable,
        );
    }

    private function fromWorkshop(WorkshopLine $l): Purchasable
    {
        return new Purchasable(
            kind: Purchasable::WORKSHOP,
            itemUuid: $l->workshopUuid,
            variantUuid: null,
            makerUuid: $l->makerUuid,
            isDigital: true,
            name: $l->name,
            variantName: null,
            slug: $l->slug,
            imageUrl: $l->imageUrl,
            unitPriceMinor: $l->priceMinor,
            currency: $l->currency,
            availableStock: $l->seatsLeft,
            isPurchasable: $l->isPurchasable,
            startsAt: $l->startsAt,
        );
    }

    private function fromCourse(CourseLine $l): Purchasable
    {
        return new Purchasable(
            kind: Purchasable::COURSE,
            itemUuid: $l->courseUuid,
            variantUuid: null,
            makerUuid: $l->makerUuid,
            isDigital: true,
            name: $l->name,
            variantName: null,
            slug: $l->slug,
            imageUrl: $l->imageUrl,
            unitPriceMinor: $l->priceMinor,
            currency: $l->currency,
            availableStock: null,
            isPurchasable: $l->isPurchasable,
        );
    }
}
