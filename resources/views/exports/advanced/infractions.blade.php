<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a202c; }

        .page-header { border-bottom: 3px solid #1a365d; margin-bottom: 10px; padding-bottom: 8px; }
        .page-header table { width: 100%; border-collapse: collapse; }
        .page-header .logo-cell { width: 70px; text-align: center; vertical-align: middle; }
        .page-header .logo-emblem {
            width: 52px; height: 52px; border-radius: 50%;
            background: #1a365d; color: #fff;
            text-align: center; line-height: 52px;
            font-size: 22px; font-weight: bold; display: inline-block;
        }
        .page-header .title-cell { vertical-align: middle; padding-left: 10px; }
        .page-header .inst { font-size: 8px; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; }
        .page-header h1 { font-size: 14px; font-weight: bold; color: #1a365d; margin: 3px 0 2px; }
        .page-header h2 { font-size: 11px; color: #2d3748; }
        .page-header .meta-right { text-align: right; vertical-align: top; font-size: 8px; color: #718096; }

        .meta-band {
            background: #EBF8FF; border: 1px solid #90CDF4;
            padding: 5px 8px; margin-bottom: 10px;
            font-size: 8px; color: #2B6CB0;
        }
        .meta-band table { width: 100%; border-collapse: collapse; }
        .meta-band td { padding: 0 8px 0 0; }
        .meta-band strong { color: #1a365d; }
        .badge { background: #BEE3F8; color: #2B6CB0; border: 1px solid #90CDF4; padding: 1px 6px; border-radius: 3px; font-weight: bold; }

        /* Cartes synthèse par catégorie — fond gris D9D9D9 comme ANASER */
        .summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .summary td {
            text-align: center; padding: 5px 3px;
            background: #D9D9D9; border: 1px solid #CCCCCC;
        }
        .summary .num { font-size: 15px; font-weight: bold; color: #1a365d; display: block; }
        .summary .lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; }

        .section-title {
            background: #1a365d; color: #fff;
            padding: 4px 8px; font-size: 10px; font-weight: bold;
            margin: 10px 0 4px;
        }

        /* Labels de catégorie — gris D9D9D9 (référence ANASER) */
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.data thead th {
            background: #1a365d; color: #fff;
            padding: 5px 4px; text-align: left; font-size: 8px;
            border: 1px solid #1a365d;
        }
        table.data tbody td {
            padding: 4px; border: 1px solid #E2E8F0;
            font-size: 8px; vertical-align: top;
        }
        table.data tbody tr:nth-child(even) td { background: #EBF8FF; }
        /* Ligne catégorie — gris comme ANASER */
        table.data tbody tr.cat-row td {
            background: #D9D9D9; font-weight: bold; font-size: 8.5px;
            color: #1a202c; border: 1px solid #CCCCCC;
        }
        /* Ligne total — jaune ANASER */
        table.data tfoot td {
            background: #FFFF00; color: #1a365d;
            font-weight: bold; font-size: 9px;
            padding: 5px 4px; border: 1px solid #D9D9D9;
        }

        .footer {
            margin-top: 14px; border-top: 1px solid #BEE3F8;
            padding-top: 5px; text-align: center;
            font-size: 7.5px; color: #a0aec0;
        }
        .empty { text-align: center; color: #a0aec0; padding: 20px; font-style: italic; }
    </style>
</head>
<body>

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

    <div class="meta-band">
        <table>
            <tr>
                <td><strong>Période :</strong> <span class="badge">{{ $period_label }}</span></td>
                <td><strong>Total infractions :</strong> {{ $records->count() }}</td>
                <td><strong>Déférés :</strong> {{ $records->where('issue', 'like', '%déféré%')->count() }}</td>
                <td><strong>Libérés :</strong> {{ $records->where('issue', 'like', '%libéré%')->count() }}</td>
            </tr>
        </table>
    </div>

    {{-- Synthèse par catégorie --}}
    @php
        $byCategorie = $records->groupBy(fn($inf) => $inf->typeInfraction?->categorieInfraction?->nom ?? 'Non classé');
    @endphp
    <table class="summary">
        <tr>
            @foreach($byCategorie as $catNom => $catRecords)
            <td>
                <span class="num">{{ $catRecords->count() }}</span>
                <span class="lbl">{{ \Illuminate\Support\Str::limit($catNom, 22) }}</span>
            </td>
            @endforeach
        </tr>
    </table>

    <div class="section-title">DÉTAIL DES INFRACTIONS ({{ $records->count() }})</div>

    @if($records->isEmpty())
        <p class="empty">Aucune infraction trouvée pour les critères sélectionnés.</p>
    @else
    <table class="data">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Date</th>
                <th width="6%">Heure</th>
                <th width="14%">Lieu</th>
                <th width="9%">Commune</th>
                <th width="13%">Service</th>
                <th width="12%">Type</th>
                <th width="12%">Catégorie</th>
                <th width="8%">Issue</th>
                <th width="15%">Description</th>
            </tr>
        </thead>
        <tbody>
            @php $prevCat = null; @endphp
            @foreach($records as $index => $inf)
                @php $cat = $inf->typeInfraction?->categorieInfraction?->nom ?? 'Non classé'; @endphp
                @if($cat !== $prevCat)
                    <tr class="cat-row">
                        <td colspan="10">▶ {{ strtoupper($cat) }}</td>
                    </tr>
                    @php $prevCat = $cat; @endphp
                @endif
                <tr>
                    <td style="text-align:center">{{ $index + 1 }}</td>
                    <td>{{ $inf->date?->format('d/m/Y') ?? ($inf->annee ?? '-') }}</td>
                    <td>{{ $inf->heure ?? '-' }}</td>
                    <td>{{ $inf->lieu ?? '-' }}</td>
                    <td>{{ $inf->commune->nom ?? '-' }}</td>
                    <td>{{ $inf->service->nom ?? '-' }}</td>
                    <td>{{ $inf->typeInfraction->nom ?? '-' }}</td>
                    <td>{{ $cat }}</td>
                    <td>{{ $inf->issue ?? '-' }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($inf->description ?? '-', 50) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="9" style="text-align:right">TOTAUX</td>
                <td style="text-align:center">{{ $records->count() }}</td>
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
