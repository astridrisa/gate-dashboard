<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Log Gate</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #0f1c2e; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        p { margin: 0 0 10px; color: #5a6b82; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d6dee9; padding: 3px 5px; text-align: left; }
        th { background: #0b1f3a; color: #fff; }
    </style>
</head>
<body>
    <h1>Riwayat Log Transaksi Gate</h1>
    <p>
        Dicetak {{ now()->format('Y-m-d H:i') }}.
        @if ($total > $limit)
            Menampilkan {{ number_format($limit) }} dari {{ number_format($total) }} baris. Gunakan export Excel untuk data lengkap.
        @else
            {{ number_format($total) }} baris.
        @endif
    </p>
    <table>
        <thead><tr>@foreach ($header as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
        <tbody>
            @foreach ($rows as $r)
                <tr>@foreach ($r as $cell)<td>{{ $cell }}</td>@endforeach</tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>