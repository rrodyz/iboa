# Laravel IBOA Project - Document Management & Import/Export Features Summary

## 1. DOCUMENT MANAGEMENT (ATTACHMENTS)

### 1.1 Overview
The project has a **polymorphic attachment system** that allows file uploads to be associated with any model. This provides a scalable way to attach documents to invoices, purchase orders, clients, suppliers, etc.

### 1.2 Core Components

#### Model: `App\Models\Attachment`
**Location:** `app/Models/Attachment.php`

**Attributes:**
- `attachable_type` - Polymorphic type (e.g., "App\\Models\\Invoice")
- `attachable_id` - Polymorphic ID
- `disk` - Storage disk name (default: 'local')
- `path` - File path on disk
- `filename` - Original filename
- `mime_type` - File MIME type
- `size` - File size in bytes
- `label` - Optional description/label
- `uploaded_by` - User ID who uploaded the file

**Relationships:**
- `attachable()` - MorphTo relationship (can attach to any model)
- `uploadedBy()` - BelongsTo User

**Utility Methods:**
- `url()` - Get storage URL
- `humanSize()` - Format file size (B, KB, MB)
- `isImage()` - Check if file is an image
- `isPdf()` - Check if file is a PDF
- `iconClass()` - Get CSS color class for file type

#### Trait: `App\Models\Traits\HasAttachments`
**Location:** `app/Models/Traits/HasAttachments.php`

```php
public function attachments(): MorphMany
{
    return $this->morphMany(Attachment::class, 'attachable')->latest();
}
```

**Status:** Trait exists but **NOT YET USED** by any model
**To Enable:** Add `use HasAttachments;` to any model that should support attachments

#### Controller: `App\Http\Controllers\AttachmentController`
**Location:** `app/Http/Controllers/AttachmentController.php`

**Methods:**
1. **store()** - Upload new file
   - Route: `POST /attachments`
   - Validates: file type, size (max 10MB)
   - Stores in: `attachments/{lowercase_model_type}/{id}/`
   - Returns: JSON with attachment metadata
   - Allowed MIME types:
     - Images: jpeg, png, gif, webp
     - Documents: PDF, Excel (xlsx/xls), Word (docx)
     - Data: CSV, plain text

2. **download()** - Download file
   - Route: `GET /attachments/{attachment}/dl`
   - Returns: File as download response

3. **destroy()** - Delete file
   - Route: `DELETE /attachments/{attachment}`
   - Removes from disk and database

### 1.3 Routes
```php
Route::middleware(['auth', 'verified'])->prefix('attachments')->name('attachments.')->group(function () {
    Route::post('/',                [\App\Http\Controllers\AttachmentController::class, 'store'])->name('store');
    Route::get('/{attachment}/dl',  [\App\Http\Controllers\AttachmentController::class, 'download'])->name('download');
    Route::delete('/{attachment}',  [\App\Http\Controllers\AttachmentController::class, 'destroy'])->name('destroy');
});
```

### 1.4 Existing Implementations
- **No views** currently use the attachment system
- **No models** currently use the HasAttachments trait
- **Foundation is ready** but awaiting integration with documents (invoices, purchase orders, etc.)

### 1.5 Database Schema
Expected table columns (migrations needed to verify):
```sql
- id
- attachable_type
- attachable_id
- disk
- path
- filename
- mime_type
- size (integer)
- label (nullable)
- uploaded_by (foreign key to users)
- created_at
- updated_at
```

---

## 2. IMPORT FEATURES

### 2.1 Overview
Bulk data import system supporting CSV/Excel files for Products, Clients, and Suppliers. Built on Maatwebsite/Excel library.

### 2.2 Core Components

#### Controller: `App\Http\Controllers\ImportController`
**Location:** `app/Http/Controllers/ImportController.php`

**Methods:**

1. **index()** - Display import page
   - Route: `GET /import`
   - Returns: import/index.blade.php view
   - Permissions: `settings.manage|products.create|clients.create`

2. **template(type)** - Download CSV template
   - Route: `GET /import/template/{type}`
   - Types: `products`, `clients`, `suppliers`
   - Returns: CSV file download
   - **Product columns:** reference, nom, famille, unite, prix_vente, prix_achat, tva, stock_min, stock_max, description
   - **Client/Supplier columns:** nom, code, email, telephone, adresse, ville, pays, ifu, rccm, notes

3. **import()** - Process file upload
   - Route: `POST /import`
   - Validates: type, file (xlsx/xls/csv, max 5MB)
   - Returns: Redirect with success message
   - Tracks imported and skipped rows

#### Import Classes

**1. ProductsImport** (`app/Imports/ProductsImport.php`)
- Implements: ToCollection, WithHeadingRow, SkipsOnError
- Fields: reference, nom, famille, unite, prix_vente, prix_achat, tva, stock_min, stock_max, description
- Behavior: UpdateOrCreate by reference
- Creates missing ProductFamilies automatically
- Looks up Unit and TaxRate by name/abbreviation

