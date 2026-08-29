# Procédure de déploiement des migrations — obligatoire

[P7.1] Cause racine de l'incident `gross_material_cost` (2026-08-28) : la migration
`2026_08_22_184900_add_material_cost_breakdown_to_production_costs` (commit `e5bd008`) était
committée et correcte, mais n'a **jamais été exécutée** sur `iboa_erp` — dérive entre l'état du
code et l'état réellement déployé de la base. Aucun code n'était en cause.

## Pourquoi les tests Feature ne peuvent pas détecter ce type de dérive

`tests/Concerns/RefreshDatabase.php` lance `migrate:fresh` une fois par process de test
(`RefreshDatabaseState::$migrated`) — la base de test (`iboa_erp_test`) est donc **toujours**
strictement à jour avec les fichiers de migration présents dans le code, par construction. Un
test Pest/PHPUnit contre cette base ne peut structurellement jamais reproduire « une migration
existe mais n'a pas été appliquée » — il n'y a rien à corriger dans la suite de tests, c'est une
propriété du mécanisme de test, documentée ici pour ne pas être redécouverte à chaque incident.
La détection doit se faire **avant/pendant le déploiement**, pas dans la suite de tests.

## Procédure obligatoire avant toute mise à jour de `iboa_erp` (ou de la base pilote/production)

1. **Sauvegarde** complète (DB — voir `docs/BACKUP-CHIFFREMENT-PROCEDURE.md` pour le mécanisme
   chiffré) avant toute action. Vérifier le checksum SHA-256 de l'archive produite.
2. **Vérifier la version Git déployée** : `git log -1 --oneline` doit correspondre exactement à
   ce qui est attendu en production/pilote — jamais déployer un état incertain.
3. **`php artisan migrate:status`** — lister explicitement toute ligne `Pending`. Ne jamais
   supposer que la base est à jour.
4. **Examiner chaque migration `Pending`** avant de l'exécuter : lire son contenu, confirmer
   qu'elle est additive/sûre (pas de `DROP` inattendu, pas de perte de données), et qu'elle
   correspond bien à un changement de code réellement déployé.
5. **`php artisan migrate --force`** — jamais `migrate:fresh` sur une base contenant des
   données réelles.
6. **`php artisan migrate:status`** à nouveau — **aucune ligne ne doit rester `Pending`** après
   cette étape. Si une migration reste en attente de façon inattendue, arrêter et investiguer
   avant de continuer.
7. **Smoke test applicatif** — au minimum, exercer le ou les flux métier que la migration
   concerne (ex. ici : clôturer un OF de test) avant de considérer le déploiement terminé.

## Ce que cette procédure ne remplace pas

Elle est un contrôle **manuel, au moment du déploiement** — pas un test automatisé continu. Un
script read-only qui échoue si `php artisan migrate:status` contient `Pending` peut être ajouté
au pipeline de déploiement (CI ou étape pré-mise en production) pour rendre l'étape 3
non-contournable ; ce n'est pas fait dans cette mission (P7.1) faute de pipeline de déploiement
formalisé pour l'instant (aucun serveur cible n'existe encore — voir
`docs/PILOT-ENVIRONMENT.md`), mais la procédure ci-dessus doit être suivie manuellement à chaque
mise à jour tant qu'un tel script n'existe pas.
