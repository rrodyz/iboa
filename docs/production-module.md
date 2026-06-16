# Module Production / Fabrication tôles bac — Documentation technique

> ERP A3 — Laravel 12 / PHP 8.2 / MySQL 8 · SYSCOHADA · Multi-sociétés
> Emplacement : `app/Modules/Production/{Models,Controllers,Services}`

---

## 1. Analyse fonctionnelle

### 1.1 Périmètre
Gestion complète de la fabrication de tôles bac, de la **bobine (matière première)** au **produit fini livré**, avec traçabilité, coût de revient temps réel et intégration stock / ventes / achats / comptabilité SYSCOHADA.

### 1.2 Flux métier central
```
Commande client ──► Ordre de fabrication (OF) ──► Découpe/Profilage
        │                     │                          │
        │                     ▼                          ▼
        │            Consommation bobine          Sortie produit fini
        │            (sortie matière)             (entrée stock PF)
        │                     │                          │
        ▼                     ▼                          ▼
   (réservation)        Coût de revient            Contrôle qualité
                              │
                              ▼
                  Écritures SYSCOHADA (OFF par défaut)
```

### 1.3 Cycle de vie d'un OF
```
brouillon ──launch──► lancé ──start──► en_cours ──finish──► terminé
     └──────────────────── cancel (tout statut non clôturé) ──────────┘
```
- **brouillon / lancé** : éditable.
- **en_cours** : seule phase autorisant consommation matière, sorties PF, chutes.
- **terminé** : fige coût + déclenche écritures GL (si activées).
- Gardes d'intégrité : `reverse*` interdits hors `en_cours`.

### 1.4 Modules fonctionnels
Tableau de bord · Ordres de fabrication · Nomenclatures (BOM) · Bobines · Machines · Lignes · Découpe & profilage (exécution) · Consommations · Pertes/chutes · Contrôle qualité · Produits finis · Coûts de revient · **MRP réappro bobines** · Rapports (8 types, PDF/Excel).

### 1.5 Règles de gestion clés
- La **bobine** est la source de vérité du stock matière (poids + lot + coût/kg) — pas de double-stock dans `product_stocks`.
- Le **produit fini** entre dans le stock existant via `StockService` (CMP).
- **Rendement matière** = (consommé − chutes) / consommé.
- **Coût de revient** = matière (réel) + MO (BOM) + machine (temps×coût horaire) + frais indirects (%).
- **Marge** = CA estimé (prix vente × qté) − coût total.

---

## 2. Schéma base de données

12 tables. Conventions communes : `company_id` (multi-sociétés), `created_by`, `timestamps`, `softDeletes` (sauf tables-lignes), index sur FK.

### production_machines
| Colonne | Type | Note |
|---|---|---|
| id | bigint PK | |
| company_id | FK companies | |
| code | string(30) | unique(company_id, code) |
| name | string(120) | |
| type | enum | decoupe / profilage / mixte |
| hourly_cost | decimal(15,0) | coût machine/heure |
| status | enum | active / maintenance / arret |
| is_active | bool | |
| created_by | FK users | + timestamps, softDeletes |
Index : (company_id, is_active)

### production_lines
| id · company_id · machine_id (FK, nullOnDelete) · code · name · is_active · created_by · timestamps · softDeletes |
Unique (company_id, code).

### coils (bobines)
| Colonne | Type | Note |
|---|---|---|
| product_id | FK products (nullable) | matière première |
| supplier_id | FK suppliers | |
| reference | string(50) | unique(company_id, reference) |
| lot_number | string(50) | |
| color | string(50) | |
| thickness / width | decimal | mm |
| initial_weight / remaining_weight | decimal(12,2) | kg |
| estimated_length | decimal(12,2) | m |
| purchase_price | decimal(15,0) | total |
| cost_per_kg | decimal(15,2) | |
| received_at | date | |
| status | enum | disponible / en_production / epuisee |
Index : (company_id, status), (supplier_id).

