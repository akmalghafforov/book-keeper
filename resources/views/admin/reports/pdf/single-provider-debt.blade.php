<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Debt Report') }}: {{ $provider->name }}</title>
    @include('admin.reports.pdf.styles')
</head>
<body>
    @php
        $reportDate = $report->report_generated_at ?? now();
        $summaryTotal = (float) ($provider->range_closing_balance ?? $provider->opening_balance_total ?? 0);
        $formatReportAmount = static function ($amount) {
            $amount = (float) $amount;

            return $amount == (int) $amount ? number_format($amount, 0) : number_format($amount, 2);
        };
    @endphp

    <div class="header">
        <h1>{{ __('Debt Report') }}:</h1>
        <p>{{ __('Date') }}: {{ $reportDate->format('M d, Y H:i') }}</p>
        <p>
            {{ __('Report Period') }}:
            @if($provider->range_end_date)
                {{ $provider->range_start_date->format('M d, Y') }} - {{ $provider->range_end_date->format('M d, Y') }}
            @else
                {{ __('From') }} {{ $provider->range_start_date->format('M d, Y') }}
            @endif
        </p>
        <p>{{ __('Serial Number') }}: {{ $report->formatted_serial_number }}</p>
    </div>

    <div class="client-info">
        <div style="font-size: 16px; font-weight: bold; margin-bottom: 5px;">{{ $provider->name }}</div>
        @if($provider->phone)
            <div>{{ __('Phone') }}: {{ $provider->phone }}</div>
        @endif
        <div style="margin-top: 10px; font-size: 16px;">
            <strong>{{ __('Selected Date Range Balance') }}:</strong>
            <span class="{{ $summaryTotal > 0 ? 'debt-positive' : 'debt-negative' }}">
                {{ $formatReportAmount($summaryTotal) }}
            </span>
        </div>
    </div>

    <h3>{{ __('Transaction History') }}</h3>
    <div class="report-table-wrapper">
        <table class="report-table ledger-table">
            <thead>
                <tr>
                    <th class="col-date">{{ __('Date') }}</th>
                    <th>{{ __('Details') }}</th>
                    <th class="text-right col-amount">{{ __('Amount') }}</th>
                    <th class="text-right col-balance">{{ __('Debt') }}</th>
                </tr>
            </thead>
            <tbody>
                @if(!empty($provider->has_opening_balance_transactions))
                    <tr class="font-bold" style="background-color: #f9f9f9;">
                        <td colspan="2" class="text-right">
                            {{ __('Total before selected date range') }}
                            <small class="date-meta">{{ __('Transactions') }}: {{ $provider->opening_balance_transactions_count }}</small>
                        </td>
                        <td class="text-right number-cell {{ $provider->opening_balance_total > 0 ? 'debt-positive' : 'debt-negative' }}">
                            {{ $formatReportAmount($provider->opening_balance_total) }}
                        </td>
                        <td class="text-right number-cell {{ $provider->opening_balance_total > 0 ? 'debt-positive' : 'debt-negative' }}">
                            {{ $formatReportAmount($provider->opening_balance_total) }}
                        </td>
                    </tr>
                @endif

                @forelse ($provider->recentLedgers as $ledger)
                    <tr>
                        <td class="date-cell">
                            {{ $ledger->transaction_date?->format('d/m') ?? $ledger->created_at->format('d/m') }}
                            @if($ledger->car_number)
                                <small class="date-meta">{{ $ledger->car_number }}</small>
                            @endif
                        </td>
                        <td class="details-cell">
                            @if($ledger->type === 'charge')
                                {{ $ledger->product?->name ?? __('charge') }}
                                @if($ledger->quantity !== null && $ledger->buy_price !== null)
                                    ({{ (float) $ledger->quantity == (int) $ledger->quantity ? number_format((float) $ledger->quantity, 0) : number_format((float) $ledger->quantity, 2) }} × {{ (float) $ledger->buy_price == (int) $ledger->buy_price ? number_format((float) $ledger->buy_price, 0) : number_format((float) $ledger->buy_price, 2) }})
                                @endif
                            @else
                                {{ __($ledger->type) }}@if($ledger->notes), {{ $ledger->notes }}@endif
                            @endif
                        </td>
                        <td class="text-right font-bold number-cell {{ $ledger->type === 'charge' ? 'debt-positive' : 'debt-negative' }}">
                            {{ $ledger->type === 'charge' ? '' : '-' }}{{ $formatReportAmount($ledger->amount) }}
                        </td>
                        <td class="text-right number-cell {{ $ledger->running_balance > 0 ? 'debt-positive' : 'debt-negative' }}">
                            {{ $formatReportAmount($ledger->running_balance) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">
                            {{ __('No transactions in the selected date range.') }}
                        </td>
                    </tr>
                @endforelse

                @if(!empty($provider->has_later_transactions))
                    <tr class="font-bold" style="background-color: #f9f9f9;">
                        <td colspan="2" class="text-right">
                            {{ __('Total after selected date range') }}
                            <small class="date-meta">{{ __('Transactions') }}: {{ $provider->later_transactions_count }}</small>
                        </td>
                        <td class="text-right number-cell {{ $provider->later_transactions_total > 0 ? 'debt-positive' : 'debt-negative' }}">
                            {{ $formatReportAmount($provider->later_transactions_total) }}
                        </td>
                        <td class="text-right number-cell {{ $summaryTotal > 0 ? 'debt-positive' : 'debt-negative' }}">
                            {{ $formatReportAmount($summaryTotal) }}
                        </td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="font-bold">
                    <td colspan="3" class="text-right">{{ __('Selected Date Range Balance') }}:</td>
                    <td class="text-right {{ $summaryTotal > 0 ? 'debt-positive' : 'debt-negative' }}">
                        {{ $formatReportAmount($summaryTotal) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="footer">
        {{ config('app.name') }} - {{ __('Generated on') }} {{ $reportDate->format('Y-m-d H:i:s') }}
    </div>
</body>
</html>
