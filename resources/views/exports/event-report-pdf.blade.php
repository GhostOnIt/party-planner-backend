<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rapport - {{ $event->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #1f2937; font-size: 24px; margin-bottom: 5px; }
        h2 { color: #4b5563; font-size: 16px; margin-top: 25px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        .header { border-bottom: 2px solid #1f2937; padding-bottom: 15px; margin-bottom: 20px; }
        .meta { color: #6b7280; font-size: 11px; }
        .info-grid { display: table; width: 100%; margin: 15px 0; }
        .info-row { display: table-row; }
        .info-label { display: table-cell; width: 120px; font-weight: bold; padding: 5px 0; }
        .info-value { display: table-cell; padding: 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1f2937; color: white; padding: 8px; text-align: left; font-size: 11px; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        .stat-box { display: inline-block; background: #f3f4f6; padding: 10px 15px; margin: 5px; border-radius: 5px; }
        .stat-value { font-size: 20px; font-weight: bold; color: #1f2937; }
        .stat-label { font-size: 10px; color: #6b7280; }
        .positive { color: #059669; }
        .negative { color: #dc2626; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; text-align: center; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $event->title }}</h1>
        <div class="meta">Rapport complet de l'événement</div>
    </div>

    <h2>Informations générales</h2>
    <div class="info-grid">
        <div class="info-row">
            <span class="info-label">Type :</span>
            <span class="info-value">{{ ucfirst($event->type) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Date :</span>
            <span class="info-value">{{ $event->date?->format('d/m/Y') ?? 'Non définie' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Lieu :</span>
            <span class="info-value">{{ $event->location ?? 'Non défini' }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Organisateur :</span>
            <span class="info-value">{{ $event->user->name }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Statut :</span>
            <span class="info-value">{{ ucfirst($event->status) }}</span>
        </div>
    </div>

    <h2>Résumé des invités</h2>
    <div>
        <div class="stat-box">
            <div class="stat-value">{{ $guestStats['total'] }}</div>
            <div class="stat-label">Total invités</div>
        </div>
        <div class="stat-box">
            <div class="stat-value positive">{{ $guestStats['accepted'] }}</div>
            <div class="stat-label">Accepté</div>
        </div>
        <div class="stat-box">
            <div class="stat-value negative">{{ $guestStats['declined'] }}</div>
            <div class="stat-label">Décliné</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $guestStats['pending'] }}</div>
            <div class="stat-label">En attente</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $guestStats['checked_in'] }}</div>
            <div class="stat-label">Check-in</div>
        </div>
    </div>

    <h2>Résumé des tâches</h2>
    <div>
        <div class="stat-box">
            <div class="stat-value">{{ $taskStats['total'] }}</div>
            <div class="stat-label">Total tâches</div>
        </div>
        <div class="stat-box">
            <div class="stat-value positive">{{ $taskStats['completed'] }}</div>
            <div class="stat-label">Terminées</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $taskStats['in_progress'] }}</div>
            <div class="stat-label">En cours</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">{{ $taskStats['todo'] }}</div>
            <div class="stat-label">À faire</div>
        </div>
    </div>

    <h2>Résumé du budget</h2>
    <table style="width: auto;">
        <tr>
            <td><strong>Budget estimé :</strong></td>
            <td style="text-align: right;">{{ number_format($budgetStats['total_estimated'], 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td><strong>Coût réel :</strong></td>
            <td style="text-align: right;">{{ number_format($budgetStats['total_actual'], 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td><strong>Payé :</strong></td>
            <td style="text-align: right;" class="positive">{{ number_format($budgetStats['total_paid'], 0, ',', ' ') }} FCFA</td>
        </tr>
    </table>

    @if($event->collaborators->count() > 0)
    <h2>Collaborateurs</h2>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Rôle</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            @foreach($event->collaborators as $collab)
            <tr>
                <td>{{ $collab->user->name }}</td>
                <td>{{ ucfirst($collab->role) }}</td>
                <td>{{ $collab->accepted_at ? 'Accepté' : 'En attente' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>Rapport généré le {{ $generatedAt->format('d/m/Y à H:i') }}</p>
        <p>Party Planner - Organisez vos événements en toute simplicité</p>
    </div>
</body>
</html>
