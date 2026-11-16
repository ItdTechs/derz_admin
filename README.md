# DerZ Admin (admin.derzsystem.com)

The DerZ team's own portal: every business, its subscription and users, payment requests,
packages, announcements, analytics and the team itself, with role-based access. It replaces
**Platform** inside the DerZ app. A CodeIgniter 3 project of its own, on the **same database** as
the app and the landing page.

## What it does

| Page | |
|---|---|
| Dashboard | MRR / ARR, revenue against last month, paying / trial businesses, new sign-ups, requests waiting, people online, plans ending, the staff member's follow-ups |
| Analytics | revenue per month (plans and add-ons), growth and how many still pay, sales the businesses record through DerZ, businesses selling per day, plan mix, funnel (signed up → products → sale → paid), signup cohorts, cities, payment methods, top businesses, businesses at risk; CSV export |
| Businesses | search, filters (status, package, CRM stage, account manager, follow-up due), sort, CSV export. A business: usage, sales per day, subscription and add-ons, details, its app users (online, reset password, switch off, sign out), payments with receipts, notes / calls / visits, account manager, stage, tags, next follow-up, what the team did, the business's own audit log. Record a payment, change the end date or the package, edit, **suspend** / reactivate |
| Payment requests / Payments | approve / reject (the business gets a Telegram message), receipts, every payment with filters, totals and CSV |
| Online now | who is using the app now (from its sessions), per business, the team in the portal; sign anyone out |
| Announcements | messages at the top of the app for all businesses, paying, trial, ending, expired or one package; Telegram broadcasts |
| Packages | prices, limits, modules, add-on prices, the website's feature lines; payment methods |
| Translations | every word of the app and the website in every language |
| Settings | free trial, support contacts, payment instructions, maintenance mode, the Telegram bot, the team's Telegram chats |
| Staff / Roles / Audit log | staff accounts, roles (Owner, Administrator, Finance, Support, Sales agent, Viewer, or your own), every action of the team |

**Roles.** A role is a list of permissions (`admin_permissions()` in `helpers/admin_helper.php`).
A role with "See businesses" but not "See all businesses" is an **agent**: they only see the
businesses whose account manager they are (and their payment requests). The **Owner** role can do
everything and cannot be changed; the last Owner cannot lose the role.

**Signing in** with an email or a phone number. **Security.** Own staff accounts (bcrypt), a pause after 5 failed sign-ins (15 minutes), signed
out after 60 idle minutes, a temporary password must be changed at the first sign-in, other
devices signed out when a password changes, CSRF on every form, optional IP allow-list
(`config/admin.php`), `noindex`, no framing, the project folders not served (`.htaccess`).

## Files

```
application/config/admin.php        app address and folder, sign-in limits, allowed IPs, "online" minutes
application/core/MY_Controller.php  session, sign-in check, permissions (need()), the layout and sidebar
application/libraries/Staff_auth.php sign-in, permissions, idle sign-out (loaded as $this->auth)
application/libraries/Telegram.php  the DerZ bot (same token as the app; its webhook stays on the app)
application/models/                 Billing (same rules as the app), Business, Analytics, Online, Staff, Message, Translation
application/controllers/            one per page (Cli: command line)
application/views/layout.php        the page frame
assets/css/admin.css, js/admin.js   the look (DerZ green, Signika, light / dark) and the behaviour
database/sql/2026-11-10_45_admin_portal.sql   the portal's tables (run once)
```

## Installing

1. Run **steps 43 and 44** of the app (`derz_inventory/database/sql/`), then
   **`database/sql/2026-11-10_45_admin_portal.sql`** on the DerZ database.
2. Upload this folder to the document root of **admin.derzsystem.com** (PHP 7.4+, mod_rewrite,
   cURL for Telegram). Make `application/logs` and `application/cache` writable.
3. Copy `application/config/database.php.example` to `database.php` and fill in the login.
4. In `application/config/admin.php` set `app_path` to the app's folder on the server (payment
   receipts are read from its `uploads/subscription_receipts/`). Optionally `allowed_ips`.
5. Run `database/sql/2026-11-11_46_admin_owner.sql`: the Owner account (Amir, phone 0972795880).
   Sign in with the phone number and change the password in My account.
6. Sign in, add the team in **Staff**, check the roles.
7. In the app set `platform_admin_url` (`derz_inventory/application/config/config.php`) to
   `https://admin.derzsystem.com/`: the app's Platform pages then open the portal.

Optional: a daily cron `php index.php cli cleanup` (old sign-in attempts and sessions).

## What the app does with it

The app (derz_inventory) reads two things the portal writes:
- `businesses.status = 'suspended'`: the business's people see a "suspended" page with the reason
  (`business_suspension_gate()` in the app's `settings_helper.php`); sign-in is refused.
- `platform_announcements`: the live ones for the business's audience show at the top of the app
  (`views/partials/announcements.php` there); each can be closed and stays closed on that device until it changes.
