# Skill : laravel-performance

Expert performance Laravel 12 pour l'ERP IBOA.
Détecte et corrige les problèmes de performance : requêtes N+1, index manquants,
caches non utilisés, mémoire excessive, temps de réponse lents.

## Contexte projet

- **Framework** : Laravel 12 / PHP 8.2
- **DB** : MySQL 8 — `iboa_erp` (140 tables, données de production)
- **ORM** : Eloquent avec 123 modèles
- **Chemin** : `C:/laragon/www/iboa`

## Diagnostic N+1 — méthode

### Activation du log de requêtes
```php
// Dans AppServiceProvider ou temporairement en tinker
DB::enableQueryLog();
// ... exécuter le code ...
$queries = DB::getQueryLog();
echo count($queries) . ' requêtes';
```

### Détection statique dans le code
```bash
# Trouver les boucles avec accès relation sans eager loading
grep -rn "foreach\|->each\|->map" app/Http/Controllers --include="*.php" -A3 \
  | grep -B3 "\$.*->[a-z]" | head -50
```

### Patterns N+1 typiques à corriger
```php
// ❌ N+1
$invoices = Invoice::all();
foreach ($invoices as $inv) {
    echo $inv->client->name;  // 1 requête par facture
}

// ✅ Eager loading
$invoices = Invoice::with('client:id,name')->get();
```

## Eager loading recommandé par module

### Ventes
```php
Invoice::with(['client:id,name,code', 'items.product:id,name,reference', 'payments'])
Quote::with(['client:id,name', 'items.product:id,name', 'assignedTo:id,name'])
```

### Achats
```php
PurchaseOrder::with(['supplier:id,name', 'items.product:id,name', 'approvals'])
Reception::with(['purchaseOrder.supplier', 'items.product:id,name'])
```

### RH/Paie
```php
PayrollRun::with(['items.employee:id,first_name,last_name,matricule', 'period'])
Employee::with(['activeContract', 'department:id,name', 'allowances.type'])
```

### Stock
```php
StockMovement::with(['product:id,name,reference', 'warehouse:id,name'])
ProductStock::with(['product:id,name', 'warehouse:id,name'])
```

## Index critiques — vérification

```sql
-- Index présents
SHOW INDEX FROM invoices;
SHOW INDEX FROM stock_movements;

-- Index manquants fréquents
-- Table invoices
ALTER TABLE invoices ADD INDEX idx_status_company (status, company_id);
ALTER TABLE invoices ADD INDEX idx_client_status (client_id, status);
ALTER TABLE invoices ADD INDEX idx_due_date (due_date);

-- Table stock_movements
ALTER TABLE stock_movements ADD INDEX idx_product_warehouse (product_id, warehouse_id);
ALTER TABLE stock_movements ADD INDEX idx_occurred_at (occurred_at);

-- Table payroll_items
ALTER TABLE payroll_items ADD INDEX idx_run_employee (payroll_run_id, employee_id);
```

## Caches applicatifs

```bash
# État des caches
php artisan cache:clear
php artisan config:cache    # config.php compilé
php artisan route:cache     # routes.php compilé
php artisan view:cache      # Blade précompilé
php artisan event:cache     # listeners compilés
```

### Cache applicatif (données)
```php
// Cache d'une requête lourde
$stats = Cache::remember('dashboard_stats_' . $companyId, 300, function () use ($companyId) {
    return [
        'invoices_count' => Invoice::where('company_id', $companyId)->count(),
        // ...
    ];
});

// Invalider sur changement
Cache::forget('dashboard_stats_' . $companyId);
```

## Optimisation des colonnes `select`

```php
// ❌ Charge toutes les colonnes
$clients = Client::all();

// ✅ Seulement ce dont on a besoin
$clients = Client::select('id', 'code', 'name', 'phone', 'balance')->get();

// ✅ Avec contrainte
$clients = Client::select('id', 'name')->where('is_active', true)->orderBy('name')->get();
```

## Pagination — règles

```php
// Toujours paginer les grandes listes
$invoices = Invoice::with('client:id,name')
    ->orderByDesc('created_at')
    ->paginate(25);  // jamais ->get() sur tables volumineuses

// Pour les exports CSV, utiliser chunk
Invoice::where('status', 'valide')->chunk(500, function ($chunk) use (&$rows) {
    foreach ($chunk as $inv) {
        $rows[] = [...];
    }
});
```

## Profiling — commandes

```bash
# Temps de réponse d'une route spécifique
curl -o /dev/null -s -w "%{time_total}s\n" http://127.0.0.1/iboa/public/dashboard

# Lister les 10 requêtes les plus lentes (si slow query log MySQL activé)
# Dans my.ini : slow_query_log=1, long_query_time=1
tail -100 /usr/local/mysql/data/slow.log

# Taille des tables
php artisan tinker --execute="
DB::select('SELECT table_name, round((data_length+index_length)/1024/1024,1) AS size_mb
  FROM information_schema.tables WHERE table_schema=\"iboa_erp\"
  ORDER BY size_mb DESC LIMIT 10')
  ->each(fn(\$r) => echo \$r->table_name . ': ' . \$r->size_mb . ' MB\n');
"
```

## Checklist performance avant mise en production

```
□ php artisan config:cache
□ php artisan route:cache
□ php artisan view:cache
□ php artisan event:cache
□ composer install --optimize-autoloader --no-dev
□ Vérifier que public/hot est absent
□ APP_DEBUG=false dans .env
□ Index sur colonnes WHERE/JOIN fréquentes
□ eager loading sur toutes les relations en boucle
□ Pagination sur toutes les listes > 100 enregistrements
```

## Format de rapport

```
## Rapport Performance IBOA

### Problèmes critiques (impact immédiat)
1. ❌ N+1 sur Invoice::all() dans InvoiceController@index — ajouter with('client')
2. ❌ Index manquant sur invoices(status, company_id) — +X ms par requête

### Améliorations recommandées
1. ⚠️ PayrollRunController@show — 47 requêtes détectées, eager load items.employee

### Déjà optimisé
✅ Routes cachées
✅ Config cachée
```
