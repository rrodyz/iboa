# Skill : erp-stock

Expert gestion de stock pour l'ERP IBOA.
Intervient sur le module Stock : mouvements, inventaires, valorisation,
transferts inter-dépôts, numéros de lot/série, alertes de réapprovisionnement.

## Contexte projet

- **Modèles clés** :
  `Product`, `ProductStock`, `StockMovement`, `StockLot`,
  `Warehouse`, `WarehouseLocation`, `InventorySession`, `InventoryItem`,
  `StockTransfer`, `StockTransferItem`
- **Contrôleurs** : `app/Http/Controllers/Stock/`
- **Services** : `app/Services/InventoryService.php`, `app/Services/StockTransferService.php`
- **Vues** : `resources/views/stock/`

## Méthodes de valorisation supportées

| Code | Méthode | Calcul |
|------|---------|--------|
| `pmp` | Prix moyen pondéré | (stock_valeur + entrée_valeur) / (stock_qté + entrée_qté) |
| `fifo` | Premier entré, premier sorti | Utilise `stock_lots` avec dates |
| `cump` | Coût unitaire moyen pondéré | Recalculé à chaque entrée |

Méthode par défaut : `pmp` — stockée dans `products.valuation_method`.
Colonne `products.weighted_avg_cost` = PMP courant.

## Types de mouvements (`stock_movements.type`)

| Type | Direction | Déclencheur |
|------|-----------|-------------|
| `entree` | + stock | Réception achat, retour client |
| `sortie` | − stock | Livraison client, retour fournisseur |
| `ajustement_plus` | + stock | Inventaire : surplus |
| `ajustement_moins` | − stock | Inventaire : manquant |
| `transfert_out` | − stock dépôt source | Transfert inter-dépôts |
| `transfert_in` | + stock dépôt dest | Transfert inter-dépôts |
| `production_in` | + stock | Assemblage / fabrication |
| `production_out` | − stock | Consommation composants |

## Tables clés — colonnes importantes

### `product_stocks`
- `product_id`, `warehouse_id` — combinaison unique
- `quantity` — quantité disponible
- `reserved_quantity` — réservé par commandes non livrées
- `available_quantity` = `quantity - reserved_quantity`

### `stock_movements`
- `reference_type` / `reference_id` — polymorphique (Reception, DeliveryNote, etc.)
- `unit_cost` — coût unitaire au moment du mouvement
- `avg_cost_after` — PMP après mouvement (calculé par service)
- `lot_number` / `serial_number` / `expiry_date` — traçabilité

### `products`
- `is_stockable` — seuls les produits stockables ont des mouvements
- `stock_min` / `stock_max` / `reorder_point` — seuils alertes
- `has_serial_number` / `has_lot_number` / `has_expiry_date`

## Règles de gestion

1. **Stock négatif interdit** : Vérifier `available_quantity >= quantité_sortie` avant tout mouvement.
2. **PMP recalculé à chaque entrée** : `nouveau_pmp = (ancien_stock × ancien_pmp + qté_entrée × coût) / (ancien_stock + qté_entrée)`
3. **Lots FIFO** : Pour les produits `has_lot_number=true`, utiliser les lots les plus anciens en premier.
4. **Inventaire** : Bloquer les mouvements pendant une session d'inventaire ouverte (`status = 'en_cours'`).
5. **Transfert** : Débit dépôt source + crédit dépôt destination dans la même transaction DB.

## Vérifications courantes

```bash
# Stock global par produit
php artisan tinker --execute="
App\Models\ProductStock::with('product','warehouse')
  ->where('quantity','<',0)
  ->get(['product_id','warehouse_id','quantity'])
  ->each(fn(\$s) => echo \$s->product->name . ' @ ' . \$s->warehouse->name . ' : ' . \$s->quantity . PHP_EOL);
"

# Produits sous seuil d'alerte
php artisan tinker --execute="
App\Models\Product::whereNotNull('reorder_point')
  ->whereHas('stocks', fn(\$q) => \$q->whereRaw('quantity <= reorder_point'))
  ->pluck('name')
  ->each(fn(\$n) => echo \$n . PHP_EOL);
"

# Cohérence PMP (produits avec pmp=0 mais stock>0)
php artisan tinker --execute="
App\Models\Product::where('weighted_avg_cost',0)
  ->whereHas('stocks', fn(\$q) => \$q->where('quantity','>',0))
  ->pluck('name','id')
  ->each(fn(\$n,\$id) => echo \$id . ': ' . \$n . PHP_EOL);
"
```

## Intégrations

| Module source | Action | Mouvement généré |
|---------------|--------|------------------|
| Réceptions achat | Validation réception | `entree` |
| Livraisons client | Validation BL | `sortie` |
| Retours fournisseur | Validation retour | `sortie` |
| Retours client | Avoir validé | `entree` |
| Inventaire | Clôture session | `ajustement_plus` / `ajustement_moins` |
| Transfert | Validation transfert | `transfert_out` + `transfert_in` |

## Écriture comptable SYSCOHADA associée

| Mouvement | Débit | Crédit |
|-----------|-------|--------|
| Entrée stock achat | 311 Stock | 401 Fournisseur |
| Sortie stock vente | 603 Variation stocks | 311 Stock |
| Ajustement + | 311 Stock | 759 Produits divers |
| Ajustement − | 659 Charges diverses | 311 Stock |

## Fichiers clés

```
app/Http/Controllers/Stock/StockController.php
app/Http/Controllers/Stock/WarehouseController.php
app/Services/InventoryService.php
app/Services/StockTransferService.php
resources/views/stock/
database/migrations/2026_04_05_112646_create_stock_lots_table.php
database/migrations/2026_05_18_151241_create_stock_transfers_tables.php
```
