<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Client Debt Report') }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .font-bold {
            font-weight: bold;
        }
        .debt-positive {
            color: #dc2626;
        }
        .debt-negative {
            color: #16a34a;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10px;
            color: #999;
        }
        .summary {
            margin-bottom: 20px;
        }
        .summary-item {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Client Debt Report') }}</h1>
        <p>{{ __('Date') }}: {{ now()->format('M d, Y H:i') }}</p>
    </div>

    <div class="summary">
        <div class="summary-item">
            <strong>{{ __('Total Clients with Debt') }}:</strong> {{ $clients->count() }}
        </div>
        <div class="summary-item">
            <strong>{{ __('Total Debt') }}:</strong> {{ number_format($clients->sum('calculated_total_debt'), 2) }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('Client') }}</th>
                <th class="text-right">{{ __('Current Debt') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($clients as $client)
                <tr>
                    <td>{{ $client->name }}</td>
                    <td class="text-right font-bold {{ $client->calculated_total_debt > 0 ? 'debt-positive' : 'debt-negative' }}">
                        {{ number_format((float) $client->calculated_total_debt, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold">
                <td class="text-right">{{ __('Total') }}:</td>
                <td class="text-right {{ $clients->sum('calculated_total_debt') > 0 ? 'debt-positive' : 'debt-negative' }}">
                    {{ number_format((float) $clients->sum('calculated_total_debt'), 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <div class="summary-item">
            <strong>{{ __('Total Clients with Debt') }}:</strong> {{ $clients->count() }}
        </div>
        <div class="summary-item">
            <strong>{{ __('Total Debt') }}:</strong> {{ number_format((float) $clients->sum('calculated_total_debt'), 2) }}
        </div>
    </div>

    <div class="footer">
        {{ config('app.name') }} - {{ __('Generated on') }} {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
