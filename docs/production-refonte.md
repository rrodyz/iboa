# Refonte A3 ERP — ERP industriel centré PRODUCTION
### Fabrication métallique : tôles bac · charpentes · hangars · portails · ouvrages sur commande
### Laravel · SYSCOHADA · multi-société / multi-dépôt · Afrique de l'Ouest

> **Principe directeur** : la Production devient le **module central** ; Ventes, Achats, Stock, Compta, Trésorerie, RH, Maintenance, Qualité, CRM, Projets gravitent autour.
> **Méthode** : refonte **incrémentale** sur l'existant (`app/Modules/Production`, 12 tables, 125 tests verts) — **ne rien casser, éviter les doublons**.
> Compléments : `docs/production-module.md` (technique existant) · `docs/production-blueprint.md` (blueprint MES).
> Légende : ✅ livré · 🔶 partiel · ⬜ à construire.

---

## 1. Nouvelle architecture fonctionnelle (ERP centré production)

```
                          ┌─────────────────────────┐
              CRM ───────►│                         │◄─────── IMMOBILISATIONS
            VENTES ──────►│       PRODUCTION        │◄─────── MAINTENANCE
       PROJETS/CHANTIERS─►│   (cœur : OF, MRP,      │◄─────── QUALITÉ
            ACHATS ──────►│    BOM, gammes, coûts)  │◄─────── RH / PAIE
                          │                         │
                          └───────────┬─────────────┘
                                      │
                  ┌───────────────────┼───────────────────┐
                  ▼                   ▼                   ▼
               STOCK            COMPTABILITÉ          TRÉSORERIE
        (MP / SF / PF /        (valorisation,        (besoin financ.,
         chutes / rebuts)       écritures, WIP)       prévision, marge)
```

### Deux modes de fabrication
| Mode | Produits | Pilotage |
|---|---|---|
| **Make-to-stock / flux** | tôles bac, profils (transformation bobine) | OF standard + MRP bobines ✅ |
| **Make-to-order / projet** | charpentes, hangars, portails, ouvrages sur mesure | OF rattaché à un **Chantier/Projet** ⬜ (devis→projet→OF multiples→livraison sur site) |

---

## 2. Structure des menus (cible)

```
🏭 PRODUCTION (central)
├── Tableau de bord industriel                 ✅ (+ KPI MES ⬜)
├── Données techniques
│   ├── Nomenclatures (BOM) ✅ · multi-niveaux/SF ⬜
│   ├── Gammes (routings) ⬜ · Opérations ⬜
│   ├── Centres de travail ⬜ · Machines ✅ · Équipes ⬜
├── Planification industrielle
│   ├── Plan de charge ⬜ · Planning atelier (Gantt) ⬜ · Capacité ⬜
├── Ordres de fabrication ✅ · Work Orders (opérations) ⬜
├── Lancement & suivi temps réel ✅ · Pointage atelier ⬜
├── Matières : Bobines ✅ · Besoins/MRP ✅ · Réservation ⬜
├── Exécution : Consommation ✅ · SF ⬜ · PF ✅ · Lots/Batch ⬜
├── Qualité : Réception ⬜ · En-cours ✅ · PF ✅ · Non-conformités ⬜
├── Maintenance : Préventive ⬜ · Corrective ⬜ · Disponibilité ⬜
├── Coûts : Revient ✅ · Standard vs réel ⬜ · Écarts ⬜ · WIP ⬜ · Rentabilité 🔶
├── Rapports industriels ✅
└── Paramétrage 🔶
```

---

## 3. Diagramme relations métier (inter-modules)

```
CRM ─opportunité─► VENTES ─devis(coût prod)─► COMMANDE
   COMMANDE ─(analyse dispo stock)─► OF  ◄─PROJET/CHANTIER (make-to-order)
   OF ─besoin matière─► MRP ─► ACHATS ─réception─► STOCK(MP)
   OF ─consommation─► STOCK(MP↓) ; OF ─sortie─► STOCK(PF↑)
   OF ─temps─► RH/PAIE(coût MO) ; OF ─machine─► MAINTENANCE(dispo)
   OF ─clôture─► COMPTA(6032/321,361/736,WIP) ─► TRÉSORERIE(marge,prévision)
   MACHINE ─► IMMOBILISATIONS(amortissement→coût machine)
   QUALITÉ ─libération─► STOCK(PF dispo) ─► VENTES(livraison→facture)
```

---

## 4. Tables — existantes / à créer / à modifier

### 4.1 Existantes ✅ (12)
production_orders · production_order_lines · bills_of_materials · bom_lines · coils · production_machines · production_lines · production_consumptions · production_outputs · production_wastes · production_quality_controls · production_costs

