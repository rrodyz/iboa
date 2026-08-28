# Chiffrement des sauvegardes — procédure

[P5 — Phase 2] Lève la réserve P4 : `BACKUP_ARCHIVE_PASSWORD` absent en configuration.

## Mécanisme

Le chiffrement n'est **pas** une fonctionnalité à développer : elle existe déjà dans
`config/backup.php` (spatie/laravel-backup) :

```php
'password' => env('BACKUP_ARCHIVE_PASSWORD'),
'encryption' => 'default', // ZipArchive::EM_AES_256 si le système le supporte
```

Quand `BACKUP_ARCHIVE_PASSWORD` est absent, `password` vaut `null` et spatie/laravel-backup
produit une archive **non chiffrée** — comportement silencieux, aucune erreur. C'était l'état
constaté en P4.

Renseigner la variable d'environnement suffit à activer le chiffrement AES-256 réel — **aucune
modification de code n'est nécessaire**. Vérifié en P5 : une archive produite avec la variable
définie refuse un mauvais mot de passe et accepte le bon (`ZipArchive::setPassword` +
`getFromIndex`/`extractTo`), preuve testée sur `db-dumps/mysql-iboa_erp_test.sql` (627 681 octets
déchiffrés avec succès) et sur l'archive complète (26 entrées, DB + fichiers).

Note outillage : l'utilitaire `unzip` (Info-Zip) ne sait pas lire une archive AES-256
(`PK compat. v5.1`, message "unsupported compression or encoding") — c'est attendu, pas un défaut
de l'archive. Utiliser `ZipArchive` (PHP) ou un outil compatible AES (7-Zip récent) pour
vérifier/extraire une sauvegarde chiffrée.

## Génération du secret

```bash
openssl rand -base64 32
```

32 octets aléatoires encodés en base64 (44 caractères). Ne jamais réutiliser un mot de passe
applicatif existant.

## Stockage du secret

- **Jamais dans le dépôt Git.** `.env.example` porte uniquement une ligne commentée
  (`# BACKUP_ARCHIVE_PASSWORD=`) à titre de documentation — sans valeur.
- **En production** : définir `BACKUP_ARCHIVE_PASSWORD` dans le `.env` du serveur de production
  uniquement (permissions fichier restreintes, `chmod 600`, propriétaire = utilisateur applicatif),
  ou via le gestionnaire de secrets de l'hébergeur s'il en existe un (variable d'environnement du
  process manager, vault, etc.) plutôt qu'en clair dans un fichier si l'infrastructure le permet.
- **Conserver une copie hors serveur** (coffre-fort de mots de passe de l'équipe technique, accès
  restreint) — un secret perdu rend les archives passées **définitivement irrécupérables**. Il n'y
  a pas de mécanisme de recouvrement du mot de passe côté ZipArchive/AES.
- Ne jamais transmettre le secret par un canal non chiffré (email en clair, chat non sécurisé).

## Rotation

Changer `BACKUP_ARCHIVE_PASSWORD` ne rechiffre pas les archives déjà produites : les anciennes
restent lisibles avec l'ancien mot de passe, les nouvelles avec le nouveau. Conserver les deux
tant que les anciennes archives sont dans la fenêtre de rétention (`config/backup.php` :
7j/16j/8sem/4mois/2ans).

## Test réel effectué (P5)

1. Génération d'un mot de passe (`openssl rand -base64 32`), jamais committé, jamais affiché.
2. `BACKUP_ARCHIVE_PASSWORD=<secret> DB_DATABASE=iboa_erp_test php artisan backup:run` (backup
   complet DB + fichiers, pas `--only-db`) — 12,07 s, archive 181,19 Ko, 26 entrées.
3. Tentative de déchiffrement avec un mauvais mot de passe → échec (`getFromIndex` retourne
   `false`).
4. Déchiffrement avec le bon mot de passe → succès, contenu exact restitué.
5. Restauration complète (base + fichiers) dans un environnement isolé, jamais vers `iboa_erp` ni
   `iboa_erp_test` eux-mêmes — voir Phase 3/4 du rapport P5 pour le détail RTO/RPO.

**Verdict : GO.**
