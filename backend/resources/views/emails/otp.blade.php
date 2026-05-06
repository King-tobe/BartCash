<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Verify your Bartcash account</title>
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
                                Thanks for signing up for Bartcash. To complete your registration, please verify your email address using the code below.
                            </p>

                            {{-- OTP Box --}}
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding: 24px 0;">
                                        <div style="display:inline-block; background-color:#f0ebff; border: 2px dashed #6C47FF; border-radius:12px; padding: 20px 48px;">
                                            <p style="margin:0 0 4px; font-size:12px; color:#6C47FF; text-transform:uppercase; letter-spacing:1px; font-weight:600;">Verification Code</p>
                                            <p style="margin:0; font-size:42px; font-weight:700; color:#6C47FF; letter-spacing:10px;">{{ $otp }}</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 16px; font-size:14px; color:#888; text-align:center;">
                                This code expires in <strong>10 minutes</strong>.
                            </p>
                            <p style="margin:0; font-size:14px; color:#888; text-align:center;">
                                If you did not create a Bartcash account, you can safely ignore this email.
                            </p>
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
