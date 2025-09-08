# Seamless Logo Marquee - User Guide

Thank you for using the Seamless Logo Marquee plugin\! This guide will walk you through installing, configuring, and using the plugin to display a beautiful, endlessly scrolling logo slider on your WordPress site.

## 1\. Installation

1.  **Compress the Plugin Folder**: Take the entire `seamless-logo-marquee` folder and compress it into a single `.zip` file.
2.  **Navigate to WordPress Admin**: Log in to your WordPress dashboard and go to **Plugins** -\> **Add New**.
3.  **Upload Plugin**: Click the **Upload Plugin** button at the top of the page.
4.  **Choose File**: Select the `seamless-logo-marquee.zip` file you just created and click **Install Now**.
5.  **Activate**: Once the installation is complete, click **Activate Plugin**.

---

## 2\. Managing Logos

After activation, you will see a new **"Logos"** menu item in your WordPress admin sidebar.

### Adding a New Logo

1.  Navigate to **Logos** -\> **Add New**.
2.  **Enter a Title**: Type the name of the company or brand in the title field (e.g., "Google", "Microsoft"). This will be used as the image's `alt` text for accessibility.
3.  **Set Featured Image**: On the right-hand sidebar, find the **"Featured Image"** box. Click **"Set featured image"** and upload your logo image.
4.  **Publish**: Click the **Publish** button.
5.  Repeat these steps for all the logos you want to display.

### Ordering Logos

You can control the exact order in which your logos appear in the slider.

1.  Navigate to **Logos** and click **Edit** on the logo you wish to order.
2.  On the right-hand sidebar, find the **"Page Attributes"** box.
3.  In the **"Order"** field, enter a number. Logos with lower numbers will appear first (e.g., `0` will show before `1`, `1` before `2`, and so on).
4.  Click the **Update** button to save your changes.

> **⭐ Pro-Tip for Easier Ordering:** For a more user-friendly drag-and-drop interface, you can install a free plugin called **"Post Types Order"**. Once activated, you can simply drag and drop your logos into the desired sequence on the main **Logos** list page. Our plugin is fully compatible with it.

---

## 3\. Displaying the Slider

To display the logo marquee on any page, post, or text widget, simply use the following shortcode:

```
[logo_marquee]
```

Copy and paste this shortcode into the WordPress editor where you want the slider to appear.

---

## 4\. Configuration

You can customize the look and behavior of your slider from a dedicated settings page.

1.  Navigate to **Logos** -\> **Settings**.

2.  Here you will find the following options:

    - **Speed**: Controls the scrolling speed of the marquee. A higher number means a faster speed. (Default: `1`)
    - **Direction**: Choose the direction the logos will travel.
      - _Right to Left_
      - _Left to Right_
    - **Layout**: Determines the width of the slider container.
      - _Container_: The slider will be constrained to your theme's standard content width.
      - _Full-width_: The slider will stretch to the full width of the browser window.
    - **Logo Height (px)**: Set the maximum height for your logos in pixels. The width will adjust automatically. (Default: `40`)
    - **Hover Effect**: Choose what happens when a user hovers their mouse over a logo.
      - _None_: No special effect.
      - _Scale Up_: The logo will zoom in slightly.
      - _Grayscale_: Logos are grayscale by default and turn to full color on hover.
      - _Opacity_: Logos are slightly transparent by default and become fully opaque on hover.
      - _Lift Up_: The logo will lift up with a subtle shadow effect.

3.  After making your changes, click the **Save Changes** button. The slider on your website will update instantly with the new settings.
