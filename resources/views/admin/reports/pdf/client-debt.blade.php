<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Client Debt Report') }}</title>
    @include('admin.reports.pdf.styles')
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

    <div class="report-table-wrapper">
        <table class="report-table">
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
    </div>

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
