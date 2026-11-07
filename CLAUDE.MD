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
1. **Delivery Documents per Dapur + Supplier (`surat_jalans` table):**
   - Two `document_type`s: `SURAT_JALAN` (DRY_GOODS shipped by the koperasi; may combine several daily Orders of one Dapur, e.g. the weekly Sunday shipment) and `TANDA_TERIMA` (WET_GOODS delivered directly by the Supplier; one per Dapur + Supplier per delivery date). WET_GOODS never go on a Surat Jalan.
   - One document per Dapur + Supplier. First document for a (Dapur, Supplier, type, shipment date) is `PRIMARY`, later ones `ADDON`. `surat_jalans.order_id` is legacy; Order ↔ document is resolved through `order_items.surat_jalan_id`.
   - Items linked to an existing document MUST NOT be duplicated. Items without one (`surat_jalan_id IS NULL`) are assigned when a new document is generated.
   - Use `DB::transaction()` and `lockForUpdate()` when generating delivery documents to prevent duplicates or race conditions.

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