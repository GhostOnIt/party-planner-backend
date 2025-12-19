<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Liste des invités - {{ $event->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { color: #1f2937; font-size: 24px; margin-bottom: 5px; }
        h2 { color: #4b5563; font-size: 16px; margin-top: 20px; }
        .header { border-bottom: 2px solid #e5e7eb; padding-bottom: 15px; margin-bottom: 20px; }
        .meta { color: #6b7280; font-size: 11px; }
        .stats { display: flex; margin: 20px 0; }
        .stat-box { background: #f3f4f6; padding: 10px 15px; margin-right: 10px; border-radius: 5px; }
        .stat-value { font-size: 18px; font-weight: bold; color: #1f2937; }
        .stat-label { font-size: 10px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #1f2937; color: white; padding: 8px; text-align: left; font-size: 11px; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        tr:nth-child(even) { background: #f9fafb; }
        .status-accepted { color: #059669; }
        .status-declined { color: #dc2626; }
        .status-pending { color: #d97706; }
        .status-maybe { color: #6b7280; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $event->title }}</h1>
        <div class="meta">
            @if($event->date)Date : {{ $event->date->format('d/m/Y') }}@endif
            @if($event->location) | Lieu : {{ $event->location }}@endif
        </div>
    </div>

    <h2>Résumé</h2>
    <table style="width: auto; margin-bottom: 20px;">
        <tr>
            <td><strong>Total invités :</strong></td>
            <td>{{ $stats['total'] }}</td>
            <td style="padding-left: 30px;"><strong>Accepté :</strong></td>
            <td class="status-accepted">{{ $stats['accepted'] }}</td>
        </tr>
        <tr>
            <td><strong>Décliné :</strong></td>
            <td class="status-declined">{{ $stats['declined'] }}</td>
            <td style="padding-left: 30px;"><strong>En attente :</strong></td>
            <td class="status-pending">{{ $stats['pending'] }}</td>
        </tr>
        <tr>
            <td><strong>Peut-être :</strong></td>
            <td class="status-maybe">{{ $stats['maybe'] }}</td>
            <td style="padding-left: 30px;"><strong>Check-in :</strong></td>
            <td>{{ $stats['checked_in'] }}</td>
        </tr>
    </table>

    <h2>Liste des invités</h2>
    <table>
        <thead>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Téléphone</th>
                <th>Statut RSVP</th>
                <th>Check-in</th>
            </tr>
        </thead>
        <tbody>
            @foreach($guests as $guest)
            <tr>
                <td>{{ $guest->name }}</td>
                <td>{{ $guest->email ?? '-' }}</td>
                <td>{{ $guest->phone ?? '-' }}</td>
                <td class="status-{{ $guest->rsvp_status }}">
                    {{ match($guest->rsvp_status) {
                        'accepted' => 'Accepté',
                        'declined' => 'Décliné',
                        'pending' => 'En attente',
                        'maybe' => 'Peut-être',
                        default => $guest->rsvp_status
                    } }}
                </td>
                <td>{{ $guest->checked_in ? '✓' : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Généré le {{ $generatedAt->format('d/m/Y à H:i') }} - Party Planner
    </div>
</body>
</html>