**2. ClientsImport** (`app/Imports/ClientsImport.php`)
- Implements: ToCollection, WithHeadingRow, SkipsOnError
- Fields: nom, code, email, telephone, adresse, ville, pays, ifu, rccm, notes
- Behavior: UpdateOrCreate by code
- Skips empty names
- Default country: "Bénin"

**3. SuppliersImport** (`app/Imports/SuppliersImport.php`)
- Implements: ToCollection, WithHeadingRow, SkipsOnError
- Fields: nom, code, email, telephone, adresse, ville, pays, ifu, rccm, notes
- Behavior: UpdateOrCreate by code
- Skips empty names
- Default country: "Bénin"

### 2.3 Import Rules
- First row must contain headers (matching template)
- Empty names are skipped
- Duplicate references/codes trigger update instead of insert
- Max file size: 5 MB
- File types: Excel (.xlsx, .xls), CSV (.csv)

### 2.4 Routes
```php
Route::middleware(['auth', 'verified', 'permission:settings.manage|products.create|clients.create'])->group(function () {
    Route::get('/import',                [\App\Http\Controllers\ImportController::class, 'index'])->name('import.index');
    Route::get('/import/template/{type}',[\App\Http\Controllers\ImportController::class, 'template'])->name('import.template');
    Route::post('/import',               [\App\Http\Controllers\ImportController::class, 'import'])->name('import.process');
});
```

### 2.5 View: `resources/views/import/index.blade.php`
Features:
- Radio button selector for import type (Products, Clients, Suppliers)
- Template download link with dynamic type
- File upload form with type validation
- Alert box showing import rules
- Export section with links to data exports

---

## 3. EXPORT FEATURES

### 3.1 Overview
Multiple export systems for different data types using Maatwebsite/Excel. Exports support filtering, formatting, and can be queued.

### 3.2 Core Export Classes

#### Standalone Export Classes

**1. ProductsExport** (`app/Exports/ProductsExport.php`)
- Implements: FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
- Columns: Référence, Code-barres, Désignation, Famille, Marque, Unité, TVA (%), Prix achat, Prix vente, Stock min, Stock max, Description
- Filters: search, family_id, brand_id, type
- Relations loaded: family, brand, unit, taxRate
- Returns: XLSX file with styled headers

**2. InvoicesExport** (`app/Exports/InvoicesExport.php`)
- Implements: FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle, **ShouldQueue**
- Columns: N° Facture, Client, Date émission, Date échéance, Statut, Total HT, TVA, Total TTC, Montant payé, Reste à payer, Créé par
- Filters: status, client_id, date_from, date_to
- **Queued for performance** on large datasets

**3. ClientPaymentsExport** (`app/Exports/ClientPaymentsExport.php`)
- Implements: FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
- Columns: N° Paiement, Client, Date paiement, Mode de paiement, Compte, Montant, Alloué, Non alloué, Référence, Statut
- Filters: client_id, payment_method_id, date_from, date_to
- Relations loaded: client, paymentMethod, cashAccount

**4. StockMovementsExport** (`app/Exports/StockMovementsExport.php`)
- Implements: FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
- Columns: Date, Produit, Référence, Entrepôt, Type mouvement, Quantité, P.U., Total, Méthode valorisation, CMP après, N° lot, Créé par
- Filters: product_id, warehouse_id, type, date_from, date_to
- Type labels: Entrée, Sortie, Transfert, Ajustement, Retour client, Retour fournisseur

#### Accounting Exports (folder: `app/Exports/Accounting/`)

**1. BalanceExport** - Balance sheet export
**2. GrandLivreExport** - General ledger export
**3. JournauxExport** - Journal entries export

All use: FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle

#### Report Exports (folder: `app/Exports/Reports/`)

**1. CaReportExport** - Sales revenue report
**2. MarginsReportExport** - Profit margins report
**3. SalesPerformanceExport** - Sales performance by user

### 3.3 Export Routes (Partially Implemented)

**Inline Exports (Already working):**

1. **ProductController - Products export**
   - URL: `GET /products?export=1`
   - Method: `ProductController->index()`
   - File: `articles-{date}.xlsx`

2. **ReportController - Multiple reports**
   - CA Report: `GET /rapports/ca?export=excel`
   - Margins Report: `GET /rapports/marges?export=excel`
   - Sales Performance: `GET /rapports/performance-ventes?export=excel`

3. **LedgerController - Accounting exports**
   - Grand Livre: `GET /comptabilite/grand-livre/export`
   - Journals: `GET /comptabilite/journaux/export`
   - Balance: `GET /comptabilite/balance/export`

**Referenced but NOT YET IMPLEMENTED routes:**

These routes are referenced in the import view but routes not defined:
- `route('exports.invoices')` - Invoices export
- `route('exports.products')` - Products export
- `route('exports.stock-movements')` - Stock movements export
- `route('exports.clients')` - Clients export
- `route('exports.client-payments')` - Client payments export (likely)

