<!doctype html>
<html lang="{{ $contentLocale }}" dir="{{ $contentLocale === 'ar' ? 'rtl' : 'ltr' }}">
<body style="font-family: Arial, sans-serif; color: #202759; line-height: 1.7">
    <h1>{{ $contentLocale === 'ar' ? 'تم استلام طلب التسجيل' : 'Registration received' }}</h1>
    <p>{{ $contentLocale === 'ar' ? 'مرحباً' : 'Hello' }} {{ $applicantName }},</p>
    <p>{{ $contentLocale === 'ar' ? 'استلمنا طلب تسجيلك في الفعالية التالية:' : 'We received your registration for:' }}</p>
    <p><strong>{{ $eventTitle }}</strong></p>
    <p>{{ $contentLocale === 'ar' ? 'سيتم التواصل معك بعد مراجعة الطلب. لا تعني هذه الرسالة قبول التسجيل النهائي.' : 'We will contact you after reviewing the submission. This message does not indicate final acceptance.' }}</p>
</body>
</html>
