# CLAUDE.md - Project Guidelines & Coding Standards

## 1. Project Context & Vision
- **Project Name:** Business Management System (BMS) - SPPG / MBG Supply Chain Engine
- **Primary PRD Location:** `docs/PRD.md`
- **Core Business Domain:** Middleman supply chain management for Dapur SPPG/MBG and external vendors.

## 2. Tech Stack & Architecture
- **Framework:** Laravel 12
- **Frontend Integration:** Inertia.js (Vue 3 / React) + TailwindCSS
- **Database:** MySQL 8.0+
- **Architecture Pattern:** Service-Oriented Pattern (`app/Services/`), Repository/Actions where applicable.
- **Access Control:** Dynamic RBAC using `spatie/laravel-permission`.

## 3. Critical Business Rules (STRICT COMPLIANCE)
1. **Multi-Delivery Note Engine (Parent-Child & Add-on SJ):**
   - An `Order` can have multiple `SuratJalan` records (`PRIMARY` for main shipment, `ADDON` for H+1/H+2 changes).
   - Items linked to an existing SJ MUST NOT be duplicated. Items without SJ (`surat_jalan_id IS NULL`) are assigned when a new SJ is generated.
   - Use `DB::transaction()` and `lockForUpdate()` when generating Delivery Notes to prevent duplicate PDF generation or race conditions.

2. **Daily Dynamic Pricing & Automated Master Sync:**
   - Daily cost prices update snapshot `order_items` and `daily_prices`.
   - Updating daily prices MUST automatically update `base_cost_price` and `base_selling_price` in the `items` table in the same transaction.

3. **Reconciled Split Billing:**
   - Invoices are split per `supplier_id`.
   - Total amount is calculated ONLY from items that passed QC (`qty_received - qty_rejected`).

4. **Data Scoping:**
   - Scoped data access for Dapur users (`dapur_id` scoping) and Supplier users (`supplier_id` scoping).

## 4. Coding & Implementation Guidelines
- **Database Column Types:** Monetary and quantity values MUST use `DECIMAL(12,2)` or `DECIMAL(10,2)` (NO floating-point types).
- **Transactions:** Wrap all dual-write/multi-model creation operations in `DB::transaction()`.
- **Naming Conventions:**
  - Controllers: PascalCase in plural resource folders (e.g., `app/Http/Controllers/Dapur/OrderController.php`).
  - Services: PascalCase with `Service` suffix (e.g., `app/Services/DeliveryNoteService.php`).
  - Models & Migrations: Standard Laravel conventions.

## 5. Development Workflow Instructions for Claude Code
- When asked to build features or write code, **always refer to `docs/PRD.md`** first.
- Build incrementally: Database Migrations & Models -> Service Classes -> Controllers & Form Requests -> Inertia/Frontend Views.
- Ensure all created code aligns with the schema specified in Section 2 of the PRD.