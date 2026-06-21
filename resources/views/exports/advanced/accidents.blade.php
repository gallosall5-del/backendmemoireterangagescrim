<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a202c; }

        /* ── En-tête institutionnel ── */
        .page-header { border-bottom: 3px solid #1B4332; margin-bottom: 10px; padding-bottom: 8px; }
        .page-header table { width: 100%; border-collapse: collapse; }
        .page-header .logo-cell { width: 70px; text-align: center; vertical-align: middle; }
        .page-header .logo-emblem {
            width: 52px; height: 52px; border-radius: 50%;
            background: #1B4332; color: #fff;
            text-align: center; line-height: 52px;
            font-size: 22px; font-weight: bold; display: inline-block;
        }
        .page-header .title-cell { vertical-align: middle; padding-left: 10px; }
        .page-header .inst { font-size: 8px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; }
        .page-header h1 { font-size: 14px; font-weight: bold; color: #1B4332; margin: 3px 0 2px; }
        .page-header h2 { font-size: 11px; color: #2d3748; }
        .page-header .meta-right { text-align: right; vertical-align: top; font-size: 8px; color: #718096; }

        /* ── Bandeau méta ── */
        .meta-band {
            background: #ECFDF5; border: 1px solid #A7F3D0;
            padding: 5px 8px; margin-bottom: 10px;
            font-size: 8px; color: #065F46;
        }
        .meta-band table { width: 100%; border-collapse: collapse; }
        .meta-band td { padding: 0 8px 0 0; }
        .meta-band strong { color: #1B4332; }
        .badge { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; padding: 1px 6px; border-radius: 3px; font-weight: bold; }

        /* ── Cartes synthèse ── */
        .summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .summary td { text-align: center; padding: 6px 4px; border: 1px solid #A7F3D0; }
        .summary .num { font-size: 16px; font-weight: bold; color: #1B4332; display: block; }
        .summary .lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.3px; }
        .s-blue   { background: #EBF8FF; border-color: #90CDF4 !important; }
        .s-orange { background: #FFFAF0; border-color: #FBD38D !important; }
        .s-red    { background: #FFF5F5; border-color: #FEB2B2 !important; }
        .s-green  { background: #F0FFF4; border-color: #9AE6B4 !important; }
        .s-num-blue   { color: #2B6CB0 !important; }
        .s-num-orange { color: #C05621 !important; }
        .s-num-red    { color: #C53030 !important; }

        /* ── Tableau de données ── */
        .section-title {
            background: #1B4332; color: #fff;
            padding: 4px 8px; font-size: 10px; font-weight: bold;
            margin: 10px 0 4px; letter-spacing: 0.3px;
        }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.data thead th {
            background: #2D6A4F; color: #fff;
            padding: 5px 4px; text-align: left; font-size: 8px;
            border: 1px solid #1B4332;
        }
        table.data tbody td {
            padding: 4px; border: 1px solid #D1FAE5;
            font-size: 8px; vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #F0FFF4; }
        /* Ligne total — jaune comme ANASER */
        table.data tfoot td {
            background: #FFFF00; color: #1B4332;
            font-weight: bold; font-size: 9px;
            padding: 5px 4px; border: 1px solid #D9D9D9;
        }

        /* ── Pied de page ── */
        .footer {
            margin-top: 14px; border-top: 1px solid #D1FAE5;
            padding-top: 5px; text-align: center;
            font-size: 7.5px; color: #a0aec0;
        }
        .empty { text-align: center; color: #a0aec0; padding: 20px; font-style: italic; }
    </style>
</head>
<body>

    {{-- En-tête institutionnel --}}
    <div class="page-header">
        <table>
            <tr>
                <td class="logo-cell">
                    <div class="logo-emblem">DSP</div>
                </td>
                <td class="title-cell">
                    <div class="inst">République du Sénégal — Ministère de l'Intérieur</div>
                    <h1>TERANGA GESCRIM</h1>
                    <h2>{{ $titre }}</h2>
                </td>
                <td class="meta-right">
                    Généré le : {{ $date_generation }}<br>
                    Agent : <strong>{{ $agent }}</strong>
                </td>
            </tr>
        </table>
    </div>

    {{-- Bandeau méta --}}
    <div class="meta-band">
        <table>
            <tr>
                <td><strong>Période :</strong> <span class="badge">{{ $period_label }}</span></td>
                <td><strong>Total accidents :</strong> {{ $records->count() }}</td>
                <td><strong>Total victimes :</strong> {{ $records->sum(fn($a) => $a->victimes->count()) }}</td>
                <td><strong>Tués :</strong> {{ $records->sum(fn($a) => $a->victimes->where('etat_medical', 'décédé')->count()) }}</td>
            </tr>
        </table>
    </div>

    {{-- Cartes de synthèse par type --}}
    @php
        $corporels = $records->where('type', 'corporel')->count();
        $materiels = $records->where('type', 'matériel')->count();
        $mortels   = $records->where('type', 'mortel')->count();
        $autres    = $records->whereNotIn('type', ['corporel','matériel','mortel'])->count();
    @endphp
    <table class="summary">
        <tr>
            <td class="s-blue">
                <span class="num s-num-blue">{{ $corporels }}</span>
                <span class="lbl">Accidents corporels</span>
            </td>
            <td class="s-orange">
                <span class="num s-num-orange">{{ $materiels }}</span>
                <span class="lbl">Accidents matériels</span>
            </td>
            <td class="s-red">
                <span class="num s-num-red">{{ $mortels }}</span>
                <span class="lbl">Accidents mortels</span>
            </td>
            <td class="s-green">
                <span class="num">{{ $autres }}</span>
                <span class="lbl">Autres / Non classés</span>
            </td>
        </tr>
    </table>

    {{-- Tableau détaillé --}}
    <div class="section-title">DÉTAIL DES ACCIDENTS ({{ $records->count() }})</div>

    @if($records->isEmpty())
        <p class="empty">Aucun accident trouvé pour les critères sélectionnés.</p>
    @else
    <table class="data">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Date</th>
                <th width="6%">Heure</th>
                <th width="8%">Type</th>
                <th width="14%">Lieu</th>
                <th width="10%">Commune</th>
                <th width="13%">Service</th>
                <th width="10%">Moyen</th>
                <th width="15%">Cause probable</th>
                <th width="13%">Victimes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $a)
            <tr>
                <td style="text-align:center">{{ $index + 1 }}</td>
                <td>{{ $a->date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $a->heure ?? '-' }}</td>
                <td>{{ $a->type ?? '-' }}</td>
                <td>{{ $a->lieu ?? '-' }}</td>
                <td>{{ $a->commune->nom ?? '-' }}</td>
                <td>{{ $a->service->nom ?? '-' }}</td>
                <td>{{ $a->moyen ?? '-' }}</td>
                <td>{{ $a->cause_probable ?? '-' }}</td>
                <td style="text-align:center">{{ $a->victimes->count() }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" style="text-align:right">TOTAUX</td>
                <td style="text-align:center">{{ $records->sum(fn($a) => $a->victimes->count()) }}</td>
            </tr>
        </tfoot>
    </table>
    @endif

    <div class="footer">
        <p>Teranga GESCRIM — Rapport confidentiel — Direction de la Sécurité Publique — Sénégal</p>
        <p>Généré le {{ $date_generation }} — Accès réservé au personnel autorisé</p>
    </div>
</body>
</html>