### bills_of_materials (nomenclatures)
| product_id (fini) · name · sheet_type · thickness · coil_width · usable_width · standard_waste_rate(%) · consumption_per_meter(kg/m) · machine_time_per_unit(min) · labor_per_unit(min) · is_active |
Index : (company_id, is_active).

### bom_lines
| bill_of_material_id (FK cascade) · product_id (composant) · label · quantity_per_meter · unit_id · waste_rate |

### production_orders (OF)
| number (unique) · fiscal_year_id · client_id · order_id (commande) · product_id (fini) · bill_of_material_id · production_line_id · sheet_type · thickness · color · length(m) · usable_width(mm) · quantity_requested · quantity_produced · status(enum 5) · launched_at · finished_at · responsible_id · notes |
Index : (company_id, status), (client_id), (order_id).

### production_order_lines (détail coupes)
| production_order_id (cascade) · length(m) · quantity · total_meters(=length×quantity) · unit_id · label |

### production_consumptions
| production_order_id (cascade) · coil_id · weight_consumed(kg) · length_consumed(m) · cost · stock_movement_id · consumed_at |

### production_outputs (produits finis)
| production_order_id (cascade) · product_id · length · color · thickness · quantity · total_meters · unit_id · stock_movement_id · warehouse_id · produced_at |

### production_wastes (pertes/chutes)
| production_order_id (cascade) · machine_id · operator_id (FK employees) · type(reutilisable/non_reutilisable/rebut) · quantity · weight(kg) · value · reason |
Index : (type).

### production_quality_controls
| production_order_id (cascade) · thickness_ok · length_ok · color_ok · visual_ok (bool) · status(conforme/non_conforme/a_reprendre) · reason · rejected_quantity · controller_id (FK employees) · controlled_at |

### production_costs
| production_order_id (**unique**, cascade) · material_cost · labor_cost · machine_cost · overhead_cost · total_cost · cost_per_meter · cost_per_unit · margin |

---

## 3. Migration Laravel

Fichier unique : `database/migrations/2026_06_09_120000_create_production_tables.php`
- Crée les 12 tables dans l'ordre de dépendance (machines → lines → coils → BOM → bom_lines → orders → order_lines → consumptions → outputs → wastes → quality_controls → costs).
- FK via `foreignId()->constrained()->nullOnDelete()` (ou `cascadeOnDelete()` pour les lignes-enfant).
- Index sur toutes les FK et colonnes de filtre (status, type).
- `down()` : drop dans l'ordre inverse.

Commandes de contrôle :
```bash
composer dump-autoload
php artisan migrate:status   # 12 tables Ran, 0 pending
php artisan route:list | grep production
```

---

## 4. Relations Eloquent

Traits communs sur chaque modèle : `HasFactory`, `HasCompanyScope` (scope global multi-société), `HasCreator` (audit créateur), `SoftDeletes` (où pertinent).

```
ProductionOrder
 ├─ belongsTo: company, client, order, product, billOfMaterial, productionLine, responsible(User)
 ├─ hasMany:   lines, consumptions, outputs, wastes, qualityControls
 └─ hasOne:    cost

ProductionOrderLine  belongsTo: productionOrder, unit
BillOfMaterial       belongsTo: company, product ; hasMany: lines(BomLine)
BomLine              belongsTo: billOfMaterial, product, unit
Coil                 belongsTo: company, product(matière), supplier ; hasMany: consumptions
ProductionMachine    belongsTo: company ; hasMany: lines
ProductionLine       belongsTo: company, machine
ProductionConsumption belongsTo: company, productionOrder, coil, stockMovement
ProductionOutput     belongsTo: company, productionOrder, product, unit, warehouse, stockMovement
ProductionWaste      belongsTo: company, productionOrder, machine, operator(Employee)
ProductionQualityControl belongsTo: company, productionOrder, controller(Employee)
ProductionCost       belongsTo: company, productionOrder
```

Relation externe ajoutée : `Order::productionOrders()` (hasMany) — pont Ventes→Production.

Helpers métier `ProductionOrder` : `isEditable()`, `isInProgress()`, `totalMeters()`, `statusLabel()`.
Helpers `Coil` : `isAvailable()`, `consumptionRate()`, `statusLabel()`.

