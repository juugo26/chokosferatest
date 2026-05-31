const API_BASE_URL = (function () {
  var host = window.location.hostname;
  var port = window.location.port;
  if ((host === 'localhost' || host === '127.0.0.1') && port && port !== '3000') {
    return 'http://localhost:3000';
  }
  if ((host === 'localhost' || host === '127.0.0.1') && !port) {
    return 'http://localhost:3000';
  }
  return '';
})();

class Order {
  constructor(id, items, total, status = 'Pending', userId = null, createdAt = null) {
    this.id = id;
    this.items = items;
    this.total = total;
    this.status = status;
    this.userId = userId;
    this.createdAt = createdAt || new Date().toISOString();
  }
}

class OrderRepository {
  async saveOrder(order) {
    const res = await fetch(API_BASE_URL + '/api/orders', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        items: order.items, 
        total: order.total, 
        userId: order.userId, 
        orderDate: order.orderDate,
        email: order.email 
      })
    });
    if (!res.ok) throw new Error('Failed to save order');
    const data = await res.json();
    return data.order;
  }

  async getAllOrders() {
    let userId = null;
    try {
      const saved = localStorage.getItem('chokosferaUser');
      if (saved) userId = JSON.parse(saved).id;
    } catch (e) {}
    const url = userId ? API_BASE_URL + `/api/orders?userId=${userId}` : API_BASE_URL + '/api/orders';
    const res = await fetch(url);
    if (!res.ok) throw new Error('Failed to fetch orders');
    return await res.json();
  }

  async cancelOrder(orderId) {
    const res = await fetch(API_BASE_URL + `/api/orders/${orderId}/cancel`, { method: 'PATCH' });
    if (!res.ok) throw new Error('Failed to cancel order');
    return await res.json();
  }
}

const CART_STORAGE_KEY = 'chokosferaCart';

const customOrderDefinitions = {
  donuts: { label: 'Donuts', unitPrice: 5.0, min: 4, step: 4, packSize: 4 },
  popsicles: { label: 'Cakestickles', unitPrice: 12.0, min: 4, step: 4, packSize: 4 },
  heartPopsicles: { label: 'Heart Cakestickles', unitPrice: 12.0, min: 4, step: 4, packSize: 4 },
  chocoStrawberries: { label: 'Choco Strawberries', unitPrice: 5.0, min: 5, step: 5, packSize: 5 },
  chocoDates: { label: 'Choco Dates', unitPrice: 4.0, min: 5, step: 5, packSize: 5 },
  smashCake: { label: 'Smash Cake', unitPrice: 40.0, min: 1, step: 1, packSize: 1 }
};

class OrderService {
  constructor(repository) {
    this.repository = repository;
    this.cart = this.loadCart();
  }

  loadCart() {
    try {
      const saved = localStorage.getItem(CART_STORAGE_KEY);
      if (!saved) return [];
      const parsed = JSON.parse(saved);
      return Array.isArray(parsed) ? parsed.map(item => ({
        name: item.name,
        price: Number(item.price) || 0,
        amount: Number(item.amount) || 1,
        description: item.description,
        customOrderData: item.customOrderData || null
      })) : [];
    } catch (error) {
      return [];
    }
  }

  saveCart() {
    try {
      localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.cart));
    } catch (error) {
      // Ignore storage write failures.
    }
  }

  addToCart(item) {
    const quantity = Number(item.amount) || 1;
    const unitPrice = Number(item.price) || 0;
    const normalizedItem = {
      name: item.name,
      price: quantity > 1 ? unitPrice : unitPrice,
      amount: quantity,
      description: item.description,
      customOrderData: item.customOrderData || null
    };
    if (quantity > 1) {
      normalizedItem.price = unitPrice * quantity;
    } else {
      normalizedItem.price = unitPrice;
    }
    this.cart.push(normalizedItem);
    this.saveCart();
  }

  removeFromCart(index) {
    if (index < 0 || index >= this.cart.length) return;
    this.cart.splice(index, 1);
    this.saveCart();
  }

  calculateTotal() {
    return this.cart.reduce((total, item) => total + Number(item.price || 0), 0);
  }

  async placeOrder(userId = null, orderDate = null, email = null) {
    if (this.cart.length === 0) throw new Error("Cart is empty");
    const total = this.calculateTotal();
    const order = new Order(null, [...this.cart], total, 'Pending', userId);
    order.orderDate = orderDate || null;
    order.email = email || null;
    const saved = await this.repository.saveOrder(order);
    this.cart = [];
    this.saveCart();
    return saved;
  }

  async cancelOrder(orderId) {
    return await this.repository.cancelOrder(orderId);
  }

  async viewOrders() {
    return await this.repository.getAllOrders();
  }
}

