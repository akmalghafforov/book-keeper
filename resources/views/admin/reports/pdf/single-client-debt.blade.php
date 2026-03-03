<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Client Debt Report') }} - {{ $client->name }}</title>
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
        .client-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #eee;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('Client Debt Report') }}</h1>
        <p>{{ __('Date') }}: {{ now()->format('M d, Y H:i') }}</p>
    </div>

    <div class="client-info">
        <div style="font-size: 14px; font-weight: bold; margin-bottom: 5px;">{{ $client->name }}</div>
        @if($client->phone)
            <div>{{ __('Phone') }}: {{ $client->phone }}</div>
        @endif
        <div style="margin-top: 10px;">
            <strong>{{ __('Current Total Debt') }}:</strong> 
            <span class="{{ $client->calculated_total_debt > 0 ? 'debt-positive' : 'debt-negative' }}">
                {{ number_format((float) $client->calculated_total_debt, 2) }}
            </span>
        </div>
    </div>

    <h3>{{ __('Transaction History') }}</h3>
    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('Details') }}</th>
                <th class="text-right">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($client->debtLedgers as $ledger)
                <tr>
                    <td>{{ $ledger->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ __($ledger->type) }}</td>
                    <td>
                        @if($ledger->type === 'charge' && $ledger->distribution)
                            {{ $ledger->distribution->product->name }} ({{ $ledger->distribution->quantity }} x {{ number_format((float) $ledger->distribution->price, 2) }})
                        @endif
                    </td>
                    <td class="text-right font-bold {{ in_array($ledger->type, ['charge']) ? 'debt-positive' : 'debt-negative' }}">
                        {{ in_array($ledger->type, ['charge']) ? '' : '-' }}{{ number_format((float) $ledger->amount, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-bold">
                <td colspan="3" class="text-right">{{ __('Final Balance') }}:</td>
                <td class="text-right {{ $client->calculated_total_debt > 0 ? 'debt-positive' : 'debt-negative' }}">
                    {{ number_format((float) $client->calculated_total_debt, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        {{ config('app.name') }} - {{ __('Generated on') }} {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
