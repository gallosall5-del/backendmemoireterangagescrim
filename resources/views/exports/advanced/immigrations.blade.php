<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a202c; margin: 0; padding: 0; }
        .header { text-align: center; padding-bottom: 10px; border-bottom: 2px solid #1B4332; margin-bottom: 12px; }
        .header h1 { color: #1B4332; font-size: 15px; margin: 0 0 3px; }
        .header h2 { color: #2d3748; font-size: 12px; margin: 0 0 3px; }
        .header p { color: #718096; font-size: 8px; margin: 2px 0; }
        .meta { font-size: 8px; color: #4a5568; margin-bottom: 10px; }
        .meta strong { color: #2d3748; }
        .badge { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; padding: 1px 5px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background-color: #1B4332; color: white; padding: 5px 4px; text-align: left; font-size: 8px; }
        td { padding: 4px; border-bottom: 1px solid #e2e8f0; font-size: 8px; vertical-align: top; }
        tr:nth-child(even) td { background-color: #f0fff4; }
        .footer { text-align: center; margin-top: 18px; font-size: 7.5px; color: #a0aec0; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .total-row td { font-weight: bold; background-color: #c6f6d5 !important; font-size: 9px; }
        .empty { text-align: center; color: #a0aec0; padding: 24px; font-style: italic; }
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
        &nbsp;&nbsp;<span><strong>Total dossiers :</strong> {{ $records->count() }}</span>
        &nbsp;&nbsp;<span><strong>Total interpellés :</strong> {{ $records->sum('nombre_interpellation') }}</span>
    </div>

    @if($records->isEmpty())
        <p class="empty">Aucun dossier d'immigration trouvé pour les critères sélectionnés.</p>
    @else
    <table>
        <thead>
            <tr>
                <th>#</th><th>Date</th><th>Service</th><th>Total</th>
                <th>Hommes</th><th>Femmes</th><th>Enfants</th>
                <th>Sénégalais</th><th>Étrangers</th>
                <th>Zone départ</th><th>Zone arrivée prévue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $r)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $r->date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $r->service->nom ?? '-' }}</td>
                <td>{{ $r->nombre_interpellation ?? '-' }}</td>
                <td>{{ $r->nombre_hommes ?? 0 }}</td>
                <td>{{ $r->nombre_femmes ?? 0 }}</td>
                <td>{{ $r->nombre_enfants ?? 0 }}</td>
                <td>{{ $r->nombre_senegalais ?? 0 }}</td>
                <td>{{ $r->nombre_etrangers ?? 0 }}</td>
                <td>{{ $r->zone_depart ?? '-' }}</td>
                <td>{{ $r->zone_arrivee_prevue ?? '-' }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">Totaux</td>
                <td>{{ $records->sum('nombre_interpellation') }}</td>
                <td>{{ $records->sum('nombre_hommes') }}</td>
                <td>{{ $records->sum('nombre_femmes') }}</td>
                <td>{{ $records->sum('nombre_enfants') }}</td>
                <td>{{ $records->sum('nombre_senegalais') }}</td>
                <td>{{ $records->sum('nombre_etrangers') }}</td>
                <td colspan="2"></td>
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
