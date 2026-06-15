# Blueprint Module PRODUCTION — niveau MES (Odoo MRP / Sage X3 / SAP B1)
### Usine tôles bac · charpentes · hangars — A3 ERP Laravel · SYSCOHADA · Burkina Faso

> **Légende d'état** · ✅ livré & testé · 🔶 partiel · ⬜ à construire
> Base : `app/Modules/Production` (12 tables, 11 controllers, 6 services, 125 tests verts).

---

## 0. Synthèse existant vs cible

| Domaine | État | Détail |
|---|---|---|
| OF + workflow + numérotation | ✅ | brouillon→lancé→en_cours→terminé→annulé |
| Nomenclatures (BOM) | ✅ | + composants, taux perte, conso/m, temps machine |
| Bobines / matières | ✅ | poids, lot, couleur, épaisseur, coût/kg, statut |
| Machines / lignes | ✅ | coût horaire, statut |
| Découpe & profilage (exécution) | ✅ | consommation, sorties PF, chutes, rendement |
| Contrôle qualité | ✅ | 4 critères + verdict + rejet |
| Coût de revient | ✅ | matière/MO/machine/indirect/marge |
| Compta SYSCOHADA | ✅ | OFF-défaut, 6032/321, 361/736 |
| Stock (sortie MP / entrée PF) | ✅ | via StockService CMP |
| Ventes → OF | ✅ | bouton « Lancer en production » + prefill |
| MRP réappro bobines → achats | ✅ | déficit vs stock_min → demande d'achat |
| Dashboard + 8 rapports PDF/Excel | ✅ | |
| **Gammes opératoires** (routings) | ⬜ | séquence d'opérations par BOM |
| **Centres de travail** (work centers) | 🔶 | machines existent, pas de capacité/calendrier |
| **Planification / ordonnancement** | ⬜ | pas de Gantt/charge capacitaire |
| **Produits semi-finis** (multi-niveaux) | ⬜ | BOM mono-niveau actuellement |
| **Maintenance** préventive/corrective | ⬜ | |
| **Réservation stock** (commande↔OF) | ⬜ | |
| **RH/Paie** (temps opérateur → coût MO réel) | 🔶 | operator_id tracé, pas de pointage→paie |
| **Analyse rentabilité** (par commande/produit) | 🔶 | marge OF existe, pas d'agrégat multi-OF |
| **Optimisation découpe** (nesting longueurs) | ⬜ | |
| **Trésorerie** (prévision coûts prod) | ⬜ | |

---

## 1. Arborescence complète du module (menus)

```
🏭 PRODUCTION
├── 📊 Tableau de bord                         ✅
├── ⚙️ Données techniques
│   ├── Nomenclatures (BOM)                    ✅
│   ├── Gammes opératoires (routings)          ⬜
│   ├── Centres de travail                     🔶
│   ├── Machines & équipements                 ✅
│   └── Opérateurs                             🔶 (Employee)
├── 📦 Matières
│   ├── Bobines (matières 1res)                ✅
│   └── Réappro MRP                            ✅
├── 🗓️ Planification
│   ├── Plan de charge (capacité)              ⬜
│   └── Ordonnancement (Gantt)                 ⬜
├── 🧾 Ordres de fabrication
│   ├── Liste / création / détail             ✅
│   ├── Lancement                             ✅
│   └── Suivi temps réel                       ✅
├── 🏗️ Exécution atelier
│   ├── Découpe & profilage                    ✅
│   ├── Consommation matières                  ✅
│   ├── Déclaration semi-finis                 ⬜
│   └── Déclaration produits finis             ✅
├── ✔️ Qualité
│   ├── Contrôles                              ✅
│   ├── Rebuts                                 ✅
│   └── Pertes / chutes                        ✅
├── 🔧 Maintenance
│   ├── Préventive (plans)                     ⬜
│   └── Corrective (interventions)             ⬜
├── 💰 Coûts & rentabilité
│   ├── Coût de revient par OF                 ✅
│   ├── Analyse de rentabilité                 🔶
│   └── Produits en cours (WIP)                ⬜
├── 📈 Rapports                                ✅
└── ⚙️ Paramétrage                             🔶
```

---

## 2. Sous-modules

| Sous-module | Rôle | Service principal |
|---|---|---|
| Données techniques | BOM, gammes, centres, machines | BillOfMaterial, (RoutingService ⬜) |
| Matières | bobines, MRP | CoilConsumptionService, MrpService |
| Planification | charge, ordonnancement | (PlanningService ⬜) |
| OF | cycle de vie | ProductionService |
| Exécution | conso, sorties, semi-finis | ProductionStockService |
| Qualité | contrôles, rebuts | ProductionQualityController |
| Maintenance | préventif/correctif | (MaintenanceService ⬜) |
| Coûts | revient, rentabilité, WIP | ProductionCostService |
| Compta | écritures auto | ProductionAccountingService |

---

## 3. Tables de base de données

