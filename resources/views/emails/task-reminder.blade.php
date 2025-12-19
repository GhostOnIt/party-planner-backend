<x-mail::message>
# Rappel de tâche

Bonjour,

@if($daysUntilDue === 0)
**Attention !** La tâche suivante est due aujourd'hui :
@else
La tâche suivante est due dans **{{ $daysUntilDue }} jour(s)** :
@endif

---

## {{ $task->title }}

@if($task->description)
{{ $task->description }}
@endif

**Événement :** {{ $event->title }}

@if($task->due_date)
**Date d'échéance :** {{ $task->due_date->format('d/m/Y') }}
@endif

**Priorité :** {{ ucfirst($task->priority) }}

---

<x-mail::button :url="$eventUrl">
Voir les tâches
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
