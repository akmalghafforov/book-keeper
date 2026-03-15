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
        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">{{ $client->name }}</div>
        @if($client->phone)
            <div>{{ __('Phone') }}: {{ $client->phone }}</div>
        @endif
        <div style="margin-top: 10px; font-size: 16px;">
            <strong>{{ __('Current Total Debt') }}:</strong>
            <span class="{{ $client->calculated_total_debt > 0 ? 'debt-positive' : 'debt-negative' }}">
                {{ (float) $client->calculated_total_debt == (int) $client->calculated_total_debt ? number_format((float) $client->calculated_total_debt, 0) : number_format((float) $client->calculated_total_debt, 2) }}
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
            @if(!empty($client->has_older_transactions))
                <tr class="font-bold" style="background-color: #f9f9f9;">
                    <td colspan="3" class="text-right">{{ __('Aggregated total for transactions older than the latest 25') }}</td>
                    <td class="text-right {{ $client->older_transactions_total > 0 ? 'debt-positive' : 'debt-negative' }}">
                        {{ (float) $client->older_transactions_total == (int) $client->older_transactions_total ? number_format((float) $client->older_transactions_total, 0) : number_format((float) $client->older_transactions_total, 2) }}
                    </td>
                </tr>
            @endif

            @foreach ($client->recentLedgers as $ledger)
                <tr>
                    <td>{{ $ledger?->distribution?->distribution_date?->format('d/m/Y') ?? $ledger->created_at->format('d/m/Y') }}</td>
                    <td>{{ __($ledger->type) }}</td>
                    <td>
                        @if($ledger->distribution)
                            {{ $ledger->distribution->product->name ?? '' }}
                            ({{ (float) $ledger->distribution->quantity == (int) $ledger->distribution->quantity ? number_format((float) $ledger->distribution->quantity, 0) : number_format((float) $ledger->distribution->quantity, 2) }} {{ __($ledger->distribution->quantity_unit ?? '') }} × {{ (float) $ledger->distribution->price == (int) $ledger->distribution->price ? number_format((float) $ledger->distribution->price, 0) : number_format((float) $ledger->distribution->price, 2) }})
                            @if($ledger->distribution->supplier?->car_number)
                                , <small>{{ __('Car') }}: {{ $ledger->distribution->supplier->car_number }}</small>
                            @endif
                            @if($ledger->distribution->shop)
                                , <small>{{ __('Shop') }}: {{ $ledger->distribution->shop->name }}</small>
                            @endif
                            @if($ledger->type === 'credit_note' && $ledger->distribution->client && $ledger->distribution->client_id !== $client->id)
                                , <small>{{ __('Client') }}: {{ $ledger->distribution->client->name }}</small>
                            @endif
                        @else
                            {{ $ledger->notes }}
                        @endif
                    </td>
                    <td class="text-right font-bold {{ in_array($ledger->type, ['charge']) ? 'debt-positive' : 'debt-negative' }}">
                        {{ in_array($ledger->type, ['charge']) ? '' : '-' }}{{ (float) $ledger->amount == (int) $ledger->amount ? number_format((float) $ledger->amount, 0) : number_format((float) $ledger->amount, 2) }}
                    </td>
                </tr>
            @endforeach

        </tbody>
        <tfoot>
            <tr class="font-bold">
                <td colspan="3" class="text-right">{{ __('Final Balance') }}:</td>
                <td class="text-right {{ $client->calculated_total_debt > 0 ? 'debt-positive' : 'debt-negative' }}">
                    {{ (float) $client->calculated_total_debt == (int) $client->calculated_total_debt ? number_format((float) $client->calculated_total_debt, 0) : number_format((float) $client->calculated_total_debt, 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        {{ config('app.name') }} - {{ __('Generated on') }} {{ now()->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
