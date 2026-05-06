<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reset your Bartcash password</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f7; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">

                    {{-- Header --}}
                    <tr>
                        <td align="center" style="background-color:#6C47FF; padding: 36px 40px;">
                            <h1 style="margin:0; color:#ffffff; font-size:26px; font-weight:700; letter-spacing:-0.5px;">Bartcash</h1>
                            <p style="margin:8px 0 0; color:#e0d9ff; font-size:14px;">Barter. Trade. Exchange.</p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px 40px 24px;">
                            <p style="margin:0 0 16px; font-size:16px; color:#1a1a2e;">Hi {{ $firstName }},</p>
                            <p style="margin:0 0 24px; font-size:15px; color:#444; line-height:1.6;">
                                We received a request to reset the password for your Bartcash account. Tap the button below to choose a new password.
                            </p>

                            {{-- CTA Button --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 24px 0;">
                                        <a href="{{ $resetLink }}"
                                           style="display:inline-block; background-color:#6C47FF; color:#ffffff; text-decoration:none; font-size:16px; font-weight:600; padding:14px 40px; border-radius:8px; letter-spacing:0.3px;">
                                            Reset My Password
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 12px; font-size:14px; color:#888; text-align:center;">
                                This link expires in <strong>1 hour</strong>.
                            </p>

                            <p style="margin:0 0 24px; font-size:14px; color:#888; text-align:center;">
                                If you did not request a password reset, you can safely ignore this email. Your password will not be changed.
                            </p>

                            {{-- Fallback link --}}
                            <div style="background-color:#f8f8f8; border-radius:8px; padding:16px; word-break:break-all;">
                                <p style="margin:0 0 6px; font-size:12px; color:#999;">If the button doesn't work, copy and paste this link:</p>
                                <p style="margin:0; font-size:12px; color:#6C47FF;">{{ $resetLink }}</p>
                            </div>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px 36px; border-top: 1px solid #f0f0f0;">
                            <p style="margin:0; font-size:12px; color:#bbb; text-align:center;">
                                © {{ date('Y') }} Bartcash. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>