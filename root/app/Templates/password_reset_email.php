<table role="presentation" style="width:100%; max-width:600px; font-family:Arial, sans-serif; border-collapse:collapse;">
    <tr>
        <td style="padding:24px;">
            <h2 style="color:#1f2d3d; margin-bottom:16px;">Password Reset Request</h2>
            <p style="color:#4f5b66; line-height:1.5;">
                Hello <?= htmlspecialchars($firstName ?? 'there', ENT_QUOTES, 'UTF-8'); ?>,
            </p>
            <p style="color:#4f5b66; line-height:1.5;">
                We received a request to reset your password for <?= htmlspecialchars($appName ?? 'the DMARC Dashboard', ENT_QUOTES, 'UTF-8'); ?>.
                If you made this request, please click the button below to choose a new password. This link will expire in one hour.
            </p>
            <p style="text-align:center; margin:32px 0;">
                <a href="<?= htmlspecialchars($resetUrl ?? '#', ENT_QUOTES, 'UTF-8'); ?>" style="background-color:#5755d9; color:#ffffff; padding:12px 24px; border-radius:4px; text-decoration:none; display:inline-block;">
                    Reset Password
                </a>
            </p>
            <p style="color:#7d8a97; line-height:1.5;">
                If you did not request a password reset, please ignore this email. Your existing password will remain unchanged.
            </p>
        </td>
    </tr>
</table>
