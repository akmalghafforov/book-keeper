<style>
    @page {
        margin: 10px;
    }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 14px;
        font-weight: bold;
        color: #333;
        margin: 0;
        padding: 0;
    }
    .header {
        text-align: center;
        margin-bottom: 10px;
    }
    .header h1 {
        margin: 0;
        font-size: 20px;
    }
    .header p {
        margin: 2px 0 0;
        color: #666;
    }
    .client-info {
        margin-bottom: 10px;
        padding: 5px;
        background-color: #f9f9f9;
        border: 1px solid #eee;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
    }
    .report-table-wrapper,
    .report-table,
    .report-table thead,
    .report-table tbody,
    .report-table tfoot,
    .report-table tr,
    .report-table td,
    .report-table th {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    .ledger-table {
        table-layout: fixed;
    }
    th, td {
        border: 1px solid #ddd;
        padding: 6px;
        text-align: left;
        vertical-align: top;
    }
    th {
        background-color: #f5f5f5;
        font-weight: bold;
    }
    .ledger-table .col-date {
        width: 58px;
    }
    .ledger-table .col-amount,
    .ledger-table .col-balance {
        width: 82px;
    }
    .ledger-table .date-cell {
        white-space: nowrap;
        font-size: 12px;
        line-height: 1.2;
    }
    .ledger-table .date-meta {
        display: block;
        margin-top: 2px;
        font-size: 10px;
        font-weight: normal;
        color: #666;
    }
    .ledger-table .details-cell {
        word-break: break-word;
    }
    .ledger-table .number-cell {
        white-space: nowrap;
        font-size: 12px;
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
        margin-top: 10px;
        text-align: right;
        font-size: 12px;
        color: #999;
    }
    .summary {
        margin-bottom: 10px;
    }
    .summary-item {
        margin-bottom: 3px;
        font-size: 16px;
    }
    tfoot tr.font-bold td {
        font-size: 16px;
    }
</style>
