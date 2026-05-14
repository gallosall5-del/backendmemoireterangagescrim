<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $titre }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { text-align: center; color: #1a365d; font-size: 16px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header p { color: #666; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background-color: #742a2a; color: white; padding: 6px; text-align: left; font-size: 9px; }
        td { padding: 5px; border-bottom: 1px solid #ddd; font-size: 9px; }
        tr:nth-child(even) { background-color: #fff5f5; }
        .footer { text-align: center; margin-top: 20px; font-size: 8px; color: #999; }
        .total { font-weight: bold; background-color: #fed7d7; padding: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>TERANGA GESCRIM</h1>
        <p>Direction de la Sécurité Publique</p>
        <h2>{{ $titre }}</h2>
        <p>Généré le : {{ $date_generation }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Lieu</th>
                <th>Commune</th>
                <th>Service</th>
                <th>Moyen</th>
                <th>Cause</th>
                <th>Victimes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($accidents as $index => $accident)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $accident->date->format('d/m/Y') }}</td>
                <td>{{ $accident->type }}</td>
                <td>{{ $accident->lieu }}</td>
                <td>{{ $accident->commune->nom ?? '-' }}</td>
                <td>{{ $accident->service->nom ?? '-' }}</td>
                <td>{{ $accident->moyen ?? '-' }}</td>
                <td>{{ $accident->cause_probable ?? '-' }}</td>
                <td>{{ $accident->victimes->count() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="total">Total : {{ $accidents->count() }} accidents</p>

    <div class="footer">
        <p>Teranga GESCRIM - Rapport confidentiel - {{ $date_generation }}</p>
    </div>
</body>
</html>
