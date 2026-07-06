<?php

namespace App\Exports\Accounting;

use App\Support\BalanceReportBuilder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BalanceExport implements FromCollection, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    /** @var array<int|string>|null */
    protected $parentAccountIds;

    /** @var array<int|string>|null */
    protected $accountIds;

    protected ?string $fiscalYear;

    protected $accountsData;

    protected $grandTotals;

    protected $reportRow;

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     */
    public function __construct(?array $parentAccountIds = null, ?string $fiscalYear = null, ?array $accountIds = null)
    {
        $this->parentAccountIds = $parentAccountIds;
        $this->accountIds = $accountIds;
        $this->fiscalYear = $fiscalYear;

        $report = BalanceReportBuilder::build($parentAccountIds, $fiscalYear, $accountIds);
        $this->accountsData = $report['accountsData'];
        $this->grandTotals = $report['grandTotals'];
        $this->reportRow = $report['reportRow'];
    }

    public function collection()
    {
        $rows = collect();

        if (BalanceReportBuilder::hasAmounts($this->reportRow)) {
            $rows->push([
                'type' => 'report',
                'account_number' => '',
                'account_name' => __('messages.accounts_balance_report'),
                'movement_debit' => $this->reportRow['movement_debit'],
                'movement_credit' => $this->reportRow['movement_credit'],
                'balance_debit' => $this->reportRow['balance_debit'],
                'balance_credit' => $this->reportRow['balance_credit'],
            ]);
        }

        foreach ($this->accountsData as $row) {
            $rows->push(array_merge(['type' => 'account'], $row));
        }

        $rows->push([
            'type' => 'carry_forward',
            'account_number' => '',
            'account_name' => __('messages.accounts_balance_carry_forward'),
            'movement_debit' => $this->grandTotals['movement_debit'],
            'movement_credit' => $this->grandTotals['movement_credit'],
            'balance_debit' => $this->grandTotals['balance_debit'],
            'balance_credit' => $this->grandTotals['balance_credit'],
        ]);

        return $rows;
    }

    public function map($row): array
    {
        return [
            $row['account_number'] ?? '',
            $row['account_name'] ?? '',
            (float) ($row['movement_debit'] ?? 0),
            (float) ($row['movement_credit'] ?? 0),
            (float) ($row['balance_debit'] ?? 0),
            (float) ($row['balance_credit'] ?? 0),
        ];
    }

    public function headings(): array
    {
        return [
            __('messages.accounts_balance_account_number'),
            __('messages.accounts_balance_account_name'),
            __('messages.accounts_balance_movements') . ' - ' . __('messages.accounts_balance_debit'),
            __('messages.accounts_balance_movements') . ' - ' . __('messages.accounts_balance_credit'),
            __('messages.accounts_balance_balances') . ' - ' . __('messages.accounts_balance_debit'),
            __('messages.accounts_balance_balances') . ' - ' . __('messages.accounts_balance_credit'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 40,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $reportOffset = BalanceReportBuilder::hasAmounts($this->reportRow) ? 1 : 0;
        $lastRow = 1 + $reportOffset + $this->accountsData->count() + 1;

        $styles = [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EEF2F7'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EEF2F7'],
                ],
            ],
        ];

        if ($reportOffset) {
            $styles[2] = [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F5F7FA'],
                ],
            ];
        }

        return $styles;
    }

    public function title(): string
    {
        return __('messages.accounts_balance_title');
    }
}