### 4.2 Mapping spec point-7 → état
| Table spec | État | Mapping |
|---|---|---|
| production_orders / _lines | ✅ | tel quel |
| bills_of_materials / bom_lines | ✅ | tel quel |
| machines | ✅ | `production_machines` |
| production_consumptions / _outputs | ✅ | tel quel |
| quality_controls | ✅ | `production_quality_controls` |
| production_costs | ✅ | tel quel |
| **routings / routing_operations** | ⬜ | gammes opératoires |
| **work_centers** | ⬜ | centres de travail (capacité, calendrier) |
| **machine_maintenance** | ⬜ | plans + interventions |
| **production_losses** | 🔶 | = `production_wastes` (pertes) — garder, vue dédiée |
| **production_scraps** | 🔶 | `production_wastes.type='rebut'` — split optionnel |
| **production_batches** | ⬜ | lot de fabrication (traçabilité) |
| **production_planning** | ⬜ | plan de charge / ordonnancement |
| **production_time_logs** | ⬜ | pointage temps opérateur |
| **production_stock_moves** | 🔶 | **réutiliser `stock_movements`** (anti-doublon) — lien polymorphe vers OF ✅ |
| **production_operator_assignments** | ⬜ | affectation opérateurs↔OF/opération |

### 4.3 Nouvelles tables (DDL synthétique) ⬜
```
work_centers(id, company_id, code, name, machine_id?, capacity_hours_per_day,
             cost_per_hour, calendar_json, is_active, +audit)
routings(id, company_id, bom_id, code, name, is_active, +audit)
routing_operations(id, routing_id, sequence, work_center_id, name,
             setup_minutes, run_minutes_per_unit, labor_rate)
production_order_operations(id, production_order_id, routing_operation_id,
             work_center_id, operator_id, planned_start, planned_end,
             real_start, real_end, status)                        -- Work Orders
production_planning(id, company_id, work_center_id, production_order_id,
             date, planned_load_hours, status)
production_time_logs(id, company_id, production_order_id, employee_id,
             hours, hourly_cost, is_overtime, entry_date)
production_operator_assignments(id, production_order_id, employee_id, role, date)
production_batches(id, company_id, production_order_id, batch_number,
             quantity, status, produced_at)                       -- lot/traçabilité
machine_maintenance(id, company_id, machine_id, type[preventive|corrective],
             planned_at, started_at, ended_at, downtime_minutes, cost, notes)
stock_reservations(id, company_id, order_id?, production_order_id?, product_id,
             warehouse_id, quantity, status[reserved|released|consumed])
```

### 4.4 Tables existantes à modifier (additif, non destructif) 🔶
| Table | Ajout | Raison |
|---|---|---|
| `products` | `product_type`(MP/SF/PF), `is_semi_finished`, `standard_cost` | SF + coût standard |
| `production_orders` | `routing_id?`, `project_id?`, `batch_id?`, `priority`, `planned_start/end`, `cost_standard` | gammes, projets, planif, écarts |
| `bills_of_materials` | `routing_id?`, `parent_bom_id?`, `version` | multi-niveaux + versioning |
| `production_costs` | `energy_cost`, `standard_total`, `variance` | énergie + écart standard/réel |
| `production_wastes` | (rien — `type` couvre scrap/loss) | |
| `coils` | `profile`, `sheets_count`, `yield_rate` | spécifique tôle bac |

> `production_stock_moves` **non créée** : on réutilise `stock_movements` (polymorphe `reference_type=ProductionOrder`) — évite le doublon Stock exigé.

---

## 5. Relations entre tables (cible)
```
bom 1─n bom_line ; bom 1─1 routing ; bom n─1 parent_bom (multi-niveaux)
routing 1─n routing_operation ──► work_center
work_center 1─n machine
production_order n─1 bom, routing, project, batch
production_order 1─n production_order_operation ──► routing_operation, work_center, employee
production_order 1─n production_time_log, operator_assignment ──► employee
production_order 1─n consumption(coil), output(product), waste, quality_control
production_order 1─1 cost
order(vente) / production_order 1─n stock_reservation
machine 1─n machine_maintenance ; machine 1─1 immobilisation(amortissement)
```

---

## 6. Workflows complets

### 6.1 Make-to-order (commande → comptabilisation) — cible
```
CRM/Devis (coût prod estimé)
 → Commande client confirmée
 → Analyse dispo stock (PF ? MP ?)            [StockService + MrpService ✅]
 → Création OF (auto/manuel) [+ Projet si ouvrage]   ✅ / projet ⬜
 → Calcul besoins (MRP) → réservation MP       ✅ MRP / réservation ⬜
 → Achat MP manquante → réception → QC réception → stock MP   🔶
 → Planification (plan de charge centres)      ⬜
 → Lancement OF → Work Orders (opérations)     ✅ OF / WO ⬜
 → Pointage temps + consommation MP            conso ✅ / temps ⬜
 → Déclaration SF puis PF                       PF ✅ / SF ⬜
 → Contrôle qualité PF → libération             ✅ (bloque si non conforme)
 → Entrée stock PF (CMP)                        ✅
 → Clôture OF → coût réel + écart standard      coût ✅ / écart ⬜
 → Écritures SYSCOHADA (conso, prod stockée, WIP)   ✅ / WIP ⬜
 → Livraison (BL) → Facture → Encaissement      ✅ (Ventes/Tréso)
```

