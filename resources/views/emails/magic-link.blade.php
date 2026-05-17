<!doctype html>
<html lang="en">
<body style="font-family: system-ui, -apple-system, sans-serif; line-height: 1.5; color: #0f172a; padding: 24px;">
    <h1 style="font-size: 1.3rem;">Sign in to Chore Charts</h1>
    <p>Click the link below to sign in. It expires in 1 hour.</p>
    <p><a href="{{ $url }}" style="display: inline-block; background: #2563eb; color: #fff; padding: 10px 16px; border-radius: 6px; text-decoration: none;">Sign in</a></p>
    <p style="font-size: 0.85rem; color: #64748b;">If the button doesn't work, paste this URL into your browser:</p>
    <p style="font-size: 0.85rem; word-break: break-all;">{{ $url }}</p>
    <p style="font-size: 0.85rem; color: #64748b;">If you didn't request this, you can ignore this email.</p>
</body>
</html>
