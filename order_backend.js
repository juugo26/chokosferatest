const express = require('express');
const fs = require('fs');
const path = require('path');

const router = express.Router();
const DATA_DIR = path.join(__dirname, 'data');
const ORDERS_FILE = path.join(DATA_DIR, 'orders.json');

function readOrders() {
  try {
    const raw = fs.readFileSync(ORDERS_FILE, 'utf8');
    return JSON.parse(raw || '[]');
  } catch (e) {
    return [];
  }
}

function writeOrders(orders) {
  fs.writeFileSync(ORDERS_FILE, JSON.stringify(orders, null, 2));
}

router.post('/', (req, res) => {
  const { items, total, userId } = req.body;
  const { orderDate } = req.body;
  if (!items || !Array.isArray(items) || items.length === 0) {
    return res.status(400).json({ error: 'Cart is empty' });
  }

  const order = {
    id: Date.now().toString(),
    items,
    total,
    status: 'Pending',
    userId: userId || null,
    orderDate: orderDate || null,
    createdAt: new Date().toISOString()
  };

  const orders = readOrders();
  orders.push(order);
  writeOrders(orders);

  res.status(201).json({ order });
});

router.get('/', (req, res) => {
  const orders = readOrders();
  const { userId } = req.query;

  if (userId) {
    return res.json(orders.filter(o => o.userId === userId));
  }

  res.json(orders);
});

router.patch('/:id/cancel', (req, res) => {
  const orders = readOrders();
  const order = orders.find(o => o.id === req.params.id);

  if (!order) return res.status(404).json({ error: 'Order not found' });
  if (order.status === 'Cancelled') return res.status(400).json({ error: 'Already cancelled' });

  order.status = 'Cancelled';
  writeOrders(orders);

  res.json({ order });
});

router.patch('/:id/approve', (req, res) => {
  const orders = readOrders();
  const order = orders.find(o => o.id === req.params.id);

  if (!order) return res.status(404).json({ error: 'Order not found' });

  const { status } = req.body;
  if (status === 'Approved') {
    order.status = 'Approved';
  } else if (status === 'Rejected') {
    order.status = 'Rejected';
  } else {
    return res.status(400).json({ error: 'Invalid status. Use "Approved" or "Rejected".' });
  }

  writeOrders(orders);
  res.json({ order });
});

module.exports = router;