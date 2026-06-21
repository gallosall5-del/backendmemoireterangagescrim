<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a202c; }

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

        .meta-band {
            background: #ECFDF5; border: 1px solid #A7F3D0;
            padding: 5px 8px; margin-bottom: 10px;
            font-size: 8px; color: #065F46;
        }
        .meta-band table { width: 100%; border-collapse: collapse; }
        .meta-band td { padding: 0 8px 0 0; }
        .meta-band strong { color: #1B4332; }
        .badge { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; padding: 1px 6px; border-radius: 3px; font-weight: bold; }

        /* Cartes synthèse — fond gris D9D9D9 comme ANASER */
        .summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .summary td { text-align: center; padding: 6px 4px; background: #D9D9D9; border: 1px solid #CCCCCC; }
        .summary .num { font-size: 16px; font-weight: bold; color: #1B4332; display: block; }
        .summary .lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; }

        /* Tableau répartition par nationalité — style ANASER */
        .section-title {
            background: #1B4332; color: #fff;
            padding: 4px 8px; font-size: 10px; font-weight: bold;
            margin: 10px 0 4px;
        }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.data thead th {
            background: #2D6A4F; color: #fff;
            padding: 5px 4px; text-align: left; font-size: 8px;
            border: 1px solid #1B4332;
        }
        table.data tbody td {
            padding: 4px; border: 1px solid #D1FAE5;
            font-size: 8px; vertical-align: middle;
        }
        /* Lignes alternées : jaune/blanc comme ANASER */
        table.data tbody tr:nth-child(odd) td  { background: #FFFF00; }
        table.data tbody tr:nth-child(even) td { background: #FFFFFF; }
        /* Ligne total */
        table.data tfoot td {
            background: #D9D9D9; color: #1B4332;
            font-weight: bold; font-size: 9px;
            padding: 5px 4px; border: 1px solid #CCCCCC;
        }

        .footer {
            margin-top: 14px; border-top: 1px solid #D1FAE5;
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
                <td><strong>Dossiers :</strong> {{ $records->count() }}</td>
                <td><strong>Total interpellés :</strong> {{ $records->sum('nombre_interpellation') }}</td>
                <td><strong>Sénégalais :</strong> {{ $records->sum('nombre_senegalais') }}</td>
                <td><strong>Étrangers :</strong> {{ $records->sum('nombre_etrangers') }}</td>
            </tr>
        </table>
    </div>

    {{-- Synthèse globale — style ANASER : gris / données en jaune --}}
    <table class="summary">
        <tr>
            <td>
                <span class="num">{{ $records->sum('nombre_interpellation') }}</span>
                <span class="lbl">Total interpellés</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_hommes') }}</span>
                <span class="lbl">Hommes</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_femmes') }}</span>
                <span class="lbl">Femmes</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_enfants') }}</span>
                <span class="lbl">Enfants</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_maries') }}</span>
                <span class="lbl">Mariés</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_celibataires') }}</span>
                <span class="lbl">Célibataires</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_senegalais') }}</span>
                <span class="lbl">Sénégalais</span>
            </td>
            <td>
                <span class="num">{{ $records->sum('nombre_etrangers') }}</span>
                <span class="lbl">Étrangers</span>
            </td>
        </tr>
    </table>

    <div class="section-title">RÉPARTITION DES INTERPELLATIONS ({{ $records->count() }} dossiers)</div>

    @if($records->isEmpty())
        <p class="empty">Aucun dossier d'immigration trouvé pour les critères sélectionnés.</p>
    @else
    <table class="data">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Date</th>
                <th width="13%">Service</th>
                <th width="6%">Total</th>
                <th width="6%">Hommes</th>
                <th width="6%">Femmes</th>
                <th width="6%">Enfants</th>
                <th width="7%">Mariés</th>
                <th width="7%">Célib.</th>
                <th width="6%">Sénég.</th>
                <th width="6%">Étrang.</th>
                <th width="13%">Zone départ</th>
                <th width="13%">Zone arrivée prévue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $r)
            <tr>
                <td style="text-align:center">{{ $index + 1 }}</td>
                <td>{{ $r->date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $r->service->nom ?? '-' }}</td>
                <td style="text-align:center;font-weight:bold">{{ $r->nombre_interpellation ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_hommes ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_femmes ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_enfants ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_maries ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_celibataires ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_senegalais ?? 0 }}</td>
                <td style="text-align:center">{{ $r->nombre_etrangers ?? 0 }}</td>
                <td>{{ $r->zone_depart ?? '-' }}</td>
                <td>{{ $r->zone_arrivee_prevue ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right">TOTAUX</td>
                <td style="text-align:center">{{ $records->sum('nombre_interpellation') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_hommes') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_femmes') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_enfants') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_maries') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_celibataires') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_senegalais') }}</td>
                <td style="text-align:center">{{ $records->sum('nombre_etrangers') }}</td>
                <td colspan="2"></td>
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
