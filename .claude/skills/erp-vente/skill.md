# Skill : erp-vente

Expert module ventes pour l'ERP IBOA.
Intervient sur le cycle commercial complet : devis → commande → bon de livraison
→ facture → paiement → lettrage comptable.

## Contexte projet

- **Modèles clés** :
  `Quote`, `QuoteItem`, `Order`, `OrderItem`,
  `DeliveryNote`, `DeliveryNoteItem`,
  `Invoice`, `InvoiceItem`, `CreditNote`, `CreditNoteItem`,
  `ClientPayment`, `ClientPaymentAllocation`, `ClientPaymentSchedule`,
  `CommercialValidation`, `DocumentSequence`
- **Contrôleurs** : `app/Http/Controllers/Sales/`
- **Services** :
  `app/Services/QuoteService.php`, `app/Services/OrderService.php`,
  `app/Services/DeliveryNoteService.php`, `app/Services/InvoiceService.php`,
  `app/Services/CreditNoteService.php`, `app/Services/CommercialWorkflowService.php`
- **Vues** : `resources/views/sales/`

## Workflow commercial IBOA

```
Devis (brouillon)
  ↓ valider
Devis (validé) ──── converti en ──→ Commande (confirmée)
                                        ↓ préparer
                                    BL (brouillon)
                                        ↓ valider → mouvement stock sortie
                                    BL (validé)
                                        ↓ facturer
                                    Facture (brouillon)
                                        ↓ valider → écriture comptable
                                    Facture (validée)
                                        ↓ encaisser
                                    Paiement → lettrage 411
```

## Statuts par document

### Devis (`quotes.status`)
`brouillon` → `valide` → `converti` | `refuse` | `expire`

### Commandes (`orders.status`)
`confirmee` → `en_preparation` → `livree_partiel` → `livree` | `annulee`

### BL (`delivery_notes.status`)
`brouillon` → `valide` → `facture`

### Factures (`invoices.status`)
`brouillon` → `valide` → `partiellement_payee` → `payee` | `annulee`

### Avoirs (`credit_notes.status`)
`brouillon` → `valide` → `applique`

## Numérotation documents

Via `DocumentSequence` — chaque type a son préfixe et séquence annuelle :

| Type | Préfixe exemple | Table |
|------|-----------------|-------|
| Devis | DEV-2026-0001 | quotes |
| Commande | CMD-2026-0001 | orders |
| Bon de livraison | BL-2026-0001 | delivery_notes |
| Facture | FAC-2026-0001 | invoices |
| Avoir | AV-2026-0001 | credit_notes |

## Calcul des montants (colonnes sur les items)

```
unit_price × quantity = subtotal
subtotal × (1 - discount_percent/100) = subtotal_after_discount
subtotal_after_discount × tax_rate/100 = tax_amount
subtotal_after_discount + tax_amount = total_ttc
```

Sur les entêtes (quotes, invoices…) :
- `subtotal_ht` — somme des sous-totaux HT
- `discount_amount` — remise globale
- `tax_amount` — TVA totale
- `withholding_tax_amount` — retenue à la source (3% ou 5%)
- `total_ttc` — total TTC
- `amount_paid` — encaissé
- `balance_due` — reste à payer

## TVA Burkina Faso — règles

- **TVA normale** : 18% (compte 445)
- **Retenue à la source** : 3% ou 5% selon statut client (entreprise publique)
  → Stockée dans `invoices.withholding_tax_amount`
  → Réduit le net à payer par le client

## Paiements et lettrage

```bash
# Vérifier une facture et ses paiements
php artisan tinker --execute="
\$inv = App\Models\Invoice::with('allocations.payment')->find(ID);
echo 'Total: ' . \$inv->total_ttc . ' | Payé: ' . \$inv->amount_paid . ' | Reste: ' . \$inv->balance_due;
"
```

## Workflow de validation (CommercialValidation)

- Devis > seuil configuré → nécessite validation manager
- Remise > X% → validation requise
- `commercial_validations` table : `document_type`, `document_id`, `status`, `validated_by`

## Intégrations automatiques

| Action | Service appelé | Effet |
|--------|---------------|-------|
| Valider BL | DeliveryNoteService | Mouvement stock `sortie` |
| Valider facture | InvoiceService | Écriture `411/701/445` |
| Encaisser facture | ClientPaymentController | Écriture `521/411` |
| Émettre avoir | CreditNoteService | Contre-écriture |

## Vérifications courantes

```bash
# Factures échues non payées
php artisan tinker --execute="
App\Models\Invoice::where('status','valide')
  ->where('balance_due','>',0)
  ->whereDate('due_date','<',now())
  ->with('client:id,name')
  ->get(['id','number','due_date','balance_due','client_id'])
  ->each(fn(\$i) => echo \$i->number . ' | ' . \$i->client->name . ' | ' . \$i->balance_due . ' XOF\n');
"

# Commandes non livrées depuis plus de 7 jours
php artisan tinker --execute="
App\Models\Order::where('status','confirmee')
  ->where('created_at','<',now()->subDays(7))
  ->count();
"
```

## Règles métier à respecter

1. **Facture validée = immuable** : Pas de modification — émettre un avoir.
2. **Stock vérifié à la livraison** : `available_quantity >= quantité_BL`.
3. **Numérotation séquentielle** : Via `DocumentSequence::nextNumber()` — jamais manuellement.
4. **Devise** : XOF, pas de décimales.
5. **Archivage PDF** : Chaque document validé génère un PDF via DomPDF.

## Fichiers clés

```
app/Http/Controllers/Sales/
app/Services/InvoiceService.php
app/Services/CommercialWorkflowService.php
resources/views/sales/
resources/views/pdf/
database/migrations/2026_05_28_100001_add_validation_workflow_to_sales_tables.php
```
