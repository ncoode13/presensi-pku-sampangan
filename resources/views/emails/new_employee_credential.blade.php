<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selamat Bergabung</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f4f7f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; width: 100%; }
        
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f7f6; padding-bottom: 40px; }
        .main-content { background-color: #ffffff; margin: 0 auto; max-width: 600px; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        
        .header-bar { background-color: #0f172a; height: 8px; width: 100%; }
        
        .content-padding { padding: 40px 40px; }
        
        h1 { color: #1e293b; font-size: 24px; font-weight: 700; margin: 0 0 20px; }
        p { color: #475569; font-size: 16px; line-height: 1.6; margin: 0 0 20px; }
        
        .cred-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: left; }
        .cred-label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 4px; display: block; }
        .cred-value { color: #0f172a; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 18px; font-weight: 700; display: block; margin-bottom: 15px; background: #fff; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; }
        .cred-value:last-child { margin-bottom: 0; }
        
        .btn-container { text-align: center; margin: 35px 0; }
        .btn { 
            display: inline-block; 
            background-color: #2563eb; 
            color: #ffffff !important; 
            padding: 14px 35px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-weight: bold; 
            font-size: 16px; 
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2); 
            transition: all 0.3s ease; 
            border: 1px solid #2563eb;
        }
        
        .btn:hover { 
            background-color: #0f172a !important; 
            border-color: #0f172a !important;
            box-shadow: 0 6px 12px rgba(15, 23, 42, 0.3);
            transform: translateY(-2px);
        }
        
        .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>

    <div class="wrapper">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" style="padding-top: 40px;"></td>
            </tr>
            <tr>
                <td align="center">
                    <div class="main-content">
                        <div class="header-bar"></div>

                        <div class="content-padding">
                            <h1>Halo, {{ $user->name }}! 👋</h1>
                            
                            <p>Selamat bergabung di tim kami! Akun kepegawaian Anda telah berhasil dibuat dan kini sudah aktif.</p>
                            
                            <p>Untuk kemudahan akses sehari-hari, kami sangat menyarankan Anda masuk menggunakan akun Google Anda.</p>
                            
                            <div class="btn-container">
                                <a href="{{ config('app.frontend_url') }}" class="btn" style="color: #ffffff;">
                                    Login via Aplikasi
                                </a>
                            </div>

                            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 30px 0;">

                            <p style="margin-bottom: 10px;">Jika Anda perlu masuk secara manual (tanpa Google), silakan gunakan akses berikut:</p>

                            <div class="cred-box">
                                <span class="cred-label">Email Login</span>
                                <span class="cred-value">{{ $user->email }}</span>
                                
                                <span class="cred-label">Password Sementara</span>
                                <span class="cred-value">{{ $password }}</span>
                            </div>

                            <p style="font-size: 13px; color: #dc2626; background-color: #fef2f2; padding: 10px; border-radius: 4px; border-left: 3px solid #dc2626;">
                                <strong>Penting:</strong> Demi keamanan data Anda, mohon segera ganti password ini setelah login pertama kali.
                            </p>

                            <br>
                            <p>Terima kasih dan sukses selalu,<br><strong>Tim HRD PKU Sampangan</strong></p>
                        </div>
                    </div>
                </td>
            </tr>
            <tr>
                <td align="center">
                    <div class="footer">
                        <p>&copy; {{ date('Y') }} PKU Sampangan. All rights reserved.</p>
                    </div>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>