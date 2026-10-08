# 🌸 Sweet Choice (スウィート・チョイス)
> Handcrafted Japanese Desserts & Confections e-Commerce Platform  
> Artisanal Ginza boutique web application built with PHP, MariaDB/MySQL, and modern responsive design.

---

## 📖 Overview

**Sweet Choice** is a full-featured Japanese pastry and confectionery e-commerce platform. Designed around the aesthetic and culinary standards of luxury Tokyo patisseries, Sweet Choice features bilingual localization (English / Japanese), an AI Sommelier recommendation engine, custom order fulfillment (store pickup and distance-based delivery tiers), comprehensive dessert customization (sweetness, dietary options, packaging), and an administrative back-office portal.

---

## ✨ Features

### 🛍️ Customer Storefront
- **Bilingual Interface**: Full real-time language toggling between English (EN) and Japanese (JA) across all pages, navigation, cart, emails, and alerts with session persistence.
- **AI Dessert Sommelier**: Interactive recommendation module matching customer emotional mood (Happy, Romantic, Tired, Stressed, Celebrating, etc.) and flavor profile preferences (Chocolate, Fruit, Creamy, Matcha, etc.) to tailored desserts.
- **Artisanal Menu Catalog**: Filtering by category (Cakes, Cookies, Puddings, Pastries, Specialty Drinks), real-time search, and price/popularity sorting.
- **Detailed Dessert Pages**:
  - High-resolution imagery and availability badges (`Available`, `Limited`, `Sold Out`).
  - Full ingredient lists and allergy disclosures (dairy, eggs, wheat, nuts, gelatin).
  - Interactive item customizations (sweetness level, dietary alternatives, gift packaging, matcha intensity).
  - Customer review and rating system verified against completed orders.
- **Shopping Cart & Checkout**:
  - Real-time stock reservation and quantity checks.
  - Multi-option fulfillment: **Boutique Pickup** (Ginza store with scheduled time slots) and **Local Delivery** (distance-tiered fees and time slots).
  - Transparent itemized fees (Subtotal, Customizations, Distance Delivery Fee, Boutique Service Fee) strictly formatted in **Japanese Yen (¥ / JPY)**.
  - Mock payment gateway supporting Credit Card, PayPay, Cash on Delivery/Pickup, and Bank Transfer.
- **Account Management & Order Tracking**:
  - Customer registration, login, profile editing, and password management.
  - Order history tracking with live status milestones (`Order Received`, `Preparing`, `Ready for Pickup`, `Out for Delivery`, `Completed`, `Delivered`).
  - **Guest Order Lookup**: Non-registered guests can check real-time order status and itemized digital receipts via order number and email.
  - Persistent Favorites wishlist.

### 🛡️ Administrative Portal (`/admin`)
- **Dashboard KPI Metrics**: Real-time business analytics including total revenue, order count, active catalog count, customer counts, and low-stock alerts.
- **Order Management**: Status workflow updates, delivery scheduling inspection, and itemized receipt viewing.
- **Dessert Management (CRUD)**: Create, edit, and delete products with image uploads, tags for AI matching, ingredients, allergens, and customization sets.
- **Category Management (CRUD)**: Organize dessert collections, slugs, and sort ordering.
- **Hero Banner Carousel (CRUD)**: Manage storefront promotional slides, bilingual copy, and promotional links.
- **Review Moderation**: Approve, hide, or delete customer product reviews.
- **Store & Delivery Configuration**: Manage distance delivery fee tiers (0-3km, 3-7km, 7-15km), boutique service fee, operating business hours, and delivery/pickup time slot capacities.

---

## 🛠️ Tech Stack & Architecture

- **Backend**: PHP 8.3+ (Procedural + OOP helpers with strict typing and PDO prepared statements)
- **Database**: MariaDB 10.11 / MySQL 8.0 (UTF-8 `utf8mb4_unicode_ci`)
- **Frontend**: Responsive HTML5, Semantic CSS3 (CSS Variables, Flexbox, CSS Grid), Vanilla JavaScript (No heavy frameworks required)
- **Security**: CSRF tokens across all form actions, BCrypt password hashing (`PASSWORD_BCRYPT`), prepared parameterized queries (SQL injection prevention), XSS output escaping (`htmlspecialchars`).

---

## 📂 Project Structure

