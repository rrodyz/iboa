# Refonte module VENTE — connecté à la PRODUCTION
### A3 ERP Laravel · cycle Commande → Production → Livraison → Facturation → Comptabilisation
### Fabrication métallique · SYSCOHADA · multi-société / multi-dépôt

> **Méthode** : refonte **incrémentale**, non destructive, sur l'existant. Ne pas casser, éviter doublons.
> Compléments : `docs/production-module.md`, `docs/production-blueprint.md`, `docs/production-refonte.md`.
> Légende : ✅ livré · 🔶 partiel · ⬜ à construire.

---

## 1. Analyse du module Vente existant

**Tables réelles** (≠ noms spec) : `quotes`/`quote_items` · `orders`/`order_items` · `delivery_notes`/`delivery_note_items` · `invoices`/`invoice_items` · `clients` (= customers).

**Statuts commande actuels** (8) : brouillon · en_attente_validation · confirme · en_preparation · partiellement_livre · livre · facture · annule.

**Déjà en place** ✅ :
- Workflow Devis→Commande→BL→Facture (services OrderService, DeliveryNoteService, InvoiceService).
- Workflow validation interne (submit/validate/reject/cancel).
- **TVA exonération client** (`clients.is_tax_exempt`, `tax_regime`, `tax_exemption_reason/number`) — testé (5 scénarios).
- Facture → écriture SYSCOHADA (DR 411 / CR 7011 / CR 4431) + retenue source 4473.
- Échéance + encaissements + relances + balance âgée (Trésorerie).
- **Connecteurs Production déjà construits cette session** :
  - Commande → OF (bouton « Lancer en production », OF pré-rempli) ✅
  - OF → commande (`production_orders.order_id`) ✅
  - Réservation PF (`stock_reservations`, bump `reserved_quantity`) ✅
  - Chaîne 10 étapes visible sur l'OF (`ProductionWorkflowService`) ✅
  - MRP réappro · réception bobine · prévision trésorerie · pointage MO ✅

**Manquant côté COMMANDE** (sens inverse Vente→Prod) :
- Statut de production visible **sur la commande** (cockpit) ⬜
- Boutons production sur la commande (vérifier stock, réserver, créer OF, voir OF/QC/PF) 🔶 (seul « Lancer en production » existe)
- Statuts commande production-aware (16) ⬜
- Blocage livraison si PF indispo / QC non conforme / qté insuffisante 🔶

---

## 2. Nouveaux statuts commande (spec §2)

Cible 16 statuts. Mapping sur l'existant + ajouts :
| Statut spec | Existant | Action |
|---|---|---|
| Brouillon, Devis envoyé/validé | quotes (statuts devis) | ✅ |
| Commande confirmée | `confirme` | ✅ |
| Stock vérifié, En attente matières, Matières réservées | — | ⬜ champ `production_status` |
| OF créé, En production, En contrôle qualité, Produit fini disponible | — | ⬜ dérivé de l'OF lié |
| Partiellement livré, Livré, Facturé | `partiellement_livre`,`livre`,`facture` | ✅ |
| Comptabilisé | — | 🔶 (dérivé : facture postée) |
| Annulé | `annule` | ✅ |

**Recommandation** : NE PAS gonfler l'enum `orders.status` (casse le workflow ventes). Ajouter une colonne **`production_status`** (nullable) dérivée de l'OF lié, affichée en parallèle. Le statut commercial reste inchangé.

---

## 3-5. Liens Vente → Production / Stock / Facturation

### Vente → Production (§3)
Par ligne commande : vérifier stock PF → si suffisant **réserver**, sinon **créer OF** (reprend client/produit/qté/dimensions/couleur/épaisseur/longueur/délai). Statut production affiché.
- OF reprend infos commande ✅ (prefill déjà fait).
- Vérif stock + décision réserver/produire ⬜ → `SalesProductionService`.

### Vente → Stock (§4)
Afficher par ligne : disponible · réservé · à produire · livré · restant. Bloquer livraison si PF indispo / QC non conforme / qté produite < commandée.
- `reserved_quantity` ✅ · available ✅ · blocages ⬜ (à câbler dans DeliveryNoteService::validate).

### Vente → Facturation (§5)
Facture seulement après BL validé + qté livrée validée. Reprend client/commande/BL/produits/qtés/PU/remises/TVA/HT/TVA/TTC/payé/reste.
- BL→Facture ✅ (`InvoiceService::createFromDeliveryNote`) · garde « BL validé » ✅.

---

## 6. Gestion TVA (spec §6) — ✅ DÉJÀ CONFORME
- TVA **pas par défaut** ✅ · client exonéré → TVA 0 ✅ (`is_tax_exempt`) · sinon taux configuré ✅ · modifiable ✅.
- Statut fiscal client affiché : 🔶 (sur facture oui ; à ajouter sur devis/commande).

---

## 7-8. Vente → Comptabilité / Trésorerie — ✅ EN PLACE
- Facture validée → écriture SYSCOHADA (DR 411 / CR 7011 / CR 4431) ✅ · grand livre · balance auxiliaire · balance âgée ✅.
- Échéance · encaissements · paiements partiels · relances · reste à payer · dashboard tréso ✅.

---

