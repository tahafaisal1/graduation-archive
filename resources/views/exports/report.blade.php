<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            direction: rtl;
            font-size: 11px;
            color: #1f2937;
            padding: 24px;
        }
        .header { border-bottom: 3px solid #1E40AF; padding-bottom: 12px; margin-bottom: 20px; }
        .header h1 { font-size: 18px; color: #1E40AF; margin-bottom: 4px; }
        .header p { font-size: 10px; color: #6b7280; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            font-size: 10px;
        }
        th {
            background: #1E40AF;
            color: #ffffff;
            padding: 7px 8px;
            text-align: right;
            font-weight: bold;
        }
        td { padding: 6px 8px; border: 1px solid #e5e7eb; }
        tr:nth-child(even) td { background: #f9fafb; }
        .footer {
            margin-top: 28px;
            font-size: 9px;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <p>تاريخ التصدير: {{ now()->format('Y-m-d H:i') }}</p>
    </div>

    @if(!empty($headers) && !empty($rows))
    <table>
        <thead>
            <tr>
                @foreach($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
            <tr>
                @foreach($row as $cell)
                    <td>{{ $cell ?? '—' }}</td>
                @endforeach
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p style="color:#6b7280; margin-top:16px;">لا توجد بيانات للعرض.</p>
    @endif

    <div class="footer">نظام أرشيف مشاريع التخرج — كلية التقنية الإلكترونية</div>
</body>
</html>