### Existantes (12) ✅
`production_machines, production_lines, coils, bills_of_materials, bom_lines, production_orders, production_order_lines, production_consumptions, production_outputs, production_wastes, production_quality_controls, production_costs`
→ schéma détaillé dans `docs/production-module.md`.

### À ajouter pour niveau MES ⬜
```
work_centers              (id, company_id, code, name, machine_id?, capacity_per_day,
                           cost_per_hour, calendar_id?, is_active)
operations                (id, company_id, bom_id, sequence, work_center_id, name,
                           setup_time, run_time_per_unit, labor_rate)         -- gammes
production_order_operations(id, production_order_id, operation_id, work_center_id,
                           planned_start, planned_end, real_start, real_end,
                           status, operator_id)                               -- suivi gamme
production_schedules      (id, company_id, work_center_id, production_order_id,
                           date, planned_load_hours, status)                  -- plan charge
semi_finished_outputs     (id, production_order_id, product_id, quantity, ...) -- semi-finis
                           (ou réutiliser production_outputs + flag is_semi_finished)
stock_reservations        (id, company_id, order_id, product_id, warehouse_id,
                           quantity, status)                                  -- réservation
maintenance_plans         (id, company_id, machine_id, frequency_days,
                           last_done_at, next_due_at, is_active)
maintenance_interventions (id, company_id, machine_id, type[preventive/corrective],
                           started_at, ended_at, cost, downtime_minutes, notes)
labor_time_entries        (id, company_id, production_order_id, employee_id,
                           hours, hourly_cost, entry_date)                    -- pointage MO
```
Conventions : `company_id`, `created_by`, `timestamps`, `softDeletes`, index FK.

---

## 4. Relations entre tables (cible)

```
work_center 1─n machine
bom 1─n operation ──► work_center
production_order n─1 bom
production_order 1─n production_order_operation ──► operation, work_center, employee
production_order 1─n labor_time_entry ──► employee        (→ coût MO réel)
production_order 1─n semi_finished_output / production_output
order(vente) 1─n stock_reservation
machine 1─n maintenance_plan / maintenance_intervention
```
Relations existantes : voir `docs/production-module.md` §4.

---

## 5. Relations métiers inter-modules

| Module | Flux | État |
|---|---|---|
| **Ventes → Prod** | Commande validée → bouton « Lancer en production » → OF pré-rempli | ✅ |
| | Réservation PF/matière pour le client (`stock_reservations`) | ⬜ |
| | Suivi délai fabrication (planned_end OF → date commande) | 🔶 |
| **Prod → Achats** | Déficit bobine (`stock_min`) → MRP → demande d'achat | ✅ |
| | Réception bobine → création Coil + coût matière | 🔶 |
| **Prod ↔ Stock** | Sortie MP (poids bobine) + entrée PF (StockService CMP) | ✅ |
| | Réservation / inventaire bobines | 🔶 |
| **Prod → Compta** | Conso DR 6032/CR 321 · Production stockée DR 361/CR 736 | ✅ |
| | WIP (en-cours) DR 33x/CR 72 · rebuts DR 6xx | 🔶 |
| **Prod → Trésorerie** | Prévision coûts production (somme coûts OF planifiés) | ⬜ |
| **Prod ↔ RH/Paie** | operator_id sur chutes/QC | 🔶 |
| | Pointage temps (`labor_time_entries`) → coût MO réel + productivité | ⬜ |

---

## 6. Workflows complets

### 6.1 Commande → Livraison (cible)
```
Commande client (confirmée)
  → Vérif stock PF / matière           [StockService + MrpService]
  → Création OF (depuis commande)      ✅
  → Réservation matières               ⬜ stock_reservations
  → Planification (charge centres)     ⬜ PlanningService
  → Lancement → en_cours               ✅
  → Consommation bobines               ✅ (sortie MP)
  → Déclaration semi-finis             ⬜
  → Déclaration PF                     ✅ (entrée stock)
  → Contrôle qualité                   ✅ (bloque si non conforme)
  → Clôture OF → coût + compta         ✅
  → Livraison (BL)                     ✅ module Ventes
  → Facturation                        ✅ module Ventes
  → Comptabilisation                   ✅ AccountingService
```

### 6.2 Gamme opératoire (cible ⬜)
```
OF lancé → générer opérations depuis gamme BOM
  → pour chaque opération : affecter centre + opérateur
  → pointage début/fin → temps réel → coût MO réel
  → avancement % OF = opérations terminées / total
```

### 6.3 Maintenance (cible ⬜)
```
Plan préventif (fréquence) → next_due_at atteint → alerte
  → intervention → machine status=maintenance → downtime → coût
Panne → intervention corrective → indispo centre → replanification
```

---

## 7. Écrans & formulaires

