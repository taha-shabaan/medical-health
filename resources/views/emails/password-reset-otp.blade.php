<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset OTP</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f6f8fb; padding: 24px; color: #1f2937;">
<div style="max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb;">
    <h2 style="margin: 0 0 12px;">Password Reset Request</h2>
    <p style="margin: 0 0 12px;">Use the following OTP code to reset your password:</p>

    <div style="font-size: 30px; font-weight: 700; letter-spacing: 6px; text-align: center; margin: 18px 0; color: #2563eb;">
        {{ $otp }}
    </div>

    <p style="margin: 0 0 8px;">This code is valid for {{ $expiresInMinutes }} minutes.</p>
    <p style="margin: 0; color: #6b7280; font-size: 13px;">If you did not request this, you can safely ignore this email.</p>
</div>
</body>
</html>