## 9-10. Écrans & boutons commande (spec §9-10)

**Cockpit production sur la fiche commande** (⬜ principal manquant) — boutons :
| Bouton | État |
|---|---|
| Vérifier stock | ⬜ |
| Réserver produit fini | ⬜ |
| Créer ordre de fabrication | ✅ (« Lancer en production ») |
| Voir OF · Voir QC · Voir stock PF | ⬜ (liens vers OF lié) |
| Créer bon de livraison | ✅ (Ventes) |
| Créer facture | ✅ (Ventes) |
| Voir écriture comptable · Voir paiement | 🔶 (existe ailleurs, à lier) |

+ panneau « Suivi production » = chaîne 10 étapes (réutiliser `ProductionWorkflowService`, déjà construit) affichée **sur la commande**.

---

## 11. Tables — mapping spec → réel

| Spec | Réel A3 | Action |
|---|---|---|
| sales_quotes / sales_orders / *_lines | quotes / orders / *_items | ✅ |
| deliveries / delivery_lines | delivery_notes / delivery_note_items | ✅ |
| invoices / invoice_lines | invoices / invoice_items | ✅ |
| customers | clients | ✅ |
| stock_movements | stock_movements | ✅ |
| production_orders / quality_controls | production_orders / production_quality_controls | ✅ |
| accounting_entries | journal_entries | ✅ |
| treasury_payments | client_payments | ✅ |
| **sales_production_links** | `production_orders.order_id` | ✅ (déjà lien) |
| **sales_stock_reservations** | `stock_reservations` | ✅ (déjà construit) |
| **customer_tax_profiles** | `clients.is_tax_exempt/tax_regime/...` | ✅ (colonnes existantes) |
| **sales_delivery_tracking / invoice_tracking** | statuts BL/facture + `delivered_quantity` | 🔶 |
| **sales_workflow_logs** | audit trail (HasCreator) | ⬜ table dédiée si traçabilité fine voulue |

→ **À ajouter** (additif, rollback-safe) : `orders.production_status` (string nullable) · éventuellement `sales_workflow_logs`.

---

## 12. Règles de gestion (spec §12)
| Règle | État |
|---|---|
| 1 commande → 1..n OF | ✅ (hasMany order→productionOrders) |
| OF lié à commande | ✅ |
| Pas de livraison sans stock dispo | ⬜ (garde à câbler) |
| Pas de livraison sans QC validé | ⬜ |
| Pas de facture sans BL validé | ✅ |
| Facture validée → écriture compta | ✅ |
| Facture payée → MAJ trésorerie | ✅ |
| Commande annulée → libère réservations | ⬜ (release auto) |
| Production annulée → libère matières réservées | 🔶 |

---

## 13. KPI Vente production-aware (spec §13)
Existants ✅ : CA HT, TVA collectée, factures impayées, devis/commandes.
À ajouter ⬜ : commandes en production · prêtes à livrer · livrées non facturées · marge/commande · retards production/livraison · taux transfo devis→commande.

---

## 14. Permissions (spec §14)
Existantes ✅ : quotes.* · orders.* · invoices.* · deliveries.* · sales.* (workflow).
À ajouter ⬜ : `sales.stock.check`, `sales.stock.reserve`, `sales.production.create`, `sales.margin.view`.

---

## 15. Plan d'implémentation (incrémental)

| Phase | Livrable | Effort | Valeur |
|---|---|---|---|
| **V1** | `orders.production_status` + cockpit production sur fiche commande (boutons Voir OF/QC/PF + chaîne 10 étapes réutilisée) | M | ★★★ |
| **V2** | `SalesProductionService` : vérif stock PF par ligne → réserver ou créer OF (décision auto/manuel) | M | ★★★ |
| **V3** | Blocages livraison (stock dispo + QC conforme + qté ≥ commandée) dans `DeliveryNoteService::validate` | S | ★★★ |
| **V4** | Libération auto réservations à l'annulation commande/OF | S | ★★ |
| **V5** | Statut fiscal client affiché sur devis + commande (TVA déjà OK) | S | ★ |
| **V6** | KPI Vente production-aware (en production, prêtes à livrer, retards, taux transfo) | M | ★★ |
| **V7** | `sales_workflow_logs` + traçabilité fine + permissions sales.production.* | M | ★ |

**Chaque phase** : migration additive (rollback `down()`) + service + controller + vues + permissions + Feature tests + `composer dump-autoload` / `route:list` / `migrate:status`. Multi-société/dépôt + SYSCOHADA. Réutilise `ProductionWorkflowService`, `ReservationService`, `StockService`, `InvoiceService` — zéro doublon.

---

## Synthèse
Le module Vente est **déjà largement connecté** (TVA exonération ✅, BL/Facture ✅, compta ✅, trésorerie ✅, OF depuis commande ✅, réservation ✅). Le **delta réel** = exposer la production **sur la fiche commande** (cockpit V1), automatiser vérif-stock→OF/réservation (V2), et durcir les blocages livraison (V3). Le reste (TVA, compta, tréso) est conforme.

**Recommandation** : démarrer par **V1 (cockpit commande)** — plus haute valeur, réutilise tout l'existant. Repère : socle actuel 141 tests verts.
