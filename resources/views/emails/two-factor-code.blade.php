{{-- Email clients ignore external stylesheets, so this is inline by necessity. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NexHRIS verification code</title>
</head>
<body style="margin:0; padding:0; background:#f5f5ee; font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif; color:#3a382f;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5ee; padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:520px; background:#ffffff; border:1px solid #eceadf; border-radius:12px; overflow:hidden;">

                <tr>
                    <td style="background:#780000; padding:20px 28px;">
                        <p style="margin:0; color:#ffffff; font-size:17px; font-weight:700; letter-spacing:.4px;">NexHRIS</p>
                        <p style="margin:3px 0 0; color:rgba(255,255,255,.7); font-size:12px;">
                            Ilocos Sur Polytechnic State College &middot; Tagudin Campus
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <p style="margin:0 0 6px; font-size:15px;">Hello {{ $user->name }},</p>

                        <p style="margin:0 0 22px; font-size:14px; line-height:1.6; color:#6b6859;">
                            Use the verification code below to finish signing in as
                            <strong style="color:#3a382f;">{{ $user->roleLabel() }}</strong>.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center"
                                    style="background:#fdf5f5; border:1px solid #f5cccc; border-radius:10px; padding:18px;">
                                    <p style="margin:0; font-size:32px; font-weight:700; letter-spacing:9px;
                                              color:#780000; font-family:'Courier New',monospace;">{{ $code }}</p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin:20px 0 0; font-size:13px; line-height:1.6; color:#6b6859;">
                            This code expires in <strong>{{ $ttlMinutes }} minutes</strong> and can be used once.
                        </p>

                        <p style="margin:18px 0 0; padding-top:18px; border-top:1px solid #eceadf;
                                  font-size:12px; line-height:1.6; color:#8a8778;">
                            If you did not try to sign in, your password may no longer be private.
                            Contact the HR Office and change it as soon as you can.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="background:#fbfbf7; padding:14px 28px; border-top:1px solid #eceadf;">
                        <p style="margin:0; font-size:11px; color:#8a8778;">
                            Automated message from NexHRIS. Please do not reply.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
