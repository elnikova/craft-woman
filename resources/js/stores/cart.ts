import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { cartApi, cartToken } from '@/api/orders';
import type { Cart } from '@/types/order';

const EMPTY: Cart = { currency: null, items_count: 0, subtotal_minor: 0, groups: [], has_issues: false, token: null };

export const useCartStore = defineStore('cart', () => {
    const cart = ref<Cart>(EMPTY);
    const loaded = ref(false);

    const count = computed(() => cart.value.items_count);
    const isEmpty = computed(() => cart.value.items_count === 0);

    function apply(next: Cart): Cart {
        cart.value = next;
        // Guests keep the token; after login the cart belongs to the account and needs no token
        cartToken.set(next.token);
        loaded.value = true;
        return next;
    }

    async function load() {
        return apply(await cartApi.show());
    }

    /** After login: move the guest cart (if any) into the account, then load it */
    async function sync(isAuthenticated: boolean) {
        try {
            if (isAuthenticated && cartToken.get()) return apply(await cartApi.claim());
            return await load();
        } catch {
            cart.value = EMPTY;
            loaded.value = true;
            return EMPTY;
        }
    }

    const add = async (product: string, variant: string | null, quantity = 1) => apply(await cartApi.add(product, variant, quantity));
    const addCourse = async (course: string) => apply(await cartApi.addCourse(course));
    const setQuantity = async (item: string, quantity: number) => apply(await cartApi.update(item, quantity));
    const remove = async (item: string) => apply(await cartApi.remove(item));

    function reset() {
        cart.value = EMPTY;
    }

    return { cart, loaded, count, isEmpty, load, sync, add, addCourse, setQuantity, remove, reset };
});
