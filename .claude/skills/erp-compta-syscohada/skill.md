# Skill : erp-compta-syscohada

Expert comptabilité SYSCOHADA Révisé pour l'ERP IBOA (Burkina Faso).
Intervient sur le module `Accounting` : plan comptable, journaux, grand livre,
balance, états financiers, clôture d'exercice, déclarations fiscales.

## Contexte projet

- **Plan comptable** : SYSCOHADA Révisé (2017) — 8 classes
- **Devise** : XOF (Franc CFA UEMOA) — pas de décimales
- **Société** : OA METAL INDUSTRIE (id=19, Ouagadougou, Burkina Faso)
- **Modèles clés** : `Account`, `AccountClass`, `JournalEntry`, `JournalEntryLine`,
  `JournalType`, `FiscalYear`, `AccountingPeriodLock`, `VatDeclaration`
- **Contrôleurs** : `app/Http/Controllers/Accounting/`
- **Vues** : `resources/views/accounting/`
- **Helper** : `currentCompany()` pour scoper les écritures par société

## Plan comptable SYSCOHADA — classes

| Classe | Libellé |
|--------|---------|
| 1 | Comptes de capitaux |
| 2 | Comptes d'actif immobilisé |
| 3 | Comptes de stocks |
| 4 | Comptes de tiers |
| 5 | Comptes de trésorerie |
| 6 | Comptes de charges |
| 7 | Comptes de produits |
| 8 | Comptes des autres charges et autres produits |

## Comptes SYSCOHADA critiques IBOA

| Compte | Libellé | Usage |
|--------|---------|-------|
| 101 | Capital social | Bilan passif |
| 411 | Clients | AR clients |
| 401 | Fournisseurs | AR fournisseurs |
| 521 | Banque | Trésorerie |
| 571 | Caisse | Espèces |
| 601 | Achats de marchandises | Charges |
| 701 | Ventes de marchandises | Produits |
| 445 | TVA collectée (18%) | Déclaration |
| 4452 | TVA déductible | Déclaration |
| 585 | Virement de fonds | Interne |

## Règles comptables à respecter

1. **Équilibre débit/crédit** : Toute écriture doit balancer à 0 (sum(debit) = sum(credit)).
2. **Exercice ouvert** : Interdire toute écriture sur exercice verrouillé (`AccountingPeriodLock`).
3. **Letttrage** : Les écritures clients/fournisseurs doivent pouvoir être lettrées.
4. **Contre-passation** : Utiliser `reversed_by_id` — jamais supprimer une écriture validée.
5. **A-Nouveaux** : Compte `AN` créé automatiquement à la clôture via `FiscalYearController`.
6. **Numérotation** : Via `DocumentSequence` type `journal_entry`.

## Journaux types

| Code | Libellé | Utilisé par |
|------|---------|-------------|
| VT | Ventes | Factures clients |
| AC | Achats | Factures fournisseurs |
| BQ | Banque | Paiements |
| CA | Caisse | Espèces |
| OD | Opérations diverses | Paie, stocks, ajustements |
| AN | À-nouveaux | Clôture |

## Axes d'intervention

### 1. Vérification d'une écriture
```bash
php artisan tinker --execute="
\$je = App\Models\JournalEntry::with('lines')->find(ID);
echo 'Balance: ' . (\$je->lines->sum('debit') - \$je->lines->sum('credit'));
"
```

### 2. Balance de vérification
- Comparer `accounts.balance` avec la somme réelle des lignes d'écriture.
- Détecter les comptes hors balance.

### 3. États financiers SYSCOHADA
- **Bilan** : Actif (1+2+3+4+5) vs Passif (1+4+5) — format SYSCOHADA 2 colonnes.
- **Compte de résultat** : Produits (7+8) - Charges (6+8).
- **Tableau de flux** : Exploitation / Investissement / Financement.

### 4. TVA Burkina Faso
- Taux normal : **18%**
- Retenue à la source : selon type de client (entreprise publique = 3% ou 5%)
- Déclaration mensuelle via `VatDeclaration`

### 5. Intégration automatique
- Facture validée → écriture `411 / 701 / 445`
- Paiement reçu → écriture `521 / 411`
- Salaires → écriture `661 / 421 / 431`
- Stock → écriture `601 / 311`

## Format de réponse attendu

Toujours indiquer :
- Le numéro de compte SYSCOHADA concerné
- Le journal utilisé
- L'impact sur le bilan/résultat
- La règle SYSCOHADA appliquée

## Fichiers clés à lire en priorité

```
app/Http/Controllers/Accounting/
app/Services/InvoiceService.php         (génération écritures ventes)
app/Services/SupplierInvoiceService.php (génération écritures achats)
app/Services/PayrollPeriodService.php   (génération écritures paie)
resources/views/accounting/
database/migrations/2026_04_18_200005_create_accounting_tables.php
```
