# Journal des anomalies — pilote A3 ERP

Aucun incident ne doit être supprimé de ce journal. Statuts possibles : `ouvert`, `en cours`,
`corrigé`, `non reproductible`, `hors périmètre pilote`.

| ID | Date | Utilisateur | Module | Action | Résultat attendu | Résultat obtenu | Criticité | Preuve | Statut |
|----|------|-------------|--------|--------|-------------------|------------------|-----------|--------|--------|
| P6-001 | 2026-08-28 | (session P4/P5/P6) | PDF Ventes | Générer une facture PDF | Numéro de page total correct (« Page 1 / N ») | « Page 1 / 0 » sur tous les documents multipages (7 templates) | P1 bloquante | Reproduit isolément (dompdf CSS `counter(pages)`) + sur facture réelle | corrigé (commit `59e2eb6`) |
| P6-002 | 2026-08-28 | (session P6) | PDF Ventes — Facture | Afficher le total « NET À PAYER » sur une facture validée | Libellé et montant lisibles sur fond bleu | Libellé invisible (fond clair de cellule masquant le texte blanc) | P2 importante | Reproduit isolément + sur facture réelle | corrigé (commit `86febf6`) |
| P6-003 | 2026-08-28 | (audit P5→P6) | Infrastructure / Scheduler | Vérifier le déclenchement automatique de `php artisan schedule:run` | Tâche planifiée OS présente et fonctionnelle | Aucune tâche Windows Task Scheduler configurée sur la machine de dev ; aucun serveur cible encore provisionné | P1 bloquante (déploiement) | `Get-ScheduledTask` — aucune entrée | ouvert — PENDING DEPLOYMENT, non actionnable sans serveur réel |
| P6-004 | 2026-08-28 | (audit référentiel) | Référentiel | Vérifier la présence de tiers réels avant pilote | Quelques clients/fournisseurs réels renseignés | 1 client, 1 fournisseur seulement (état post-reset transactionnel) | P2 importante | Requête lecture seule `iboa_erp` | ouvert — à faire par le métier avant extension du pilote |
| P6-005 | 2026-08-28 | (audit rôles) | Sécurité / Rôles | Vérifier qu'un utilisateur réel est assigné à chaque rôle métier nécessaire | Rôle `chef_atelier` assigné à au moins un utilisateur | 0 utilisateur assigné à `chef_atelier` | P2 importante | Requête lecture seule `iboa_erp` | ouvert — à faire avant ouverture du pilote si le workflow chef d'atelier est utilisé |

---
**Note de méthode** : les IDs P6-001/P6-002 documentent des anomalies trouvées et corrigées
pendant la préparation P6 elle-même (avant tout utilisateur pilote réel) — conservées ici pour
traçabilité complète, conformément à la consigne de ne rien supprimer du journal. Les entrées
futures, déposées par de véritables utilisateurs pilotes, viendront s'ajouter à la suite sans
jamais réécrire l'historique.