class OrderController {
  constructor(service) {
    this.service = service;
    this.customOrderControlsBound = false;
    this.customOrderCounts = {
      donuts: 0,
      popsicles: 0,
      heartPopsicles: 0,
      chocoStrawberries: 0,
      chocoDates: 0,
      smashCake: 0
    };
    this.customOrderSummary = document.getElementById('customOrderSummary');
    this.customOrderPriceValue = document.getElementById('customOrderPriceValue');
    this.activeCustomOrderDraft = null;
  }

  addToCart(item) {
    this.service.addToCart(item);
    this.renderCart();
  }

  removeFromCart(index) {
    this.service.removeFromCart(index);
    this.renderCart();
  }

  calculateTotal() {
    return this.service.calculateTotal();
  }

  getCurrentUserKey() {
    try {
      const saved = localStorage.getItem('chokosferaUser');
      if (saved) {
        const user = JSON.parse(saved);
        if (user && user.id) return user.id;
      }
    } catch (error) {}
    return 'guest';
  }

  getNextCustomOrderLabel() {
    if (this.activeCustomOrderDraft && this.activeCustomOrderDraft.label) {
      return this.activeCustomOrderDraft.label;
    }
    const userKey = this.getCurrentUserKey();
    const storageKey = `chokosferaCustomOrderCounter_${userKey}`;
    const currentCount = Number.parseInt(localStorage.getItem(storageKey) || '0', 10);
    const nextCount = currentCount + 1;
    localStorage.setItem(storageKey, String(nextCount));
    return `Custom order #${nextCount}`;
  }

  calculateCustomOrderTotal() {
    return Object.entries(this.customOrderCounts).reduce((total, [key, quantity]) => {
      if (!quantity) return total;
      const config = customOrderDefinitions[key];
      if (!config) return total;
      const groups = quantity / config.step;
      return total + Number((groups * config.unitPrice).toFixed(2));
    }, 0);
  }

  renderCustomOrderSummary() {
    if (!this.customOrderSummary) return;
    this.customOrderSummary.innerHTML = '';

    const selectedItems = Object.entries(this.customOrderCounts).filter(([, quantity]) => quantity > 0);
    if (selectedItems.length === 0) {
      this.customOrderSummary.textContent = 'No items selected yet.';
      return;
    }

    selectedItems.forEach(([key, quantity]) => {
      const config = customOrderDefinitions[key];
      if (!config) return;
      const chip = document.createElement('div');
      chip.className = 'summary-chip';
      chip.innerHTML = `
        <span>${config.label} x ${quantity}</span>
        <button type="button" aria-label="Remove ${config.label}" data-remove-option="${key}">×</button>
      `;
      chip.querySelector('button').addEventListener('click', () => {
        this.customOrderCounts[key] = 0;
        this.syncCustomOrderUI();
      });
      this.customOrderSummary.appendChild(chip);
    });
  }

  updateCustomOrderPriceDisplay() {
    if (!this.customOrderPriceValue) return;
    const total = this.calculateCustomOrderTotal();
    this.customOrderPriceValue.textContent = `${total.toFixed(2)} KM`;
  }

