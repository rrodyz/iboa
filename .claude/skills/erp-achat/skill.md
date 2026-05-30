# Skill : erp-achat

Expert module achats pour l'ERP IBOA.
Intervient sur le cycle achat complet : demande d'achat → appel d'offres →
bon de commande → réception → facture fournisseur → paiement.

## Contexte projet

- **Modèles clés** :
  `PurchaseRequest`, `PurchaseRequestItem`,
  `Rfq`, `RfqItem`, `RfqSupplier`, `RfqQuote`, `RfqQuoteItem`,
  `PurchaseOrder`, `PurchaseOrderItem`,
  `Reception`, `ReceptionItem`,
  `SupplierInvoice`, `SupplierInvoiceItem`,
  `SupplierPayment`, `SupplierPaymentAllocation`,
  `SupplierReturn`, `SupplierReturnItem`,
  `PoApprovalThreshold`
- **Contrôleurs** : `app/Http/Controllers/Purchases/`
- **Services** :
  `app/Services/PurchaseOrderService.php`,
  `app/Services/PurchaseRequestService.php`,
  `app/Services/RfqService.php`,
  `app/Services/SupplierInvoiceService.php`,
  `app/Services/SupplierReturnService.php`
- **Vues** : `resources/views/achats/`

## Workflow achat IBOA

```
Demande d'achat (DA)
  ↓ approuver
DA approuvée ──→ Appel d'offres (RFQ) ──→ Réponses fournisseurs
                                              ↓ sélectionner fournisseur
                                          Bon de commande (BC)
                                              ↓ envoyer / approuver (si > seuil)
                                          BC approuvé
                                              ↓ recevoir marchandises
                                          Réception
                                              ↓ valider → mouvement stock entree
                                          Réception validée
                                              ↓ facturer
                                          Facture fournisseur
                                              ↓ valider → écriture comptable
                                          Facture validée
                                              ↓ payer
                                          Paiement → lettrage 401
```

## Statuts par document

### Demande d'achat (`purchase_requests.status`)
`brouillon` → `soumise` → `approuvee` | `rejetee`

### Bon de commande (`purchase_orders.status`)
`brouillon` → `soumis` → `approuve` → `envoye` → `en_cours` → `livre_partiel` → `livre` | `annule`

### Réception (`receptions.status`)
`en_attente` → `partielle` → `complete`

### Facture fournisseur (`supplier_invoices.status`)
`brouillon` → `validee` → `partiellement_payee` → `payee` | `annulee`

## Seuils d'approbation BC

Table `po_approval_thresholds` — définit qui approuve selon le montant :

| Montant | Approbateur |
|---------|-------------|
| < 500 000 XOF | Manager achat |
| 500 000 – 2 000 000 XOF | Directeur |
| > 2 000 000 XOF | DG |

Workflow d'approbation : `app/Http/Controllers/Purchases/PoApprovalController.php`

## Calcul des montants

```
quantity × unit_price = subtotal
subtotal × (1 - discount_percent/100) = subtotal_net
subtotal_net × tax_rate/100 = tax_amount
subtotal_net + tax_amount = total_ttc
```

## Réception et contrôle qualité

- `reception_items.quantity_ordered` vs `quantity_received` vs `quantity_accepted`
- Quantité refusée → déclencher retour fournisseur
- Réception partielle → BC reste `livre_partiel`
- Réception complète → BC passe à `livre`

```bash
# BCs avec réceptions partielles depuis > 30 jours
php artisan tinker --execute="
App\Models\PurchaseOrder::where('status','livre_partiel')
  ->where('updated_at','<',now()->subDays(30))
  ->with('supplier:id,name')
  ->get(['id','reference','updated_at','supplier_id'])
  ->each(fn(\$po) => echo \$po->reference . ' | ' . \$po->supplier->name . PHP_EOL);
"
```

## Appels d'offres (RFQ)

- `Rfq` → plusieurs `RfqSupplier` (fournisseurs invités)
- Chaque fournisseur répond → `RfqQuote` avec ses `RfqQuoteItem`
- Comparaison des offres → sélection → génération BC
- `rfq_quotes.is_selected = true` sur l'offre retenue

## Intégrations automatiques

| Action | Service appelé | Effet |
|--------|---------------|-------|
| Valider réception | PurchaseOrderService | Mouvement stock `entree` |
| Valider facture fournisseur | SupplierInvoiceService | Écriture `601/401/4452` |
| Payer fournisseur | SupplierPaymentController | Écriture `401/521` |
| Valider retour | SupplierReturnService | Mouvement stock `sortie` + avoir fournisseur |

## Écriture comptable SYSCOHADA

| Action | Débit | Crédit |
|--------|-------|--------|
| Réception (entrée stock) | 311 Stocks | 401 Fournisseurs |
| Facture fournisseur | 601 Achats + 4452 TVA déd. | 401 Fournisseurs |
| Paiement fournisseur | 401 Fournisseurs | 521 Banque |
| Retour fournisseur | 401 Fournisseurs | 311 Stocks |

## Vérifications courantes

```bash
# Factures fournisseur non rapprochées (sans BC)
php artisan tinker --execute="
App\Models\SupplierInvoice::whereNull('purchase_order_id')
  ->where('status','validee')
  ->sum('total_ttc');
"

# Écart réception vs facture (3-way matching)
php artisan tinker --execute="
App\Models\SupplierInvoice::with(['purchaseOrder.items','items'])
  ->where('status','validee')
  ->get()
  ->filter(fn(\$inv) =>
    \$inv->purchaseOrder &&
    abs(\$inv->total_ttc - \$inv->purchaseOrder->total_ttc) > 1000
  )
  ->pluck('reference');
"
```

## Règles métier à respecter

1. **3-way matching** : Bon de commande + Réception + Facture avant paiement.
2. **Facture sans BC** : Signaler et demander justification.
3. **Doublons fournisseur** : Vérifier unicité `suppliers.ifu` avant création.
4. **Retour avant paiement** : Créer avoir fournisseur avant de déduire du paiement.

## Fichiers clés

```
app/Http/Controllers/Purchases/
app/Services/PurchaseOrderService.php
app/Services/RfqService.php
app/Services/SupplierInvoiceService.php
resources/views/achats/
```
