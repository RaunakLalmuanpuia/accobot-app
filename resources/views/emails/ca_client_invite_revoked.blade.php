<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: sans-serif; background: #f9fafb; padding: 40px 0; margin: 0;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 40px;">
        <img src="{{ asset('logo.jpg') }}" alt="Logo" style="height: 40px; width: auto; margin-bottom: 20px; display: block;" />

        <h2 style="margin: 0 0 8px; font-size: 20px; color: #111827;">Invitation withdrawn</h2>
        <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px;">
            <strong>{{ $invitation->tenant->name }}</strong> has withdrawn their invitation to manage your business on Accobot.
        </p>

        <p style="margin: 0; font-size: 13px; color: #9ca3af;">
            If you have any questions, please reach out to them directly.
        </p>
    </div>
</body>
</html>