```
sweetchoice/
├── admin/                  # Administrative management back-office
│   ├── banners.php         # Hero banner slider CRUD
│   ├── categories.php      # Category catalog management
│   ├── header.php / footer.php # Admin layout components
│   ├── index.php           # Admin analytics dashboard
│   ├── login.php / logout.php # Admin authentication
│   ├── orders.php          # Order fulfillment and status processor
│   ├── products.php        # Dessert menu CRUD & image uploader
│   ├── reviews.php         # Review moderation
│   ├── settings.php        # Delivery tiers, time slots, and business hours
│   └── users.php           # Staff and customer account management
├── assets/                 # Static assets
│   ├── css/style.css       # Global luxury dessert boutique styling
│   ├── js/
│   │   ├── main.js         # Core UI interactions & favorites API handler
│   │   ├── recommendation.js # Interactive AI Sommelier widget script
│   │   └── slider.js       # Hero banner responsive carousel
│   └── images/             # Brand logos and fallback artwork
├── database/
│   ├── schema.sql          # Full database DDL schema (20 relational tables)
│   ├── seed.sql            # Initial sample data (menu, categories, slots, banners)
│   └── generate_assets.php # Dynamic graphic generator for dessert photography
├── includes/               # Reusable core modules & business logic
│   ├── auth.php            # Authentication & session access control
│   ├── db.php              # PDO database connection & output buffering
│   ├── footer.php          # Storefront footer & operating hours display
│   ├── functions.php       # JPY currency formatters, CSRF, cart & badges
│   ├── header.php          # Storefront head, metadata & navigation bar
│   ├── language.php        # Bilingual EN/JA dictionary and switcher
│   ├── navbar.php          # Header navigation bar & live badge counters
│   └── recommendation.php  # Scoring algorithms for AI Sommelier pairings
├── public/                 # Storefront public webroot
│   ├── account.php         # Customer profile & account center
│   ├── api/
│   │   ├── favorites.php   # Asynchronous favorite toggle API
│   │   ├── recommend.php   # AI Sommelier JSON recommendation endpoint
│   │   └── review.php      # Customer review submission handler
│   ├── cart.php            # Shopping cart & customization manager
│   ├── category.php        # Dessert menu catalog with search & filters
│   ├── checkout.php        # Fulfillment scheduling, distance tiers & payment
│   ├── favorites.php       # Saved customer favorites gallery
│   ├── index.php           # Boutique home page & hero showcase
│   ├── login.php / logout.php # Customer authentication
│   ├── order-confirmation.php # Digital receipt & confirmation screen
│   ├── orders.php          # Customer order history & guest lookup
│   ├── product.php         # Single dessert details, ingredients & options
│   └── register.php        # New member account registration
├── uploads/                # Dynamic media uploads (products and banners)
├── index.php               # Root redirector to /public/index.php
└── README.md               # Project documentation
```

---

## 🚀 Getting Started

### 1. Prerequisites
- **PHP**: 8.1 or higher (with `pdo`, `pdo_mysql`, `gd`, `session`, `json` extensions)
- **MariaDB** or **MySQL**: 10.4+ / 8.0+
- **Web Server**: Apache, Nginx, or PHP built-in CLI server

### 2. Database Setup
Start MariaDB / MySQL and create the database:
```bash
sudo service mariadb start
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sweetchoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root sweetchoice < database/schema.sql
mysql -u root sweetchoice < database/seed.sql
```

Ensure user privileges match your environment:
```sql
CREATE USER IF NOT EXISTS 'sweetchoice'@'localhost' IDENTIFIED BY 'sweetchoice123';
GRANT ALL PRIVILEGES ON sweetchoice.* TO 'sweetchoice'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Run the Development Server
From the repository root directory:
```bash
php -S 127.0.0.1:8000
```
Then visit:
- **Storefront**: [http://127.0.0.1:8000/](http://127.0.0.1:8000/) (or `/public/index.php`)
- **Admin Portal**: [http://127.0.0.1:8000/admin/](http://127.0.0.1:8000/admin/)

---

## 🔑 Default Credentials

| Portal | Email | Password | Role |
| :--- | :--- | :--- | :--- |
| **Admin Back-Office** | `admin@sweetchoice.jp` | `adminpassword123` | Administrator |
| **Customer Storefront** | `customer@sweetchoice.jp` | `customer123` | Registered Member |

---

## 📄 License
This project is open-source under the MIT License.
