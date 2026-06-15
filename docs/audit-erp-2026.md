# Audit ERP A3 — production-centré · niveau Odoo Enterprise / Sage X3 / SAP B1
### Fabrication métallique (tôles bac, charpentes, hangars, portails, clôtures) · SYSCOHADA · Afrique de l'Ouest

> Méthode : audit du **code réel** (185 migrations, 146 modèles, 68 services, ~93 controllers, 11 domaines).
> Légende : ✅ complet · 🔶 partiel · ⬜ absent.
> Complète : `production-module.md`, `production-blueprint.md`, `production-refonte.md`, `vente-refonte.md`.

---

## PHASE 1 — Audit module par module

| Module | Controllers | État | Détail |
|--------|:-:|:-:|--------|
| **Production** (`app/Modules/Production`) | 15 | 🔶 | 25 tables · OF/workflow/BOM/bobines/conso/QC/coût/MRP/réservation/MO/chaîne ✅ ; gammes/centres/planif/maintenance ⬜ |
| **Ventes** | 8 | ✅ | Devis→Commande→BL→Facture→Avoir · TVA exonération · workflow validation · **cockpit production V1-V6** ✅ |
| **Achats** | 9 | ✅ | DA→PO→Réception→Facture fourn. · **réception→bobine** ✅ · QC réception ⬜ |
| **Stock** | 6 | ✅ | MP/PF · mouvements auto · inventaire · CMP/FIFO/LIFO · lots/seuils · semi-finis ⬜ |
| **Comptabilité** | 13 | ✅ | Plan SYSCOHADA · journaux · GL · balance · **lettrage · rapprochement · immobilisations · effets · TVA** ✅ |
| **Trésorerie** | 16 | ✅ | Encaissements/décaissements · caisses/banques · virements · prévisions · relances ✅ |
| **RH & Paie** | 22 | ✅ | Employés · contrats · présences · congés · **paie (28 migrations)** · compta paie ✅ |
| **CRM** | 4 | 🔶 | Contacts/leads · opportunités · activités · pipeline ✅ ; scoring/emailing ⬜ |
| **Intégrations** | — | 🔶 | API DGI/TVA · déclarations 🔶 |
| **Maintenance** | 0 | ⬜ | **module absent** |
| **Qualité (transverse)** | 0 | 🔶 | QC seulement dans production ; module qualité standalone (réception/NC/CAPA) ⬜ |

**Verdict global** : ERP **mature** (compta/tréso/RH/ventes/achats/stock complets, niveau Sage). Le retard est **uniquement sur le MES production** (gammes, centres, planification, maintenance) et la qualité transverse.

---

## PHASE 2 — Anomalies détectées

| # | Anomalie | Sévérité | État |
|---|----------|:-:|:-:|
| A1 | XSS référence produit dans PDF facture (`{!! !!}`) | Moyen | ✅ corrigé (session) |
| A2 | Incohérence donnée : OF 105 épaisseur 5 mm vs produit 0.4 mm | Donnée | ⚠ saisie |
| A3 | TVA 0 % sur client assujetti (taux non saisi sur ligne) | Donnée | ⚠ règle « TVA non auto » → saisir taux |
| A4 | `production_status` commande non persisté (dérivé dynamique) | Mineur | 🔶 choix design (OK) |
| A5 | ~~Doublon `Accounting/` vs `Comptabilite/`~~ — **infirmé** : controllers seulement dans `Accounting/` (13), `Comptabilite/` = vues only | — | ✅ pas de doublon |
| A6 | `production_stock_moves` envisagée mais **non créée** (réutilise `stock_movements`) | — | ✅ anti-doublon respecté |
| A7 | Pas de table maintenance → indispo machine non tracée | Important | ⬜ |

**Doublons confirmés évités** : bobine = matière (pas de double-stock), PF via StockService, réservation via `stock_reservations`, demandes d'achat via `PurchaseRequestService`, compta via `AccountingService`. Aucune logique métier dupliquée.

---

## PHASE 3 — Fonctionnalités manquantes

### Production (MES) — priorité haute
- ⬜ **Gammes opératoires** (routings) + **Work Orders** (opérations par OF)
- ⬜ **Centres de travail** (capacité, calendrier, coût horaire)
- ⬜ **Planification / plan de charge** (Gantt, ordonnancement)
- ⬜ **Produits semi-finis** (BOM multi-niveaux) + **lots/batch** traçabilité
- ⬜ **Coût standard** + analyse d'**écarts** (matière/MO/machine)
- ⬜ **Optimisation de découpe** (nesting longueurs tôle)

### Qualité (transverse)
- ⬜ Contrôle **réception** (matière entrante) · **non-conformités** · **actions correctives (CAPA)**

### Maintenance
- ⬜ Plans **préventifs** · interventions **correctives** · **disponibilité machine (OEE/MTBF/MTTR)**

### CRM
- ⬜ Scoring leads · campagnes emailing · devis depuis opportunité

### Transverse
- ⬜ **Réservation matière** (actuellement réservation PF seulement)
- ⬜ Recherche globale enrichie · raccourcis clavier (recherche existe déjà)

---

## PHASE 4 — Nouvelle architecture (production = hub)

