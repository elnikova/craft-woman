import type { Money } from './product';

/** Short maker data (MakerSummary::toArray) */
export interface MakerBrief {
    uuid: string;
    display_name: string;
    slug: string;
    city: string | null;
    avatar_url: string | null;
}

// ---------- Cart (CartService::view) ----------

export type CartIssue = 'unavailable' | 'sold_out' | 'insufficient_stock';

export interface CartItem {
    uuid: string;
    product_uuid: string;
    variant_uuid: string | null;
    name: string;
    variant_name: string | null;
    slug: string | null;
    image_url: string | null;
    is_digital: boolean;
    quantity: number;
    available_stock: number | null;
    unit_price: Money;
    total: Money;
    price_changed: boolean;
    issue: CartIssue | null;
}

export interface Cart {
    currency: string | null;
    items_count: number;
    subtotal_minor: number;
    groups: { maker: MakerBrief | null; items: CartItem[] }[];
    has_issues: boolean;
    /** Guest cart UUID; null for signed-in users */
    token: string | null;
}

// ---------- Checkout ----------

export type PaymentMethod = 'online' | 'cash_on_delivery';

export interface Quote {
    currency: string;
    needs_address: boolean;
    subtotal: Money;
    delivery: Money;
    total: Money;
    groups: {
        maker: MakerBrief | null;
        items_count: number;
        subtotal: Money;
        delivery_fee: Money;
        total: Money;
        has_physical: boolean;
        is_international: boolean;
    }[];
    payment_methods: { method: PaymentMethod; available: boolean; reasons: string[] }[];
}

export interface DeliveryAddress {
    recipient_name: string;
    phone: string;
    country_code: string;
    city: string;
    address: string;
    postal_code: string;
}

// ---------- Orders ----------

/** Mirrors Modules\Orders\Domain\OrderStatus */
export type OrderStatus = 'pending_payment' | 'processing' | 'partially_shipped' | 'shipped' | 'completed' | 'cancelled';
/** Mirrors Modules\Orders\Domain\GroupStatus */
export type GroupStatus = 'pending_payment' | 'awaiting_shipment' | 'shipped' | 'delivered' | 'completed' | 'cancelled';

export interface OrderItem {
    uuid: string;
    product_uuid: string | null;
    name: string;
    variant_name: string | null;
    is_digital: boolean;
    slug: string | null;
    image_url: string | null;
    quantity: number;
    unit_price: Money;
    total: Money;
}

export interface Shipment {
    recipient_name: string;
    phone: string;
    country_code: string;
    city: string;
    address: string;
    postal_code: string | null;
    tracking_number: string | null;
    status: string;
    shipped_at: string | null;
    delivered_at: string | null;
}

export interface OrderGroup {
    uuid: string;
    maker: { uuid: string; name: string };
    status: GroupStatus;
    cancel_reason: string | null;
    subtotal: Money;
    delivery_fee: Money;
    total: Money;
    items: OrderItem[];
    shipment: Shipment | null;
}

export interface Order {
    uuid: string;
    order_number: string;
    status: OrderStatus;
    payment_method: PaymentMethod;
    is_paid: boolean;
    subtotal: Money;
    delivery: Money;
    total: Money;
    billing_email: string;
    placed_at: string;
    paid_at: string | null;
    groups: OrderGroup[];
}

export interface DownloadFile {
    uuid: string;
    name: string;
    size: number;
}

/** An order part as the maker sees it */
export interface Sale extends OrderGroup {
    order_number: string;
    placed_at: string;
    payment_method: PaymentMethod;
    is_paid: boolean;
    buyer_email: string;
}
