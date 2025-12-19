<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tâches - {{ $event->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #1f2937; font-size: 24px; margin-bottom: 5px; }
        h2 { color: #4b5563; font-size: 16px; margin-top: 20px; }
        .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px; }
        .meta { color: #6b7280; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #1f2937; color: white; padding: 8px; text-align: left; font-size: 11px; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        tr:nth-child(even) { background: #f9fafb; }
        .status-todo { color: #6b7280; }
        .status-in_progress { color: #d97706; }
        .status-completed { color: #059669; }
        .status-cancelled { color: #dc2626; }
        .priority-high { color: #dc2626; font-weight: bold; }
        .priority-medium { color: #d97706; }
        .priority-low { color: #6b7280; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $event->title }}</h1>
        <div class="meta">Liste des tâches</div>
    </div>

    <h2>Résumé</h2>
    <table style="width: auto; margin-bottom: 20px;">
        <tr>
            <td><strong>Total tâches :</strong></td>
            <td>{{ $stats['total'] }}</td>
            <td style="padding-left: 30px;"><strong>Terminées :</strong></td>
            <td class="status-completed">{{ $stats['completed'] }}</td>
        </tr>
        <tr>
            <td><strong>En cours :</strong></td>
            <td class="status-in_progress">{{ $stats['in_progress'] }}</td>
            <td style="padding-left: 30px;"><strong>À faire :</strong></td>
            <td class="status-todo">{{ $stats['todo'] }}</td>
        </tr>
    </table>

    <h2>Liste des tâches</h2>
    <table>
        <thead>
            <tr>
                <th>Titre</th>
                <th>Statut</th>
                <th>Priorité</th>
                <th>Assigné à</th>
                <th>Échéance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tasks as $task)
            <tr>
                <td>{{ $task->title }}</td>
                <td class="status-{{ $task->status }}">
                    {{ match($task->status) {
                        'todo' => 'À faire',
                        'in_progress' => 'En cours',
                        'completed' => 'Terminé',
                        'cancelled' => 'Annulé',
                        default => $task->status
                    } }}
                </td>
                <td class="priority-{{ $task->priority }}">
                    {{ match($task->priority) {
                        'low' => 'Basse',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        default => $task->priority
                    } }}
                </td>
                <td>{{ $task->assignedUser?->name ?? '-' }}</td>
                <td>{{ $task->due_date?->format('d/m/Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Généré le {{ $generatedAt->format('d/m/Y à H:i') }} - Party Planner
    </div>
</body>
</html>
