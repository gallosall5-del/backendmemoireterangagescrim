<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a202c; margin: 0; padding: 0; }
        .header { text-align: center; padding-bottom: 10px; border-bottom: 2px solid #742a2a; margin-bottom: 12px; }
        .header h1 { color: #742a2a; font-size: 15px; margin: 0 0 3px; }
        .header h2 { color: #2d3748; font-size: 12px; margin: 0 0 3px; }
        .header p { color: #718096; font-size: 8px; margin: 2px 0; }
        .meta { font-size: 8px; color: #4a5568; margin-bottom: 10px; display: flex; gap: 16px; flex-wrap: wrap; }
        .meta strong { color: #2d3748; }
        .badge { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; padding: 1px 5px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background-color: #742a2a; color: white; padding: 5px 4px; text-align: left; font-size: 8px; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; font-size: 8px; vertical-align: top; }
        tr:nth-child(even) td { background-color: #fff5f5; }
        .footer { text-align: center; margin-top: 18px; font-size: 7.5px; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .total-row td { font-weight: bold; background-color: #fed7d7 !important; font-size: 9px; }
        .empty { text-align: center; color: #a0aec0; padding: 24px; font-style: italic; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="header">
        <h1>TERANGA GESCRIM</h1>
        <p>Direction de la Sécurité Publique — Sénégal</p>
        <h2>{{ $titre }}</h2>
        <p>Généré le : {{ $date_generation }} — Agent : {{ $agent }}</p>
    </div>

    <div class="meta">
        <span><strong>Période :</strong> <span class="badge">{{ $period_label }}</span></span>
        <span><strong>Total :</strong> {{ $records->count() }} accident(s)</span>
    </div>

    @if($records->isEmpty())
        <p class="empty">Aucun accident trouvé pour les critères sélectionnés.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>#</th><th>Date</th><th>Heure</th><th>Type</th><th>Lieu</th>
                <th>Commune</th><th>Service</th><th>Moyen</th><th>Cause</th><th>Victimes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $a)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $a->date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $a->heure ?? '-' }}</td>
                <td>{{ $a->type ?? '-' }}</td>
                <td>{{ $a->lieu ?? '-' }}</td>
                <td>{{ $a->commune->nom ?? '-' }}</td>
                <td>{{ $a->service->nom ?? '-' }}</td>
                <td>{{ $a->moyen ?? '-' }}</td>
                <td>{{ $a->cause_probable ?? '-' }}</td>
                <td>{{ $a->victimes->count() }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="9">Total</td>
                <td>{{ $records->sum(fn($a) => $a->victimes->count()) }}</td>
            </tr>
        </tbody>
    </table>
    @endif

    <div class="footer">
        <p>Teranga GESCRIM — Rapport confidentiel — {{ $date_generation }}</p>
        <p>Direction de la Sécurité Publique — Accès réservé au personnel autorisé</p>
    </div>
</body>
</html>