### 6.2 Règles de blocage
- Production : consommation/sortie seulement OF `en_cours`.
- Livraison/facture : bloquées si OF non terminé OU QC non conforme.
- Réservation : MP réservée non disponible pour autre OF.

---

## 7. Modèles · Controllers · Services (plan technique)

### Existant ✅
12 modèles · 11 controllers · 6 services (`ProductionService`, `CoilConsumptionService`, `ProductionCostService`, `ProductionStockService`, `ProductionAccountingService`, `MrpService`).

### À ajouter ⬜
| Couche | Éléments |
|---|---|
| Modèles | WorkCenter, Routing, RoutingOperation, ProductionOrderOperation, ProductionPlanning, ProductionTimeLog, OperatorAssignment, ProductionBatch, MachineMaintenance, StockReservation |
| Services | RoutingService, PlanningService (capacité/Gantt), LaborService (temps→coût MO+paie), MaintenanceService, ReservationService, StandardCostingService (écarts), CoilReceptionService |
| Controllers | WorkCenterController, RoutingController, ProductionPlanningController, WorkOrderController, MaintenanceController, ProfitabilityController |
| Policies | ProductionOrderPolicy, BomPolicy (au lieu du gating middleware seul) |
| API | `/api/production/orders`, `/work-orders/{id}/declare`, `/mrp/needs`, `/planning/load`, `/costs/{of}` (Sanctum + API Resources) |

---

## 8. Permissions (spec point-8)
Existantes ✅ : view/create/update/delete/launch/validate/cancel/cost.view/report.view.
À ajouter ⬜ : `production.consume`, `production.output.declare`, `production.quality.validate`, `production.cost.edit`, `production.plan`, `production.maintenance`, `production.configure`.

---

## 9. KPI tableau de bord (spec point-9)
| KPI | État |
|---|---|
| OF en cours / terminés / **en retard** | ✅ / retard ⬜ |
| Taux rendement matière · taux rebut | ✅ |
| Coût moyen/production · marge/commande | ✅ / marge agrégée 🔶 |
| Temps moyen fabrication | ⬜ (besoin time_logs) |
| Disponibilité machine (OEE/TRS) | ⬜ |
| Productivité opérateur | ⬜ |
| Conso réelle vs théorique · **coût réel vs standard** | ⬜ |
| Stock matière critique | ✅ (MRP) |

---

## 10. Écritures SYSCOHADA (spec compta)
Existant ✅ : conso DR 6032/CR 321 · production stockée DR 361/CR 736 (OFF défaut, idempotent).
Cible ⬜ : WIP DR 335/CR 736 · MO imputée (analytique) · rebuts DR 6581 · réception MP DR 321+4452/CR 401 · maintenance DR 624 · amortissement machine (immobilisations) DR 681/CR 28.

---

## 11. Plan d'implémentation Laravel (incrémental)

| Phase | Livrable | Effort | Valeur |
|---|---|---|---|
| P8 | Réservation stock (Ventes↔Prod, MP/PF) | S | ★★★ |
| P9 | Réception bobine ← Achats + QC réception | S | ★★★ |
| P10 | Centres de travail + capacité | M | ★★ |
| P11 | Gammes (routings) + Work Orders | L | ★★★ |
| P12 | Pointage temps → coût MO réel + RH/Paie | M | ★★★ |
| P13 | Planification / plan de charge (Gantt) | L | ★★ |
| P14 | Coût standard + analyse écarts | M | ★★★ |
| P15 | Semi-finis (BOM multi-niveaux) + batch/lot | M | ★★ |
| P16 | Maintenance + disponibilité (OEE) | M | ★★ |
| P17 | WIP + écritures compta étendues | M | ★★ |
| P18 | Projets/Chantiers (make-to-order ouvrages) | L | ★★★ |
| P19 | Immobilisations → coût machine (amortissement) | S | ★ |
| P20 | Rentabilité + KPI MES + API + exports | M | ★★ |

**Chaque phase** : migration (rollback `down()`) + modèles + service + controller + Policy + vues responsive + permissions + seeders réalistes + **Feature tests** + `composer dump-autoload` / `route:list` / `migrate:status`. Multi-société + multi-dépôt + SYSCOHADA respectés. Zéro doublon Stock/Vente/Achat/Compta (réutilisation services existants).

---

## 12. Contraintes respectées
✅ Laravel · interface moderne/responsive (Tailwind+Alpine) · multi-société (`HasCompanyScope`) · multi-dépôt (`warehouse_id`) · SYSCOHADA · seeders réalistes · workflows métier · non destructif · rollback migration · anti-doublon (réutilise StockService/AccountingService/PurchaseRequestService/DocumentSequenceService).

---

*Proposition de refonte — niveau Odoo Manufacturing / Sage X3 / SAP B1, adaptée fabrication métallique africaine. Socle actuel : 125 tests verts. Roadmap P8→P20 livrable phase par phase sur demande.*
