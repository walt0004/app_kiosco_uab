/**
 * Pantalla de venta (Alpine). Busca productos, arma el carrito y registra la venta.
 *
 * Importante: el navegador solo envía producto y cantidad. El precio y el stock
 * los vuelve a validar el servidor, así que nada de lo que se calcule aquí
 * puede alterar el cobro real.
 */

const STORAGE_KEY = 'kiosco.carrito';

export function formatMoney(cents) {
    const negative = cents < 0;
    const abs = Math.abs(cents);
    const whole = Math.floor(abs / 100)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    const decimals = String(abs % 100).padStart(2, '0');

    return `${negative ? '-' : ''}Bs ${whole},${decimals}`;
}

export function formatQty(value) {
    const text = (Math.round(value * 1000) / 1000).toString().replace('.', ',');

    return text;
}

/** Importe de una línea en centavos (misma fórmula que el servidor). */
export function lineCents(price, quantity) {
    return Math.floor((Math.round(price * 100) * Math.round(quantity * 1000) + 500) / 1000);
}

function newToken() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default (config) => ({
    query: '',
    results: [],
    loading: false,
    searchFailed: false,
    highlighted: 0,
    cart: [],
    method: 'efectivo',
    submitting: false,
    error: '',
    token: newToken(),
    timer: null,
    searchSeq: 0,

    init() {
        this.restoreCart();
        this.search();
        this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
    },

    // ---- Búsqueda -------------------------------------------------------

    onInput() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.search(), 200);
    },

    async search() {
        const seq = ++this.searchSeq;
        this.loading = true;
        this.searchFailed = false;

        try {
            const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(this.query)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            // Ignora respuestas viejas si el usuario siguió escribiendo.
            if (seq === this.searchSeq) {
                this.results = data;
                this.highlighted = 0;
            }
        } catch {
            if (seq === this.searchSeq) {
                this.searchFailed = true;
                this.results = [];
            }
        } finally {
            if (seq === this.searchSeq) {
                this.loading = false;
            }
        }
    },

    moveHighlight(step) {
        if (!this.results.length) {
            return;
        }
        this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
    },

    addHighlighted() {
        const product = this.results[this.highlighted];
        if (product) {
            this.add(product);
        }
    },

    // ---- Carrito --------------------------------------------------------

    add(product) {
        if (product.stock <= 0) {
            return;
        }

        this.error = '';
        const existing = this.cart.find((line) => line.id === product.id);

        if (existing) {
            existing.stock = product.stock;
            existing.qty = this.clamp(existing, Number(existing.qty) + 1);
        } else {
            this.cart.push({
                id: product.id,
                name: product.name,
                unit_short: product.unit_short,
                decimals: product.decimals,
                price: product.price,
                stock: product.stock,
                qty: 1,
            });
        }

        this.persist();
        this.query = '';
        this.search();
        this.$nextTick(() => this.$refs.search && this.$refs.search.focus());
    },

    remove(index) {
        this.cart.splice(index, 1);
        this.persist();
    },

    clear() {
        this.cart = [];
        this.error = '';
        this.persist();
    },

    /** Mantiene la cantidad dentro de lo permitido (mínimo, entero si corresponde, máximo = stock). */
    clamp(line, value) {
        let qty = Number(value);

        if (!Number.isFinite(qty)) {
            qty = 1;
        }

        qty = line.decimals ? Math.round(qty * 1000) / 1000 : Math.round(qty);
        const min = line.decimals ? 0.001 : 1;

        return Math.min(Math.max(qty, min), line.stock);
    },

    normalize(line) {
        line.qty = this.clamp(line, line.qty);
        this.persist();
    },

    step(line, delta) {
        line.qty = this.clamp(line, Number(line.qty) + delta);
        this.persist();
    },

    lineTotal(line) {
        return formatMoney(lineCents(line.price, line.qty));
    },

    get totalCents() {
        return this.cart.reduce((sum, line) => sum + lineCents(line.price, line.qty), 0);
    },

    get total() {
        return formatMoney(this.totalCents);
    },

    money(value) {
        return formatMoney(Math.round(value * 100));
    },

    qty(value) {
        return formatQty(value);
    },

    // ---- Registro de la venta ------------------------------------------

    async submit() {
        if (this.submitting || !this.cart.length) {
            return;
        }

        this.submitting = true;
        this.error = '';

        try {
            const response = await fetch(config.storeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': config.csrf,
                },
                body: JSON.stringify({
                    items: this.cart.map((line) => ({ product_id: line.id, quantity: line.qty })),
                    payment_method: this.method,
                    token: this.token,
                }),
            });

            let data = {};
            try {
                data = await response.json();
            } catch {
                data = {};
            }

            if (response.ok) {
                this.cart = [];
                this.persist();
                window.location.href = data.redirect;

                return;
            }

            if (response.status === 419) {
                this.error = 'Tu sesión expiró. Recarga la página (el carrito se conserva) e inténtalo de nuevo.';
            } else if (response.status === 401) {
                this.error = 'Tu sesión terminó. Vuelve a ingresar al sistema.';
            } else if (data.errors) {
                this.error = Object.values(data.errors).flat().join(' ');
            } else {
                this.error = data.message || 'No se pudo registrar la venta. Inténtalo de nuevo.';
            }

            // El stock pudo cambiar: se actualizan los resultados.
            this.search();
        } catch {
            this.error = 'No hay conexión con el servidor. Revisa la red e inténtalo de nuevo; no se registró nada.';
        } finally {
            this.submitting = false;
        }
    },

    // ---- Persistencia del carrito (sobrevive a recargar la página) ------

    persist() {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(this.cart));
        } catch {
            // Sin almacenamiento disponible: el carrito sigue funcionando en memoria.
        }
    },

    restoreCart() {
        try {
            const saved = JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]');

            if (Array.isArray(saved)) {
                this.cart = saved.filter((line) => line && line.id && line.price >= 0 && line.qty > 0);
            }
        } catch {
            this.cart = [];
        }
    },
});
