<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your login link</title>
    <style>
        body { font-family: sans-serif; background: #0a0a0a; color: #e5e5e5; margin: 0; padding: 40px 20px; }
        .card { max-width: 480px; margin: 0 auto; background: #18181b; border: 1px solid #27272a; border-radius: 8px; padding: 40px; }
        h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 16px; color: #fff; }
        p { color: #a1a1aa; line-height: 1.6; margin: 0 0 24px; }
        .btn { display: inline-block; background: #e11d48; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; }
        .expire { font-size: 0.8rem; color: #52525b; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Nexus Radio</h1>
        <p>Click the link below to log in. No password needed.</p>
        <a href="{!! $link !!}" class="btn">Log in to the dashboard</a>
        <p class="expire">This link expires in 15 minutes and can only be used once.</p>
    </div>
</body>
</html>
