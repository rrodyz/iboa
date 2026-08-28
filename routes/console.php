<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Marquer chaque matin les factures en retard
Schedule::command('invoices:mark-overdue')->dailyAt('06:00');

// [MED-2] Générer les factures récurrentes échues (factures abonnements, prestations mensuelles…)
// [Anti-doublons] withoutOverlapping : deux exécutions simultanées (relance
// manuelle pendant le cron, redémarrage scheduler) généreraient les factures
// en double. onOneServer pour le futur multi-serveur.
Schedule::command('invoices:generate-recurring')->dailyAt('06:30')->withoutOverlapping()->onOneServer();

// Audit métier quotidien — non-zero exit code en cas d'anomalie HIGH
// (à coupler à un système d'alerte mail si besoin via ->emailOutputOnFailure())
Schedule::command('audit:business')->dailyAt('05:30');

// [AUTOMATION] Transitions de statut quotidiennes + alertes (devis expirés,
// factures en retard, PO d'approbation bloqués…). Tourne tôt avant l'arrivée
// au bureau pour que les KPIs du dashboard reflètent l'état du jour.
Schedule::command('automation:daily')->dailyAt('05:45');

// [AUDIT-SYNC] Audit synchronisation inter-modules — détecte les ruptures de
// chaîne entre devis/commandes/factures/paiements/réceptions/écritures.
Schedule::command('audit:sync')->dailyAt('05:50');

// [SYNC] Resynchronisation réparatrice des agrégats dénormalisés (balances
// clients/fournisseurs, restes à payer, montants facturés, statuts livraison,
// balances de comptes) — tourne juste après l'audit qui détecte.
Schedule::command('sync:modules')->dailyAt('05:55');

// [CRM] Notifier chaque matin les responsables des activités en retard
Schedule::command('crm:notify-overdue-activities')->dailyAt('08:00');

// [CDC §Workflow] Relance des validations en attente au-delà du délai
// configuré (config/validation.php — 24h production, 48h commercial/achats).
Schedule::command('validations:remind')->dailyAt('08:15');

// [CDC §14][P5 — Phase 3] Sauvegarde quotidienne complète (DB + fichiers).
// Auparavant --only-db : les pièces jointes/documents archivés (factures,
// BL, avoirs PDF sous storage/app/private/archives, uploads publics) ne
// remontaient dans aucune sauvegarde automatique. Le volume observé en
// exploitation réelle (documents commerciaux d'un pilote industriel) reste
// modeste (PDF de quelques dizaines à centaines de Ko) — le coût d'un
// backup complet quotidien est négligeable face au risque de perte
// définitive d'une pièce justificative non re-générable à l'identique.
Schedule::command('backup:clean')->dailyAt('01:30')->onOneServer();
Schedule::command('backup:run')->dailyAt('02:00')->onOneServer();
Schedule::command('backup:monitor')->dailyAt('07:00')->onOneServer();

// [PIL-04] Évaluation horaire des alertes par seuil → notification des rôles cibles.
// [Anti-doublons] withoutOverlapping : un run qui déborde sur l'heure suivante
// enverrait les mêmes notifications deux fois.
Schedule::command('alerts:run')->hourly()->withoutOverlapping();
