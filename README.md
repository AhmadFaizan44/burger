# Burger Bistro — Simple Burger Ordering Website

A lightweight demo site for ordering burgers, fries, and combo deals. Built with **HTML, CSS, JavaScript, and PHP**.

## Files
- `index.html` — Landing page with menu, cart, and checkout form (frontend).
- `styles.css` — Styling.
- `script.js` — Client-side cart logic and checkout (serializes the cart to JSON).
- `order.php` — Server-side order processing: validates items & prices, applies deals, computes totals, and writes to `data/orders.csv`.
- `data/orders.csv` — Appended order log (created automatically if missing).

## Quick Start (Local)
1. Ensure you have PHP installed (e.g., PHP 8.x).
2. From this folder, run a local PHP server:
   ```bash
   php -S localhost:8000
   ```
3. Open http://localhost:8000 in your browser.
4. Add items to the cart, fill checkout form, and place your order.

> **Note:** GitHub Pages does not run PHP. If you need live PHP processing deployed,
> host on a PHP-capable service (e.g., shared hosting, Render, Fly.io, Heroku-compatible stacks, etc.).
> You can still upload this project to GitHub as code; to *run* `order.php`, you need a PHP server.

## Default Deals
- `COMBO10` — 10% off the entire cart.
- `FREESODA` — Adds a free small soda (max 1) during server validation (won't reduce price below $0).
- `FRIESUP` — Upgrades one fries to large at medium price (server will adjust if applicable).

## Security Notes
- Prices are **recomputed on the server** to prevent tampering.
- Allowed items and prices are fixed in `order.php` via `$PRICE_BOOK`.
- The server validates quantities and caps extremes.

Enjoy!