MD Flash Sale Pro

MD Flash Sale Pro is a powerful WordPress/WooCommerce plugin that enables you to create Shopee-style flash sales with multiple time slots, product limits, countdown timers, and engaging campaign banners. Designed for performance and flexibility, it helps store owners drive urgency, boost engagement, and increase conversions.

✨ Features

Custom Post Type (CPT) “Flash Sale Campaign”
Manage campaigns with title, description, thumbnail, and custom editor content.

Multi-Timeframe Slots
Create daily/hourly slots (e.g., 9AM, 12PM, 6PM) to schedule flash sales.

Product Management

Add products manually or via AJAX search (by title or SKU).

Set discounts, quotas, and campaign prices.

Track sales progress with real-time progress bars.

Frontend Shortcodes

[md_flash_sale_slider] → display all active campaigns on homepage.

[md_flash_sale_campaign id="123"] → display a specific campaign.

Dynamic Frontend

Tabs for each slot.

Countdown timers for upcoming/ongoing sales.

Campaign content with banners and rich descriptions.

Admin Features

Upload campaign thumbnail.

WYSIWYG editor for campaign content.

Manage products and slots from a unified menu.

🛠 Installation

Download the plugin .zip file.

In your WordPress admin panel, go to Plugins → Add New → Upload Plugin.

Upload md-flash-sale-pro.zip and click Install Now.

Activate the plugin.

A new Flash Sale menu will appear in the WordPress admin sidebar.

🚀 Usage

Create a Flash Sale Campaign from the admin menu.

Add slots with start and end times.

Select products, set flash sale prices, and define quotas.

Add campaign thumbnail and content (banners, description).

Insert the shortcode on your page/post:

[md_flash_sale_slider]

or

[md_flash_sale_campaign id="123"]

📂 Folder Structure
md-flash-sale-pro/
├── assets/ # CSS, JS, images
├── includes/ # Core plugin logic
├── templates/ # Frontend display templates
├── flash-sale-cpt.php # CPT registration
├── flash-sale-slots.php # Slot management
├── readme.md

⚡ Technical Notes

Built with WordPress standards (CPT, meta boxes, WP Query).

WooCommerce integration for products, pricing, and stock.

AJAX and Select2 used for product search in admin.

Fully responsive frontend layout.

📜 License

This plugin is released under the GPLv2 or later license.
