# Audit de sécurité du scheduler (PILOT-DEPLOY-02, lecture seule)

Vérification de `onOneServer()`/`withoutOverlapping()` sur les tâches
planifiées (`routes/console.php`) — **aucun code de scheduler modifié**,
ce fichier est hors périmètre de cette mission (préparation de déploiement
uniquement). Recommandations seulement.

| Commande | onOneServer | withoutOverlapping | Constat |
|---|---|---|---|
| `backup:run` | oui | non | pilote mono-serveur : suffisant |
| `backup:clean` | oui | non | suffisant |
| `backup:monitor` | oui | non | lecture seule, sans risque |
| `invoices:generate-recurring` | oui | oui | déjà protégé (anti-doublons factures) |
| `alerts:run` | non | oui | suffisant en mono-serveur |
| `audit:business` | **non** | **non** | **écarts non protégés** |
| `audit:sync` | **non** | **non** | **écarts non protégés** |
| `sync:modules` | **non** | **non** | **écriture non protégée — le plus sensible des trois** |
| `invoices:mark-overdue` | non | non | idempotent par nature (marquage), risque faible |
| `crm:notify-overdue-activities` | non | non | pire cas = notification en double, faible impact |
| `validations:remind` | non | non | pire cas = relance en double, faible impact |

## Recommandation

`sync:modules` réécrit des agrégats dénormalisés (soldes clients/
fournisseurs, restes à payer...) — une exécution qui chevauche la
précédente (run anormalement long, ou futur multi-serveur) risque une
écriture concurrente sur les mêmes lignes. `audit:business`/`audit:sync`
sont en lecture seule donc moins risqués, mais un chevauchement double
inutilement leur coût et leurs notifications.

Ajouter `->withoutOverlapping()` aux trois (`audit:business`, `audit:sync`,
`sync:modules`) serait la correction minimale — **non appliquée ici**,
`routes/console.php` étant hors périmètre de PILOT-DEPLOY-02 (préparation
de déploiement, aucune modification fonctionnelle). À traiter dans une
mission dédiée si jugé prioritaire avant le pilote réel.
