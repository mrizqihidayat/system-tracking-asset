{{-- Email kode OTP lupa password (T40): judul web & logo dari Pengaturan, bahasa sederhana. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kode OTP reset password {{ $siteTitle }}</title>
</head>
<body style="margin:0; padding:24px 12px; background:#f5f7f9; font-family:Arial, Helvetica, sans-serif; color:#1f2b37;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; margin:0 auto; background:#ffffff; border:1px solid #e3e8ed; border-radius:12px;">
        <tr>
            <td style="padding:24px 24px 8px;">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteTitle }}" height="40" style="display:block; height:40px; width:auto; margin-bottom:12px;">
                @endif
                <p style="margin:0; font-size:18px; font-weight:bold;">{{ $siteTitle }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:8px 24px 24px; font-size:15px; line-height:1.6;">
                <p style="margin:0 0 12px;">Ada permintaan untuk membuat password baru akun Anda. Masukkan kode ini di halaman Lupa password:</p>
                <p style="margin:0 0 12px; padding:14px; background:#eef1f4; border-radius:8px; text-align:center; font-family:'Courier New', monospace; font-size:30px; font-weight:bold; letter-spacing:8px;">{{ $otp }}</p>
                <p style="margin:0 0 12px;">Kode berlaku <strong>{{ $minutes }} menit</strong> dan hanya bisa dipakai satu kali.</p>
                <p style="margin:0 0 12px;">Jangan berikan kode ini kepada siapa pun, termasuk staf SHD.</p>
                <p style="margin:0; color:#5e6b77; font-size:13px;">Tidak merasa meminta? Abaikan email ini. Password Anda tidak berubah.</p>
            </td>
        </tr>
    </table>
</body>
</html>
