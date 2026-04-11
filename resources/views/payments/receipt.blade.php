<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        .muted { color: #666; font-size: 11px; margin-top: 24px; }
    </style>
</head>
<body>
    <h1>Reçu de paiement</h1>
    <p><strong>{{ config('app.name') }}</strong></p>
    <p>Émis le {{ $issuedAt->format('d/m/Y H:i') }}</p>

    <table>
        <tr>
            <th>Référence</th>
            <td>{{ $payment->transaction_reference ?? $payment->id }}</td>
        </tr>
        <tr>
            <th>Montant</th>
            <td>{{ $payment->formatted_amount }}</td>
        </tr>
        <tr>
            <th>Moyen de paiement</th>
            <td>{{ $payment->payment_method_label }}</td>
        </tr>
        @if($payment->subscription?->event)
        <tr>
            <th>Événement</th>
            <td>{{ $payment->subscription->event->title }}</td>
        </tr>
        @endif
        <tr>
            <th>Client</th>
            <td>{{ $payment->subscription?->user?->name ?? '—' }} ({{ $payment->subscription?->user?->email ?? '—' }})</td>
        </tr>
    </table>

    <p class="muted">Document généré automatiquement — conservez ce reçu pour vos archives.</p>
</body>
</html>
