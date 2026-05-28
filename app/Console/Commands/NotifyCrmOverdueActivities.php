<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
use App\Models\User;
use App\Notifications\CrmActivityOverdueNotification;
use Illuminate\Console\Command;

/**
 * Notifie les responsables des activités CRM en retard.
 * Même pattern que MarkOverdueInvoices — canal database.
 * Planifié à 08h00 chaque jour (routes/console.php).
 */
class NotifyCrmOverdueActivities extends Command
{
    protected $signature   = 'crm:notify-overdue-activities';
    protected $description = 'Notifie les responsables CRM des activités en retard';

    public function handle(): int
    {
        // Activités en retard depuis moins de 3 jours (pour éviter le spam)
        $activities = CrmActivity::overdue()
            ->where('due_at', '>=', now()->subDays(3))
            ->with(['user', 'contact', 'opportunity'])
            ->get();

        $count = 0;
        foreach ($activities as $activity) {
            // Notifier le responsable de l'activité (ou les managers si non assigné)
            $notifiable = $activity->user
                ?? User::where('company_id', $activity->company_id)
                        ->permission('settings.manage') // managers/admins
                        ->first();

            if ($notifiable) {
                $notifiable->notify(new CrmActivityOverdueNotification($activity));
                $count++;
            }
        }

        $this->info("{$count} notification(s) envoyée(s) pour activités CRM en retard.");
        return self::SUCCESS;
    }
}
