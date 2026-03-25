<x-mail::message>
# Demande d'Absence Approuvée

Bonjour **{{ $absenceRequest->user->name }}**,

Nous avons le plaisir de vous informer que votre demande d'absence a été **approuvée** par la direction.

**Détails de l'absence :**
- **Type :** {{ $absenceRequest->absenceType->name }}
- **De :** {{ \Carbon\Carbon::parse($absenceRequest->start_date)->translatedFormat('d F Y') }}
- **Au :** {{ \Carbon\Carbon::parse($absenceRequest->end_date)->translatedFormat('d F Y') }}
- **Durée totale :** {{ $absenceRequest->days_count }} jour(s)

Profitez bien de votre congé !

<x-mail::button :url="env('FRONTEND_URL', 'http://localhost:5173') . '/employee/requests'">
Voir mes demandes
</x-mail::button>

Cordialement,<br>
L'équipe RH
</x-mail::message>