### 3.4 Database Tables Involved

**Exports read from:**
- invoices, invoice_items
- products, product_families, brands, units, tax_rates
- clients, client_payments
- stock_movements, warehouses
- users (for created_by tracking)
- journal_entries, journal_entry_lines, accounts, account_classes
- supplier_invoices, suppliers

---

## 4. FILE STRUCTURE

```
app/
├── Http/Controllers/
│   ├── AttachmentController.php        ← File upload/download
│   ├── ImportController.php            ← Bulk import
│   ├── ReportController.php            ← Report exports
│   ├── ProductController.php           ← Product export
│   └── Accounting/
│       └── LedgerController.php        ← Accounting exports
├── Models/
│   ├── Attachment.php                  ← Attachment model
│   ├── Traits/
│   │   └── HasAttachments.php          ← Polymorphic attachment trait
│   ├── Invoice.php
│   ├── Client.php
│   ├── Product.php
│   └── [other models]
├── Imports/
│   ├── ProductsImport.php
│   ├── ClientsImport.php
│   └── SuppliersImport.php
├── Exports/
│   ├── ProductsExport.php
│   ├── InvoicesExport.php
│   ├── ClientPaymentsExport.php
│   ├── StockMovementsExport.php
│   ├── Accounting/
│   │   ├── BalanceExport.php
│   │   ├── GrandLivreExport.php
│   │   └── JournauxExport.php
│   └── Reports/
│       ├── CaReportExport.php
│       ├── MarginsReportExport.php
│       └── SalesPerformanceExport.php
├── Jobs/
│   ├── GenerateDocumentPdfJob.php      ← PDF generation
│   ├── SendInvoiceEmailJob.php
│   └── SendRelanceEmailJob.php

config/
├── filesystems.php                      ← File storage config

resources/views/
└── import/
    └── index.blade.php                 ← Import/Export UI

routes/
└── web.php                             ← All routes defined here
```

---

## 5. CONFIGURATION FILES

### `config/filesystems.php`
Defines storage disks for file storage. Default uses 'local' disk for attachments.

### Key Libraries Used
- **maatwebsite/excel** - Excel/CSV import/export
- **laravel/framework** - Core file handling
- **barryvdh/laravel-dompdf** - PDF generation (for documents)

---

## 6. CAPABILITIES & STATUS

### ✅ Currently Working
- Attachment upload system (complete infrastructure)
- Bulk import for Products, Clients, Suppliers
- Export for Products, Invoices, Client Payments, Stock Movements
- Accounting exports (Journaux, Grand Livre, Balance)
- Report exports (CA, Margins, Sales Performance)
- Inline exports via query parameters
- CSV template downloads for imports

### ⚠️ Ready But Not Yet Used
- **HasAttachments trait** - Defined but no models use it
- **Attachment relationships** - Not linked to Invoice, Client, Supplier, etc.
- **Standalone export routes** - Views reference routes that don't exist yet

### 🚧 Missing/Incomplete
- Integration of attachments with models (needs HasAttachments trait to be applied)
- Proper export routes for invoices, clients, stock movements
- Attachment UI components in forms (no blade components)
- Document management views for viewing/managing attachments
- Export filtering UI for most export types
- Multi-file upload support via UI

---

## 7. RECOMMENDED NEXT STEPS

### To Enable Attachments on Models:
1. Add `use HasAttachments;` to Invoice, Client, Supplier, and other relevant models
2. Create blade component for attachment upload UI
3. Add attachment forms to invoice/client/supplier edit views
4. Create routes/view for managing attachments of a specific entity

### To Complete Export Routes:
1. Create export controller or move export logic to existing controllers
2. Define routes for: invoices, clients, stock_movements exports
3. Add filtering UI to import/index view for export data selection

### To Enhance Document Management:
1. Add attachment viewing/management to document detail views
2. Create document download/archive functionality
3. Add attachment versioning/audit trail
4. Implement file scanning for security
5. Add document templates and auto-generation

---

## 8. API INTEGRATION

### API Routes (Partial)
Located in `routes/api.php` - Focus on read-only data access, no imports/exports exposed yet.

Endpoints include:
- `GET /api/products`
- `GET /api/invoices`
- `GET /api/clients`
- `GET /api/stock/movements`

---

## Summary Statistics

| Feature | Status | Components |
|---------|--------|------------|
| **Document Attachments** | Infrastructure Ready | 1 Model, 1 Trait, 1 Controller, 3 Routes |
| **Data Import** | ✅ Working | 1 Controller, 3 Import Classes, 3 Routes, 1 View |
| **Data Export** | ⚠️ Partially Working | 10+ Export Classes, 6+ Routes, References for more |
| **File Storage** | ✅ Working | Local disk configured, polymorphic attachment storage |

