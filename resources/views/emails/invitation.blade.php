<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: sans-serif; background: #f9fafb; padding: 40px 0; margin: 0;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 40px;">
        <img src="{{ asset('logo.jpg') }}" alt="Logo" style="height: 40px; width: auto; margin-bottom: 20px; display: block;" />
        <h2 style="margin: 0 0 8px; font-size: 20px; color: #111827;">You've been invited</h2>
        <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px;">
            <strong>{{ $invitation->invitedBy->name }}</strong> has invited you to join
            <strong>{{ $invitation->tenant->name }}</strong> as a
            <strong>{{ $invitation->role->name }}</strong>.
        </p>

        <a
            href="{{ url('/invite/' . $rawToken) }}"
            style="display: inline-block; background: #4f46e5; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600;"
        >Accept Invitation</a>

        <p style="margin: 24px 0 0; font-size: 13px; color: #9ca3af;">
            This invitation expires on {{ $invitation->expires_at->format('M j, Y') }}.
            If you weren't expecting this, you can ignore this email.
        </p>
    </div>
</body>
</html>