  addCustomOrder() {
    const notesField = document.getElementById('designNotes');
    const notes = notesField ? notesField.value.trim() : '';
    const selectedItems = [];
    let summaryText = [];

    Object.entries(customOrderDefinitions).forEach(([key, config]) => {
      const quantity = Math.max(0, this.customOrderCounts[key] || 0);
      if (quantity === 0) return;

      const groups = quantity / config.step;
      const linePrice = Number((groups * config.unitPrice).toFixed(2));
      selectedItems.push({
        name: config.label,
        price: linePrice,
        amount: quantity,
        description: `Custom order selection`
      });
      summaryText.push(`${config.label} x ${quantity}`);
    });

    if (selectedItems.length === 0) {
      alert('Select at least one item to add to the cart.');
      return;
    }

    const customOrderLabel = this.getNextCustomOrderLabel();
    const total = this.calculateCustomOrderTotal();
    const description = [summaryText.join(', '), notes ? `Design notes: ${notes}` : null].filter(Boolean).join(' • ');
    const customOrderData = {
      counts: { ...this.customOrderCounts },
      notes,
      label: customOrderLabel,
      total
    };
    this.service.addToCart({
      name: customOrderLabel,
      price: total,
      amount: 1,
      description,
      customOrderData
    });
    this.renderCart();
    this.resetCustomOrder();
  }

  restoreCustomOrder(index) {
    const item = this.service.cart[index];
    if (!item || !item.customOrderData) return;

    const customOrderData = item.customOrderData;
    this.customOrderCounts = {
      donuts: Number(customOrderData.counts?.donuts) || 0,
      popsicles: Number(customOrderData.counts?.popsicles) || 0,
      heartPopsicles: Number(customOrderData.counts?.heartPopsicles) || 0,
      chocoStrawberries: Number(customOrderData.counts?.chocoStrawberries) || 0,
      chocoDates: Number(customOrderData.counts?.chocoDates) || 0,
      smashCake: Number(customOrderData.counts?.smashCake) || 0
    };

    const notesField = document.getElementById('designNotes');
    if (notesField) notesField.value = customOrderData.notes || '';

    this.activeCustomOrderDraft = {
      label: item.name,
      counts: { ...this.customOrderCounts },
      notes: customOrderData.notes || ''
    };

    this.service.removeFromCart(index);
    this.syncCustomOrderUI();
  }

  resetCustomOrder() {
    this.customOrderCounts = {
      donuts: 0,
      popsicles: 0,
      heartPopsicles: 0,
      chocoStrawberries: 0,
      chocoDates: 0,
      smashCake: 0
    };
    this.activeCustomOrderDraft = null;
    this.syncCustomOrderUI();
    const notesField = document.getElementById('designNotes');
    if (notesField) notesField.value = '';
  }

  bindCustomOrderControls() {
    if (this.customOrderControlsBound) return;

    const controls = document.querySelectorAll('[data-action="increase"], [data-action="decrease"]');
    controls.forEach((button) => {
      button.addEventListener('click', () => {
        const option = button.getAttribute('data-option');
        if (!option || !customOrderDefinitions[option]) return;

        const config = customOrderDefinitions[option];
        const current = this.customOrderCounts[option] || 0;
        const change = button.getAttribute('data-action') === 'increase' ? config.step : -config.step;
        const nextValue = Math.max(0, current + change);
        this.customOrderCounts[option] = nextValue;
        this.syncCustomOrderUI();
      });
    });

    const addButton = document.getElementById('addCustomOrderButton');
    if (addButton) {
      addButton.addEventListener('click', () => this.addCustomOrder());
    }

    const resetButton = document.getElementById('resetCustomOrderButton');
    if (resetButton) {
      resetButton.addEventListener('click', () => this.resetCustomOrder());
    }

    this.customOrderControlsBound = true;
  }

  syncCustomOrderUI() {
    Object.entries(this.customOrderCounts).forEach(([key, value]) => {
      const countElement = document.getElementById(`${key}Count`);
      if (countElement) countElement.textContent = String(value);
    });
    this.renderCustomOrderSummary();
    this.updateCustomOrderPriceDisplay();
  }