| Écran | État |
|---|---|
| Dashboard KPIs + graphes | ✅ |
| Liste OF (filtres, statut coloré) | ✅ |
| Création OF (coupes dynamiques) | ✅ |
| Détail OF (frise workflow, panneaux exécution) | ✅ |
| Bobines (table + progression poids) | ✅ |
| BOM (form + lignes) | ✅ |
| Machines / lignes | ✅ |
| Contrôle qualité (inline OF) | ✅ |
| Coût de revient (carte OF) | ✅ |
| MRP réappro | ✅ |
| Rapports (8 types + PDF/Excel) | ✅ |
| **Gamme opératoire** (séquence + centres) | ⬜ |
| **Plan de charge / Gantt** | ⬜ |
| **Pointage atelier** (terminal opérateur) | ⬜ |
| **Maintenance** (plans + interventions) | ⬜ |
| **Analyse rentabilité** (par commande/produit) | 🔶 |

---

## 8. Permissions

Existantes (9) ✅ : `production.view/create/update/delete/launch/validate/cancel/cost.view/report.view` + rôle `chef_production`.

À ajouter ⬜ : `production.plan` (planification), `production.maintenance`, `production.quality.validate`, `production.operation.declare` (pointage), `production.reservation`.

---

## 9. KPI & tableaux de bord

### Existants ✅
Production jour/mois (m + u) · rendement matière · taux perte · coût moyen/m · OF en cours/terminés · alertes bobines · top clients · production journalière (graphe) · OF par statut.

### Cible MES ⬜
- **OEE** (TRS) = disponibilité × performance × qualité par machine.
- **Taux de charge** centres (capacité vs planifié).
- **Respect délais** (OTD — on-time delivery).
- **Productivité opérateur** (m/h, coût MO/m).
- **WIP** (valeur en-cours).
- **MTBF/MTTR** maintenance.
- **Marge par commande / par produit** (rentabilité).

---

## 10. Écritures comptables SYSCOHADA automatiques

### Existantes ✅ (OFF par défaut)
| Événement | Débit | Crédit |
|---|---|---|
| Consommation matière | 6032 Variation stocks MP | 321 Stocks MP |
| Production stockée (PF) | 361 Stocks PF | 736 Production stockée |

### Cible ⬜
| Événement | Débit | Crédit |
|---|---|---|
| Production en-cours (WIP) | 335 Produits en cours | 736 |
| Main-d'œuvre directe imputée | 33x / 72 | 661 (via OD analytique) |
| Rebuts / pertes valorisées | 6581 Pertes | 361 / 321 |
| Réception bobine (achat) | 321 Stocks MP + 4452 TVA | 401 Fournisseur |
| Maintenance | 624 Entretien | 401 / 52 |

Respect SYSCOHADA : classes 3 (stocks), 6 (charges), 7 (produits) ; comptabilité analytique par OF (axe « ordre de fabrication »).

---

## 11. Plan d'implémentation Laravel (incrémental, sur l'existant)

> Principe : **étendre `app/Modules/Production`**, ne pas reconstruire. Phase par phase, tests verts à chaque étape.

| Phase | Livrable | Tables | Services | Effort |
|---|---|---|---|---|
| **P8** | Réservation stock Ventes↔Prod | stock_reservations | ReservationService | S |
| **P9** | Réception bobine ← Achats | (réutilise Reception) | CoilReceptionService | S |
| **P10** | Centres de travail + capacité | work_centers | — | M |
| **P11** | Gammes opératoires (routings) | operations, production_order_operations | RoutingService | L |
| **P12** | Planification / plan de charge | production_schedules | PlanningService | L |
| **P13** | Pointage temps → coût MO réel + RH | labor_time_entries | LaborService | M |
| **P14** | Semi-finis (BOM multi-niveaux) | flag production_outputs | — | M |
| **P15** | Maintenance préventive/corrective | maintenance_plans, _interventions | MaintenanceService | M |
| **P16** | WIP + écritures compta étendues | — | ProductionAccountingService+ | M |
| **P17** | Analyse rentabilité + KPI MES (OEE/OTD) | (vues SQL) | ProfitabilityService | M |
| **P18** | Optimisation découpe (nesting longueurs) | — | CuttingOptimizerService | L |

Chaque phase : migration + modèles + service + controller + vues + permissions + Feature tests + `composer dump-autoload` / `route:list` / `migrate:status`.

---

## 12. Règles de gestion (synthèse)

1. Bobine = source de vérité stock matière (pas de double-stock).
2. PF entre en stock via StockService (CMP).
3. Consommation/sortie/chute seulement si OF `en_cours` ; reverse interdit hors `en_cours`.
4. Facturation bloquée si production non terminée OU QC non conforme.
5. Coût figé à la clôture OF ; compta idempotente (anti-double-écriture).
6. Multi-sociétés strict (`HasCompanyScope` qualifié) ; audit `created_by`.
7. SYSCOHADA : analytique par OF, compta production activable (OFF défaut).

---

*Document de conception — état au build courant (125 tests verts). Voir `docs/production-module.md` pour le détail technique de l'existant.*
