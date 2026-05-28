<?php

namespace App\Notifications;

use App\Models\CrmActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CrmActivityOverdueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly CrmActivity $activity) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $context = $this->activity->contact?->name
            ?? $this->activity->opportunity?->title
            ?? '—';

        return [
            'type'       => 'crm_activity_overdue',
            'icon'       => 'clock',
            'color'      => 'amber',
            'title'      => 'Activité CRM en retard',
            'message'    => 'L\'activité « ' . $this->activity->subject . ' » '
                           . '(' . $this->activity->typeLabel() . ' — ' . $context . ') '
                           . 'était prévue le ' . $this->activity->due_at?->format('d/m/Y H:i') . '.',
            'url'        => route('crm.activities.index', ['status' => 'overdue']),
            'model_type' => 'CrmActivity',
            'model_id'   => $this->activity->id,
        ];
    }
}
