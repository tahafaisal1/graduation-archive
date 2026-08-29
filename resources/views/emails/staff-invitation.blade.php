<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Tajawal','Segoe UI',Tahoma,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;">
                <tr><td style="background:#103A52;padding:28px 32px;text-align:center;">
                    <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="كلية التقنية الإلكترونية" height="56" style="display:inline-block;">
                </td></tr>
                <tr><td style="padding:32px;direction:rtl;text-align:right;">
                    <h1 style="margin:0 0 16px;font-size:20px;color:#103A52;">مرحباً {{ $inviteeName }}</h1>
                    <p style="margin:0 0 12px;line-height:1.9;font-size:15px;">
                        قام <strong>{{ $inviterName }}</strong> بدعوتك للانضمام إلى <strong>منظومة أرشفة مشاريع التخرج</strong>
                        الخاصة بكلية التقنية الإلكترونية — وهي المنظومة التي تُدار من خلالها أرشفة وتصنيف مشاريع التخرج.
                    </p>
                    <p style="margin:0 0 12px;line-height:1.9;font-size:15px;">
                        لإتمام إنشاء حسابك، يرجى الضغط على الزر أدناه لتعيين كلمة المرور الخاصة بك.
                        <strong>ينتهي هذا الرابط خلال 24 ساعة.</strong>
                    </p>
                    <p style="margin:0 0 24px;line-height:1.9;font-size:14px;color:#64748b;">
                        إذا لم تكن تتوقع هذه الرسالة، يمكنك تجاهلها أو التواصل مع مدير المنظومة.
                    </p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;"><tr><td style="border-radius:8px;background:#1B6B93;">
                        <a href="{{ $setupUrl }}" style="display:inline-block;padding:14px 32px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;">إنشاء كلمة المرور</a>
                    </td></tr></table>
                    <p style="margin:0 0 8px;font-size:13px;color:#64748b;">إذا لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:</p>
                    <p style="margin:0;font-size:12px;color:#1B6B93;word-break:break-all;direction:ltr;text-align:left;">{{ $setupUrl }}</p>
                </td></tr>
                <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;font-size:12px;color:#94a3b8;border-top:1px solid #e2e8f0;">
                    كلية التقنية الإلكترونية — طرابلس
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
