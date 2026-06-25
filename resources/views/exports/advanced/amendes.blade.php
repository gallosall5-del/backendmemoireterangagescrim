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

        .summary { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
        .summary td { text-align: center; padding: 6px 4px; background: #D9D9D9; border: 1px solid #CCCCCC; }
        .summary .num { font-size: 16px; font-weight: bold; color: #1B4332; display: block; }
        .summary .lbl { font-size: 7px; color: #4a5568; text-transform: uppercase; }

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
        table.data tbody tr:nth-child(odd) td  { background: #FFFF00; }
        table.data tbody tr:nth-child(even) td { background: #FFFFFF; }
        table.data tfoot td {
            background: #1B4332; color: #fff;
            padding: 5px 4px; font-weight: bold; font-size: 8px;
            border: 1px solid #1B4332;
        }

        .footer { position: fixed; bottom: 0; width: 100%; border-top: 2px solid #1B4332; padding-top: 4px; font-size: 7px; color: #718096; }
        .footer table { width: 100%; border-collapse: collapse; }
    </style>
</head>
<body>
    <div class="page-header">
        <table>
            <tr>
                <td class="logo-cell"><div class="logo-emblem">DSP</div></td>
                <td class="title-cell">
                    <div class="inst">République du Sénégal — Direction de la Sûreté Publique</div>
                    <h1>{{ $titre }}</h1>
                    <h2>Période : {{ $period_label }}</h2>
                </td>
                <td class="meta-right">
                    Généré le {{ $date_generation }}<br>
                    Par : {{ $agent }}<br>
                    Système Teranga GESCRIM
                </td>
            </tr>
        </table>
    </div>

    <div class="meta-band">
        <table>
            <tr>
                <td><strong>Total enregistrements :</strong> {{ $records->count() }}</td>
                <td><strong>Montant total :</strong> {{ number_format($records->sum('montant'), 0, ',', ' ') }} FCFA</td>
                <td><strong>Amendes :</strong> {{ $records->where('type', 'Amende')->count() }}</td>
                <td><strong>Pièces saisies :</strong> {{ $records->where('type', '!=', 'Amende')->count() }}</td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td><span class="num">{{ $records->count() }}</span><span class="lbl">Total registres</span></td>
            <td><span class="num">{{ number_format($records->sum('montant'), 0, ',', ' ') }}</span><span class="lbl">Montant FCFA</span></td>
            <td><span class="num">{{ $records->where('type', 'Amende')->count() }}</span><span class="lbl">Amendes</span></td>
            <td><span class="num">{{ $records->where('type', '!=', 'Amende')->count() }}</span><span class="lbl">Saisies</span></td>
        </tr>
    </table>

    <div class="section-title">Détail des enregistrements</div>
    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Service</th>
                <th>Montant (FCFA)</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $i => $r)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $r->date?->format('d/m/Y') ?? '-' }}</td>
                <td>{{ $r->type ?? '-' }}</td>
                <td>{{ $r->service->nom ?? '-' }}</td>
                <td style="text-align: right;">{{ number_format($r->montant ?? 0, 0, ',', ' ') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($r->description ?? '-', 40) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right;">TOTAL</td>
                <td style="text-align: right;">{{ number_format($records->sum('montant'), 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        <table>
            <tr>
                <td>Teranga GESCRIM — Système National de Gestion de la Criminalité</td>
                <td style="text-align: right;">Document généré automatiquement — {{ $date_generation }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
