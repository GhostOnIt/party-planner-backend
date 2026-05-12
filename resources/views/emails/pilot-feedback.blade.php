<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feedback pilote</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #1e293b;">
    <p style="margin: 0 0 1rem;">
        <strong>Expéditeur :</strong> {{ $userName }} &lt;{{ $userEmail }}&gt;
    </p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 1rem 0;">
    <div style="white-space: pre-wrap;">{{ $body }}</div>
</body>
</html>
