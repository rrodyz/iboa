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
Schedule::command('invoices:generate-recurring')->dailyAt('06:30');

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
