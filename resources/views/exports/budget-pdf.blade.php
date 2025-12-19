<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Budget - {{ $event->title }}</title>
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
        .text-right { text-align: right; }
        .summary { background: #f3f4f6; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .summary-row { display: flex; justify-content: space-between; margin: 5px 0; }
        .positive { color: #059669; }
        .negative { color: #dc2626; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #e5e7eb; font-size: 10px; color: #9ca3af; }
        .total-row { font-weight: bold; background: #e5e7eb; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $event->title }}</h1>
        <div class="meta">Budget de l'événement</div>
    </div>

    <div class="summary">
        <table style="width: auto;">
            <tr>
                <td><strong>Budget estimé :</strong></td>
                <td class="text-right">{{ number_format($stats['total_estimated'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td><strong>Coût réel :</strong></td>
                <td class="text-right">{{ number_format($stats['total_actual'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td><strong>Écart :</strong></td>
                <td class="text-right {{ ($stats['total_actual'] - $stats['total_estimated']) > 0 ? 'negative' : 'positive' }}">
                    {{ number_format($stats['total_actual'] - $stats['total_estimated'], 0, ',', ' ') }} FCFA
                </td>
            </tr>
            <tr>
                <td><strong>Payé :</strong></td>
                <td class="text-right positive">{{ number_format($stats['total_paid'], 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td><strong>Reste à payer :</strong></td>
                <td class="text-right negative">{{ number_format($stats['total_unpaid'], 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>
    </div>

    <h2>Détail par élément</h2>
    <table>
        <thead>
            <tr>
                <th>Catégorie</th>
                <th>Nom</th>
                <th class="text-right">Estimé</th>
                <th class="text-right">Réel</th>
                <th>Payé</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>{{ match($item->category) {
                    'location' => 'Lieu',
                    'catering' => 'Traiteur',
                    'decoration' => 'Décoration',
                    'entertainment' => 'Animation',
                    'photography' => 'Photographie',
                    'transportation' => 'Transport',
                    'other' => 'Autre',
                    default => $item->category
                } }}</td>
                <td>{{ $item->name }}</td>
                <td class="text-right">{{ number_format($item->estimated_cost, 0, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($item->actual_cost ?? 0, 0, ',', ' ') }}</td>
                <td>{{ $item->paid ? '✓' : '-' }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">TOTAL</td>
                <td class="text-right">{{ number_format($stats['total_estimated'], 0, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($stats['total_actual'], 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Généré le {{ $generatedAt->format('d/m/Y à H:i') }} - Party Planner
    </div>
</body>
</html>
