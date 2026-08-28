# Environnement pilote — état et prérequis

[P6 — Phase 1] **Aucun serveur cible n'est provisionné à ce jour** (confirmé par le
commanditaire lors de cette mission). Ce document a donc deux parties : l'état de la machine
de développement actuelle (qui n'est pas le futur hôte du pilote, sauf décision contraire), et
la checklist de prérequis pour la machine qui accueillera réellement le pilote.

## Partie A — Machine de développement actuelle (référence, pas la cible)

Relevé le 2026-08-28 sur `C:\laragon\www\iboa` (Laragon).

| Élément | Valeur |
|---|---|
| OS | Windows 11 Professionnel 10.0.26200 |
| RAM | 15,7 Go total / **1,7 Go libre au moment du relevé** (charge de dev élevée — plusieurs worktrees/suites de test en parallèle) |
| CPU | Intel Core i7-1165G7, 4 cœurs / 8 threads |
| PHP | 8.2.28 NTS (Visual C++ 2019 x64) |
| MySQL | 8.4.3 (MySQL Community Server) |
| Composer | 2.8.9 |
| Node | v22.16.0 |
| Serveur HTTP | Apache (`httpd`, via Laragon) |
| HTTPS | **Absent** — accès en `http://127.0.0.1/iboa/public` |
| Domaine | Aucun (localhost uniquement) |
| Timezone OS | Greenwich Standard Time (= UTC+0, cohérent avec `Africa/Ouagadougou` côté app) |
| Espace disque | 476 Go total, 147 Go libres (30 %) |
| Chemin projet | `C:\laragon\www\iboa` (+ worktrees `iboa-p*-*` pour chaque phase) |
| Utilisateur système | Session utilisateur Windows interactive (pas de compte de service dédié) |

**Ce n'est pas un environnement de pilote acceptable tel quel** : pas de HTTPS, pas de domaine,
pas de compte système dédié, RAM sous tension par l'usage de développement concurrent.
Utilisable uniquement pour la préparation/les tests, pas pour héberger des utilisateurs réels.

## Partie B — Prérequis pour la machine cible réelle (à provisionner)

À valider avant toute ouverture de pilote, quelle que soit la machine choisie :

| Élément | Exigence minimale |
|---|---|
| OS | Linux recommandé (Debian/Ubuntu LTS) pour un déploiement pérenne — cron natif, Supervisor/systemd disponibles nativement (contrairement à Windows où l'équivalent Task Scheduler + NSSM est plus fragile à opérer) |
| PHP | 8.2+ (version actuellement utilisée et testée) avec extensions : mysqli/pdo_mysql, zip (AES-256), gd ou imagick (PDF/QR), bcmath, mbstring, xml |
| MySQL/MariaDB | MySQL 8.4+ ou MariaDB équivalent, `utf8mb4`/`utf8mb4_unicode_ci`, `sql_mode` strict |
| Serveur HTTP | Apache ou Nginx, document root sur `public/`, réécriture d'URL active |
| HTTPS | **Obligatoire avant tout accès utilisateur réel** — certificat valide (Let's Encrypt suffit), sans quoi `SESSION_SECURE_COOKIE=true` et HSTS (déjà codés côté app, voir P5) ne peuvent pas être activés sans casser les connexions |
| Domaine | Un nom de domaine ou sous-domaine dédié (`APP_URL` doit être l'URL réelle, pas `127.0.0.1`) |
| Chemin projet | Hors document root public si possible (seul `public/` exposé au serveur HTTP) |
| Utilisateur système | Compte de service dédié, non privilégié, propriétaire des fichiers de l'app — pas un compte interactif partagé |
| RAM | 4 Go minimum recommandé pour PHP-FPM/Apache + MySQL en usage pilote limité (à réévaluer selon charge réelle) |
| Espace disque | Prévoir large marge pour la rétention des sauvegardes (`config/backup.php` : jusqu'à 2 ans en cleanup mensuel/annuel) |
| Composer/Node | Composer 2.x pour déployer les dépendances PHP ; Node uniquement nécessaire si un rebuild des assets (`npm run build`) est fait sur le serveur — sinon livrer `public/build/` déjà compilé |

**Ce document sera mis à jour dès qu'une machine cible réelle sera désignée** — les phases 2 à
5 de P6 (config production, scheduler, backup automatique, queue) qui exigent cette machine sont
documentées en checklist prête à l'emploi dans les sections correspondantes du rapport P6, à
exécuter au moment du provisionnement.
