# Skill : erp-paie-burkina

Expert paie et droit social Burkina Faso pour l'ERP IBOA.
Intervient sur le module RH/Paie : calcul des bulletins, cotisations sociales,
IUTS, états de paiement, clôture mensuelle.

## Contexte projet

- **Pays** : Burkina Faso — législation du travail + convention collective
- **Devise** : XOF (pas de décimales, arrondi à l'entier)
- **Modèles clés** :
  `Employee`, `EmployeeContract`, `PayrollRun`, `PayrollItem`,
  `PayrollSetting`, `PayrollPlan`, `PayRubric`, `PayrollProfile`,
  `IutsBracket`, `SocialContribution`, `PayrollPeriod`,
  `SalaryAdvance`, `EmployeeLoan`, `EmployeeAllowance`
- **Contrôleurs** : `app/Http/Controllers/HR/`
- **Vues** : `resources/views/rh/`
- **Services** : `app/Services/BulletinNumberingService.php`, `app/Services/PayrollPeriodService.php`

## Barèmes légaux Burkina Faso

### CNSS (Caisse Nationale de Sécurité Sociale)

| Branche | Salarié | Employeur | Plafond mensuel |
|---------|---------|-----------|-----------------|
| Prestations familiales (PF) | 0% | 5,75% | Illimité |
| Accidents du travail (AT) | 0% | Variable (1–5%) | Illimité |
| Retraite (RP) | 5,5% | 5,5% | 500 000 XOF |

> Le plafond CNSS retraite est de **500 000 XOF/mois** (vérifier `payroll_settings.cnss_plafond`)

### IUTS (Impôt Unique sur les Traitements et Salaires)

Base imposable = Salaire brut − cotisations salariales − abattement 20%

Barème progressif (tranches mensuelles) stocké dans `iuts_brackets` :

| Tranche (XOF) | Taux |
|---------------|------|
| 0 – 20 000 | 0% |
| 20 001 – 30 000 | 12% |
| 30 001 – 50 000 | 14% |
| 50 001 – 80 000 | 16% |
| 80 001 – 120 000 | 18% |
| 120 001 – 200 000 | 24% |
| > 200 000 | 28% |

> Abattement forfaitaire IUTS : **20%** (configurable dans `payroll_settings.iuts_abattement_rate`)

### Effort de Paix
- Taux salarial : configurable (`payroll_settings.effort_paix_rate_salarie`)
- Taux patronal : configurable (`payroll_settings.effort_paix_rate_patronal`)

### Ancienneté
- Calculée en années complètes depuis `employee_contracts.start_date`
- Taux progressif stocké dans `payroll_settings` (champs `anc_*`)
- Typiquement : +2% par tranche de 5 ans

## Structure d'un bulletin IBOA

```
RUBRIQUE                    GAIN        RETENUE
─────────────────────────────────────────────
Salaire de base             500 000
Heures supplémentaires       20 000
Prime d'ancienneté           10 000
Indemnité transport          15 000
                            ─────────
Salaire brut imposable      530 000

CNSS salarié (5,5%)                      29 150
IUTS                                      XX XXX
Effort de paix salarial                    X XXX
Avances sur salaire                        X XXX
                                         ─────────
NET À PAYER                             XXX XXX
─────────────────────────────────────────────
CHARGES PATRONALES
CNSS patronal PF (5,75%)                 30 475
CNSS patronal AT                          X XXX
CNSS patronal RP (5,5%)                  29 150
Effort de paix patronal                   X XXX
```

## Tables DB et colonnes importantes

### `payroll_settings` — configuration paie par société
- `cnss_plafond` — plafond CNSS retraite (500 000)
- `cnss_salarie_rate` — 5.5%
- `cnss_patronal_pf_rate` — 5.75%
- `cnss_patronal_at_rate` — variable
- `cnss_patronal_rp_rate` — 5.5%
- `iuts_abattement_rate` — 20%
- `effort_paix_rate_salarie`, `effort_paix_rate_patronal`
- `smig` — SMIG mensuel (Burkina : 34 664 XOF)
- `anc_rate_*` — taux ancienneté par tranche

### `payroll_items` — lignes de bulletin
- `rubric_code` — code de la rubrique
- `rubric_label` — libellé affiché
- `is_gain` / `is_deduction` / `is_employer_charge`
- `amount` — montant calculé
- `base_amount` — assiette de calcul
- `cnss_av` / `cnss_pf` / `cnss_rp` — parts CNSS
- `iuts_amount` — montant IUTS
- `net_amount` — net à payer

### `employee_contracts`
- `gross_salary` — salaire brut de base
- `type` — CDI / CDD / Stage / Consultant
- `status` — actif / termine / resilie
- `payroll_profile_id` — profil de paie associé

## Algorithme de calcul d'un bulletin

```
1. Récupérer contrat actif + profil de paie + rubrics actives
2. Calculer salaire brut = base + primes fixes + ancienneté
3. Assiette CNSS = min(brut, plafond_cnss)  [pour retraite]
4. CNSS salarié = assiette × 5,5%
5. Effort de paix salarié = brut × taux
6. Base IUTS = brut - CNSS_salarié - EP_salarié
7. Abattement = base_IUTS × 20%
8. IUTS_imposable = base_IUTS - abattement
9. IUTS = calcul progressif via iuts_brackets
10. Avances = sum(salary_advances non remboursées du mois)
11. Retenues prêts = mensualité du mois (employee_loans)
12. NET = brut - CNSS_salarié - EP_salarié - IUTS - avances - remb_prêts
13. Charges patronales = CNSS_PF + CNSS_AT + CNSS_RP + EP_patronal
```

## Vérifications obligatoires avant clôture

```bash
# Vérifier que tous les employés actifs ont un bulletin
php artisan tinker --execute="
\$run = App\Models\PayrollRun::find(ID);
\$covered = \$run->items->pluck('employee_id')->unique();
\$active  = App\Models\Employee::where('status','actif')->pluck('id');
\$missing = \$active->diff(\$covered);
echo 'Employés sans bulletin: ' . \$missing->count();
"
```

## Fichiers clés

```
app/Http/Controllers/HR/PayrollRunController.php
app/Http/Controllers/HR/PayrollBulletinTemplateController.php
app/Services/BulletinNumberingService.php
app/Services/PayrollPeriodService.php
resources/views/rh/bulletins/
resources/views/rh/paie/
```

## Règles de réponse

- Toujours arrondir en XOF entier (pas de centimes).
- Toujours vérifier le plafond CNSS avant d'appliquer le taux.
- Mentionner la référence légale (Code du Travail BF, Décret CNSS, etc.) quand pertinent.
- Proposer le journal comptable SYSCOHADA pour chaque écriture de paie.