```
        CRM → DEVIS → COMMANDE ──► [vérif stock] ──► OF (cœur)
                                         │
   ACHATS ◄──(MRP déficit)── OF ──► réservation MP/PF
   réception → bobine            │
                                 ▼
   STOCK ◄─ sortie MP / entrée PF ─ PRODUCTION → QC → libération PF
                                 │                      │
   RH ◄─ pointage temps → coût MO │              [garde livraison]
                                 ▼                      ▼
   COMPTA ◄─ écritures SYSCOHADA ─ clôture OF      BL → FACTURE → PAIEMENT
                                 │                              │
   TRÉSORERIE ◄─ prévision coûts / besoin financement ◄────────┘
   IMMOBILISATIONS ◄─ amortissement machine → coût machine
```
**Couplage** : 1 service connecteur par lien (réutilise services natifs) → couplage faible, zéro doublon.
**Tables nouvelles requises** : `work_centers, routings, routing_operations, production_order_operations, production_planning, production_time_logs✅, stock_reservations✅, production_batches, machine_maintenance, semi_finished(flag), quality_inspections, non_conformities`.
(`production_time_logs` + `stock_reservations` déjà livrés cette session.)

---

## PHASE 5 — Plan de développement détaillé (incrémental, non destructif)

| Lot | Livrable | Tables | Effort | Tests |
|-----|----------|--------|:-:|:-:|
| P10 | Centres de travail + capacité | work_centers | M | Feature |
| P11 | Gammes + Work Orders + avancement % OF | routings, routing_operations, production_order_operations | L | Feature |
| P12 | ~~Pointage temps → coût MO réel~~ | production_time_logs | — | ✅ **livré** |
| P13 | Planification / plan de charge (Gantt) | production_planning | L | Feature |
| P14 | Coût standard + analyse écarts | (colonnes production_costs) | M | Unit |
| P15 | Semi-finis (BOM multi-niveaux) + batch | production_batches + flag | M | Feature |
| P16 | Maintenance préventive/corrective + OEE | machine_maintenance | M | Feature |
| Q1 | Qualité : contrôle réception + NC + CAPA | quality_inspections, non_conformities | M | Feature |
| P17 | Réservation matière (en plus du PF) | (stock_reservations étendu) | S | Feature |
| P18 | Optimisation découpe (nesting) | — | L | Unit |
| UX1 | Dashboards rôle (Direction/Prod/Commercial/Comptable) | — | M | smoke |

Chaque lot : migration (rollback `down()`) + modèle + service + controller + **Policy** + vues responsive + permissions + Feature tests + `dump-autoload`/`route:list`/`migrate:status`. Multi-société/dépôt + SYSCOHADA.

---

## PHASE 6 — Roadmap mise à niveau Odoo Enterprise / Sage X3

| Trimestre | Objectif | Lots |
|-----------|----------|------|
| **T1** | MES socle : centres + gammes + Work Orders | P10, P11 |
| **T2** | Planification + coût standard/écarts | P13, P14 |
| **T3** | Maintenance + Qualité transverse + semi-finis | P16, Q1, P15 |
| **T4** | Réservation matière + nesting + dashboards rôle | P17, P18, UX1 |

Cible atteinte : OF avec opérations/centres planifiés, coût standard vs réel, maintenance OEE, qualité bout-en-bout, multi-niveaux BOM → **parité fonctionnelle MES** avec Odoo MRP / Sage X3 Production.

---

## PHASE 7 — Priorisation

### 🔴 Critique (bloque le pilotage industriel)
- P11 Gammes + Work Orders (suivi atelier réel)
- P10 Centres de travail (prérequis planif/coût)
- Q1 Qualité réception + non-conformités
- P14 Coût standard + écarts (pilotage marge)

### 🟠 Important
- P13 Planification / plan de charge
- P16 Maintenance + disponibilité machine
- P15 Semi-finis (charpentes/hangars multi-niveaux)
- P17 Réservation matière
- UX1 Dashboards par rôle

### 🟢 Optionnel
- P18 Optimisation de découpe (nesting)
- CRM scoring/emailing
- Dark mode avancé / raccourcis clavier

---

## Analyse technique (synthèse)
- **Sécurité** ✅ : autorisation fine (Spatie), `validate()` partout (zéro mass-assign), `HasCompanyScope` multi-tenant, CSRF, XSS corrigé. (audit dédié réalisé)
- **Perf** 🔶 : eager-loading présent (relations `with()`), pagination serveur ; à surveiller — quelques agrégats en mémoire déjà optimisés (index `metres`). Pas de Redis/queues sur la prod (jobs synchrones). Index FK posés sur les 25 tables production.
- **Policies** 🔶 : gating par middleware permission (suffisant) ; Policies dédiées recommandées pour Production (lot P10+).
- **Tests** ✅ : **159 verts** (458 assertions) sur la couche production + connecteurs.
- **Code mort / tables inutiles** : aucun détecté dans le périmètre production ; audit global `php artisan model:prune`/migrations orphelines à planifier hors-scope.

---

## Synthèse exécutive
L'ERP A3 est **déjà au niveau Sage** sur Compta / Trésorerie / RH-Paie / Ventes / Achats / Stock. Le **seul écart** vers Odoo Enterprise / Sage X3 / SAP B1 est le **MES production avancé** (gammes, centres, planification, maintenance, qualité transverse, semi-finis). La fondation production-centrée est **posée et testée** (OF, coûts, 6 modules connectés, refonte Vente). Roadmap T1→T4 ci-dessus pour la parité MES complète.

**Recommandation immédiate** : démarrer **P10 (centres) → P11 (gammes/Work Orders)** = plus haute valeur industrielle. Socle : 159 tests verts.