  async placeOrder() {
    try {
      let userId = null;
      let email = null;
      try {
        const saved = localStorage.getItem('chokosferaUser');
        if (saved) {
          const user = JSON.parse(saved);
          userId = user.id;
          email = user.email;
        }
      } catch (e) {}
      // read date from UI (if present)
      let orderDate = null;
      try {
        const dateInput = document.getElementById('orderDate');
        if (dateInput && dateInput.value) {
          orderDate = dateInput.value; // format YYYY-MM-DD
        }
      } catch (e) {}

      await this.service.placeOrder(userId, orderDate, email);
      alert('Order placed successfully!');
      this.renderCart();
      await this.viewOrders();
    } catch (error) {
      alert('Failed to place order: ' + error.message);
    }
  }

  async cancelOrder(orderId) {
    try {
      await this.service.cancelOrder(orderId);
      await this.viewOrders();
    } catch (error) {
      alert('Failed to cancel order: ' + error.message);
    }
  }

  async viewOrders() {
    const orders = await this.service.viewOrders();
    this.renderOrders(orders);
  }

  renderCart() {
    const cartContainer = document.getElementById('cartContainer');
    if (!cartContainer) return;
    cartContainer.innerHTML = '';
    this.service.cart.forEach((item, index) => {
      const div = document.createElement('div');
      div.className = 'cart-item';
      div.style.display = 'flex';
      div.style.alignItems = 'center';
      div.style.justifyContent = 'space-between';
      div.style.gap = '12px';

      const content = document.createElement('div');
      content.style.flex = '1';
      const quantity = Number(item.amount) || 1;
      const totalPrice = Number(item.price) || 0;
      const note = item.description ? ` • ${item.description}` : '';
      content.innerText = `${item.name} x ${quantity} • ${totalPrice.toFixed(2)} KM${note}`;

      const actions = document.createElement('div');
      actions.style.display = 'flex';
      actions.style.gap = '8px';
      actions.style.alignItems = 'center';

      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'btn btn-secondary';
      editBtn.textContent = 'Edit';
      editBtn.onclick = () => this.restoreCustomOrder(index);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-cancel';
      removeBtn.textContent = 'Remove';
      removeBtn.onclick = () => this.removeFromCart(index);

      if (item.customOrderData) {
        actions.append(editBtn, removeBtn);
      } else {
        actions.append(removeBtn);
      }

      div.append(content, actions);
      cartContainer.appendChild(div);
    });
    if (this.service.cart.length === 0) {
      cartContainer.innerHTML = '<p style="color: #999;">Cart is empty.</p>';
    }
    const totalDiv = document.getElementById('cartTotal');
    if (totalDiv) totalDiv.innerText = `Total: ${this.calculateTotal().toFixed(2)} KM`;
  }

  renderOrders(orders) {
    const ordersContainer = document.getElementById('ordersContainer');
    if (!ordersContainer) return;
    ordersContainer.innerHTML = '';
    if (orders.length === 0) {
      ordersContainer.innerHTML = '<p>No orders placed yet.</p>';
      return;
    }
    [...orders].reverse().forEach(order => {
      const div = document.createElement('div');
      div.className = 'order-card';
      const isPending = order.status === 'Pending';
      const itemsList = order.items.map(i => i.name).join(', ');
      const displayDate = order.orderDate || (order.createdAt ? order.createdAt.split('T')[0] : 'N/A');
      div.innerHTML = `
        <h3>Order #${String(order.id).slice(-6)}</h3>
        <p><strong>Date:</strong> ${displayDate}</p>
        <p><strong>Items:</strong> ${itemsList}</p>
        <p><strong>Total:</strong> ${Number(order.total).toFixed(2)} KM</p>
        <p><strong>Status:</strong> <span style="color: ${order.status === 'Cancelled' ? 'red' : 'green'}">${order.status}</span></p>
        ${isPending ? `<button class="btn btn-cancel" onclick="appController.cancelOrder('${order.id}')">Cancel Order</button>` : ''}
      `;
      ordersContainer.appendChild(div);
    });
  }
}

const repository = new OrderRepository();
const service = new OrderService(repository);
const appController = new OrderController(service);

function initializeOrderPage() {
  appController.bindCustomOrderControls();
  appController.syncCustomOrderUI();
  appController.renderCart();
  appController.viewOrders();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeOrderPage);
} else {
  initializeOrderPage();
}
