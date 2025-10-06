(function () {
  const TAX_RATE = 0.08;
  const cart = new Map(); // key: id, value: { id, name, price, qty }

  const $cartItems = document.getElementById('cart-items');
  const $subtotal = document.getElementById('subtotal');
  const $tax = document.getElementById('tax');
  const $discount = document.getElementById('discount');
  const $total = document.getElementById('total');
  const $checkoutForm = document.getElementById('checkout-form');
  const $cartJson = document.getElementById('cart-json');
  const $dealCode = document.getElementById('deal-code');

  function fmt(x) { return `$${x.toFixed(2)}`; }

  function addItem(id, name, price) {
    const existing = cart.get(id);
    if (existing) {
      existing.qty += 1;
    } else {
      cart.set(id, { id, name, price: Number(price), qty: 1 });
    }
    render();
  }

  function updateQty(id, qty) {
    qty = Math.max(0, Math.min(99, Number(qty)||0));
    const it = cart.get(id);
    if (!it) return;
    if (qty === 0) cart.delete(id);
    else it.qty = qty;
    render();
  }

  function removeItem(id) {
    cart.delete(id);
    render();
  }

  function computeTotals() {
    let subtotal = 0;
    for (const it of cart.values()) subtotal += it.price * it.qty;
    const tax = subtotal * TAX_RATE;

    // Client-side discount preview (server will re-validate).
    let discount = 0;
    const code = ($dealCode.value || '').trim().toUpperCase();
    if (code === 'COMBO10') discount = 0.10 * subtotal;
    // Server will add FREESODA & FRIESUP where applicable.

    const total = Math.max(0, subtotal + tax - discount);
    return { subtotal, tax, discount, total };
  }

  function render() {
    $cartItems.innerHTML = '';
    for (const it of cart.values()) {
      const div = document.createElement('div');
      div.className = 'cart-item';

      div.innerHTML = `
        <div><strong>${it.name}</strong><div class="muted">$${it.price.toFixed(2)}</div></div>
        <div class="qty">
          <button class="btn small" data-act="dec" data-id="${it.id}">-</button>
          <input type="number" min="0" max="99" value="${it.qty}" data-id="${it.id}" />
          <button class="btn small" data-act="inc" data-id="${it.id}">+</button>
        </div>
        <div><strong>${fmt(it.price * it.qty)}</strong></div>
        <button class="remove" title="Remove" data-act="rm" data-id="${it.id}">✕</button>
      `;
      $cartItems.appendChild(div);
    }

    const { subtotal, tax, discount, total } = computeTotals();
    $subtotal.textContent = fmt(subtotal);
    $tax.textContent = fmt(tax);
    $discount.textContent = `-${fmt(discount).slice(1)}`;
    $total.textContent = fmt(total);
  }

  document.querySelectorAll('.add-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      addItem(btn.dataset.id, btn.dataset.name, btn.dataset.price);
    });
  });

  $cartItems.addEventListener('click', (e) => {
    const t = e.target;
    const id = t.getAttribute('data-id');
    const act = t.getAttribute('data-act');
    if (!id || !act) return;

    const it = cart.get(id);
    if (!it) return;
    if (act === 'inc') updateQty(id, it.qty + 1);
    if (act === 'dec') updateQty(id, it.qty - 1);
    if (act === 'rm') removeItem(id);
  });

  $cartItems.addEventListener('change', (e) => {
    const input = e.target;
    if (input.tagName === 'INPUT' && input.type === 'number') {
      const id = input.getAttribute('data-id');
      updateQty(id, input.value);
    }
  });

  $checkoutForm.addEventListener('submit', () => {
    // Serialize cart to hidden input
    const items = Array.from(cart.values());
    const payload = {
      items,
      taxRate: TAX_RATE,
    };
    $cartJson.value = JSON.stringify(payload);
  });

  document.getElementById('year').textContent = new Date().getFullYear();
  render();
})();