---

## 5. Contrôleurs

`app/Modules/Production/Controllers/` (11) — chacun gating Spatie + Form-Request-style validation + délégation aux services.

| Controller | Responsabilité | Permissions |
|---|---|---|
| ProductionDashboardController | KPIs temps réel, graphes | production.view |
| ProductionOrderController | CRUD OF + workflow (launch/start/finish/cancel) + prefill depuis commande | production.view/create/launch/validate/cancel/delete |
| BillOfMaterialController | CRUD nomenclatures + lignes | production.view/create |
| CoilController | CRUD bobines + réception | production.view/create |
| ProductionMachineController | CRUD machines | production.view/create |
| ProductionLineController | CRUD lignes | production.view/create |
| ProductionExecutionController | Consommation matière, sorties PF, chutes (+ reverse) | production.update |
| ProductionCostController | Calcul coût de revient | production.update |
| ProductionQualityController | Contrôles qualité | production.update |
| ProductionReportController | 8 rapports + export PDF/Excel | production.report.view |
| MrpController | Réappro bobines → demande d'achat | production.view/update |

---

## 6. Services métier

`app/Modules/Production/Services/` (6) — toute la logique métier, transactions DB, hors contrôleur.

| Service | Rôle |
|---|---|
| **ProductionService** | Cycle de vie OF : `create` (numérotation OF-AAAA-NNNN + lignes), `update`, `launch`, `start`, `finish` (→ post compta), `cancel`. Transitions gardées (`ValidationException`). |
| **CoilConsumptionService** | `consume(OF, coil, poids, longueur)` : crée consommation + décrémente poids bobine + resync statut (disponible→en_production→epuisee). `reverse()` restitue (garde en_cours). Coût = poids × coût/kg. |
| **ProductionStockService** | `recordOutput` : sortie PF + **entrée stock** via `StockService` (CMP) + incrément quantity_produced. `recordWaste` : chute valorisée au coût moyen consommé. `reverseOutput/reverseWaste` (gardes en_cours). |
| **ProductionCostService** | `compute(OF, opts)` : matière (réel) + MO (BOM×qté) + machine (temps×coût horaire ligne) + indirect (%). Coût/mètre, coût/unité, marge. Idempotent (`updateOrCreate`). |
| **ProductionAccountingService** | Pont SYSCOHADA **OFF par défaut** (`config/production.php`). À la clôture : DR 6032/CR 321 (conso matière), DR 361/CR 736 (production stockée). Idempotent par référence (`OF-xxxx-CONS` / `-PROD`), non bloquant. |
| **MrpService** | Analyse déficit poids matière (Σ bobines non épuisées < `stock_min` produit) → génère demande d'achat (réutilise `PurchaseRequestService`). |

### Intégrations transverses réutilisées (zéro doublon)
- `StockService::recordMovement` — entrées/sorties stock PF.
- `AccountingService` — post/line/account SYSCOHADA (plan comptable étendu : 321, 6032, 361, 736).
- `DocumentSequenceService` — numérotation OF (type `ordre_fabrication`).
- `PurchaseRequestService` — demandes d'achat MRP.

---

## 7. Sécurité & qualité

- **9 permissions** Spatie (`production.view/create/update/delete/launch/validate/cancel/cost.view/report.view`) + rôle `chef_production`.
- **Multi-sociétés** : `HasCompanyScope` (subquery `companies` qualifiée par table → safe en jointures).
- **Audit** : `HasCreator` (`created_by`).
- **Pagination serveur** partout (25/page).
- **Tests** : 13 fichiers Feature, **125 verts** (372 assertions) — workflow, exécution, coût, compta, rapports, seeders, factories, MRP.
- **Seeders** : `ProductionSeeder` (orchestrateur 100 OF + données cohérentes) + 10 sous-seeders + 11 factories.

### Déploiement
```bash
php artisan migrate                              # crée les 12 tables
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # permissions
php artisan db:seed --class=ProductionSeeder     # (optionnel) jeu de démo
# Activer la compta production : .env → PRODUCTION_ACCOUNTING_ENABLED=true
```
