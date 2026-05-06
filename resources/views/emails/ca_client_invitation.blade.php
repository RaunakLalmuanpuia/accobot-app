<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="font-family: sans-serif; background: #f9fafb; padding: 40px 0; margin: 0;">
    <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 40px;">
        <img src="{{ asset('logo.jpg') }}" alt="Logo" style="height: 40px; width: auto; margin-bottom: 20px; display: block;" />

        <h2 style="margin: 0 0 8px; font-size: 20px; color: #111827;">You've been invited by your CA</h2>
        <p style="margin: 0 0 24px; color: #6b7280; font-size: 15px;">
            <strong>{{ $invitation->invitedBy->name }}</strong> from
            <strong>{{ $invitation->tenant->name }}</strong> wants to connect with your business on Accobot to manage your accounting.
        </p>

        @if(!empty($invitation->meta['business_name']))
        <p style="margin: 0 0 24px; color: #6b7280; font-size: 14px; background: #f3f4f6; padding: 12px 16px; border-radius: 8px;">
            Your CA has suggested the business name: <strong>{{ $invitation->meta['business_name'] }}</strong>
        </p>
        @endif

        <a
            href="{{ url('/invite/' . $rawToken) }}"
            style="display: inline-block; background: #7c3aed; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 15px; font-weight: 600;"
        >Accept &amp; Connect</a>

        <p style="margin: 24px 0 0; font-size: 13px; color: #9ca3af;">
            This invitation expires on {{ $invitation->expires_at->format('M j, Y') }}.
            Once connected, your CA will have access to your business's books. You remain the owner of your data.
            If you weren't expecting this, you can ignore this email.
        </p>
    </div>
</body>
</html>
