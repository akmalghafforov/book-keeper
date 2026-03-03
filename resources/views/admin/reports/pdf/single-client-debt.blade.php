<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Client Debt Report') }} - {{ $client->name }}</title>
    @include('admin.reports.pdf.styles')
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
                            @if($ledger->distribution->supplier?->car_number)
                                <br><small>{{ __('Car') }}: {{ $ledger->distribution->supplier->car_number }}</small>
                            @endif
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
