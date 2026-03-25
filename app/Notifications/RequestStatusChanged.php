<?php

namespace App\Notifications;

use App\Models\AbsenceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequestStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public AbsenceRequest $absenceRequest,
        public string $event // 'chef_approved', 'chef_rejected', 'directeur_approved', 'directeur_rejected', 'pending_directeur'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $request = $this->absenceRequest;
        $type    = $request->absenceType?->name ?? 'Absence';

        $messages = [
            'new_request'         => "Nouvelle demande de « {$type} » de {$request->user?->name} à examiner.",
            'chef_approved'       => "Votre demande de « {$type} » a été approuvée par le chef de service. En attente du directeur.",
            'chef_rejected'       => "Votre demande de « {$type} » a été rejetée par le chef de service.",
            'directeur_approved'  => "Votre demande de « {$type} » a été approuvée définitivement par le directeur.",
            'directeur_rejected'  => "Votre demande de « {$type} » a été rejetée par le directeur.",
            'pending_directeur'   => "Une demande de « {$type} » de {$request->user?->name} est en attente de votre validation.",
        ];

        return [
            'request_id'    => $request->id,
            'event'         => $this->event,
            'employee_name' => $request->user?->name,
            'type'          => $type,
            'start_date'    => $request->start_date?->toDateString(),
            'end_date'      => $request->end_date?->toDateString(),
            'status'        => $request->status,
            'message'       => $messages[$this->event] ?? "Mise à jour sur votre demande de « {$type} ».",
        ];
    }
}
