# MD Flash Sale Pro

[![WordPress](https://img.shields.io/badge/WordPress-%5E6.0-blue)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%5E7.0-purple)](https://woocommerce.com/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

**MD Flash Sale Pro** is a powerful WooCommerce plugin that allows you to create **Shopee-style flash sales** with multiple time slots, quotas, countdown timers, and engaging campaign banners.  
It is optimized for performance and conversion, helping store owners boost engagement and increase sales.

---

## 📑 Table of Contents

- [Features](#-features)
- [Installation](#-installation)
- [Usage](#-usage)
- [Shortcodes](#-shortcodes)
- [Folder Structure](#-folder-structure)
- [Screenshots](#-screenshots)
- [Technical Notes](#-technical-notes)
- [License](#-license)

---

## ✨ Features

- **Custom Post Type (CPT): Flash Sale Campaigns**

  - Title, description (WYSIWYG editor), thumbnail.
  - Manage products and slots under one menu.

- **Multi-Timeframe Slots**

  - Set up campaigns at specific hours of the day.
  - Example: `9AM – 12PM`, `12PM – 3PM`, `6PM – 9PM`.

- **Product Management**

  - AJAX product search by **title** or **SKU**.
  - Set **flash sale price**, **quota**, and **progress bar**.

- **Frontend Display**

  - Tabs for each slot.
  - Countdown timers for ongoing/upcoming slots.
  - Responsive design similar to Shopee’s flash sale layout.

- **Shortcodes**
  - `[md_flash_sale_slider]` → Display all active campaigns (homepage slider).
  - `[md_flash_sale_campaign id="123"]` → Display a single campaign.

---

## 🛠 Installation

1. Download the plugin `.zip` file.
2. Go to **WordPress Admin → Plugins → Add New → Upload Plugin**.
3. Upload `md-flash-sale-pro.zip` and click **Install Now**.
4. Activate the plugin.
5. You will see a new menu **Flash Sale** in the WordPress admin dashboard.

---

## 🚀 Usage

1. Create a new **Flash Sale Campaign**.
2. Add **time slots** (start and end times).
3. Select products and set:
   - Flash sale price
   - Quantity limit (quota)
   - Progress bar display
4. Upload a **thumbnail** and add rich **campaign content** (banners, text).
5. Insert the shortcode into a page/post:

```php
[md_flash_sale_slider]
```

Or for a specific campaign:
[md_flash_sale_campaign id="123"]

## 📂 Folder Structure

md-flash-sale-pro/
├── assets/ # CSS, JS, images
├── includes/ # Core plugin functions
├── templates/ # Frontend templates
├── flash-sale-cpt.php # CPT registration
├── flash-sale-slots.php# Slot management
├── readme.md # Documentation

## 🖼 Screenshots

(Add your images here, e.g. assets/screenshot-1.png)

Admin Campaign Setup – Create campaigns and manage slots.

Product Selection – Search by SKU or product title.

Frontend Display – Shopee-style tabbed flash sale layout with countdown.

⚡ Technical Notes

Built on WordPress CPT & WooCommerce hooks.

Uses WP AJAX + Select2 for smooth product search.

Fully responsive with modern frontend templates.

Compatible with WordPress 6+ and WooCommerce 7+.

📜 License

This plugin is distributed under the GPLv2 or later license.
See the LICENSE
file for details.

💡 Author

Developed by trangc0der Minh Bee Trading Service Co., Ltd
