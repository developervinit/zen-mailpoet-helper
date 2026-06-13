# Zen MailPoet Helper - Administration & Usage Guide

Welcome to the Zen MailPoet Helper plugin. Below are step-by-step instructions on how to set up, configure, and troubleshoot your custom newsletter popups.

---

## 🛠️ Step-by-Step Setup Guide

To display a custom newsletter popup on your website, follow these steps:

### 1. Create a New Popup
1. Go to your WordPress Admin dashboard.
2. In the left-hand sidebar menu, locate and click **Newsletter Popup** -> **Popups** -> **Add New**.
3. Enter an administrative title for your popup (e.g. *Summer 10% Discount Coupon*).

### 2. Configure Popup Content & Styling
Under the **Popup Settings & Content Builder** metabox:
- **General Tab**: Check the **Enable Popup** toggle. *(Note: Only one popup can be active globally on the site. Enabling this popup will automatically disable other configured popups).*
- **Content Tab**: Fill in the Headline Title, Description, Email field placeholder, and CTA button text.
- **Subscription Tab**: Map the popup to one or more of your MailPoet subscriber list checkmarks.
- **Display Tab**: Choose where you want the popup to show:
  - **Entire Website**: Displays globally across all public pages.
  - **Selected Pages**: Restricts visibility to checked pages only.
  - **Exclude Selected Pages**: Excludes Checked pages (e.g. cart, checkout, or privacy policy) from global loading.
- **Behavior Tab**: Specify the load delay (in milliseconds, e.g. `2500` for 2.5 seconds) and the number of days the popup stays hidden if a user closes or dismisses it.

### 3. Publish the Popup (CRITICAL)
- **Important**: Your popup will **not** display on the front-end if it is saved as a *Draft*.
- You must click the blue **Publish** (or Update) button in the upper-right corner of the editor to change the post status to `publish`.

---

## ⚠️ Troubleshooting: Why isn't my popup showing?

If you have completed the steps above and still do not see the popup on your website, check the following:

### Reason A: It has been saved as a Draft
Go to **Newsletter Popup** -> **Popups** and verify the popup's status is listed as **Enabled** (green badge). If it shows a draft status, edit the popup and click **Publish**.

### Reason B: The popup is marked as "Dismissed" in your browser cache
To prevent annoying visitors, closing the popup sets a flag in your browser's local storage:
- Storage Key: `zen_mp_dismissed_*` (where `*` is the configured list IDs).
- **How to test again**: 
  - Open a new **Incognito / Private Window** in your browser.
  - Or, open your browser Console (`F12` -> Application -> Local Storage) and click **Clear Storage** or clear keys starting with `zen_mp_`.

### Reason C: Logged-in user exclusion is active
- Go to **Newsletter Popup** -> **Settings**.
- If the option **Hide popups for logged-in users** is checked, log out of WordPress (or test in Incognito) to see the popup.
