<?php

namespace App\Exports\Accounting;

use App\Support\BalanceReportBuilder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalanceExport implements FromArray, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    /** @var array<int, array<int, string|float|int>> */
    protected $rows = [];

    /** @var int */
    protected $lastDataRow = 1;

    /**
     * @param  array<int|string>|null  $parentAccountIds
     * @param  array<int|string>|null  $accountIds
     */
    public function __construct(
        $parentAccountIds = null,
        $fiscalYear = null,
        $accountIds = null,
        $dateDebut = null,
        $dateFin = null,
        $consolidateByParent = null
    ) {
        $report = BalanceReportBuilder::build(
            $parentAccountIds,
            $fiscalYear,
            $accountIds,
            $dateDebut,
            $dateFin,
            $consolidateByParent
        );

        $this->rows[] = [
            __('messages.accounts_balance_account_number'),
            __('messages.accounts_balance_account_name'),
            __('messages.accounts_balance_movements') . ' - ' . __('messages.accounts_balance_debit'),
            __('messages.accounts_balance_movements') . ' - ' . __('messages.accounts_balance_credit'),
            __('messages.accounts_balance_balances') . ' - ' . __('messages.accounts_balance_debit'),
            __('messages.accounts_balance_balances') . ' - ' . __('messages.accounts_balance_credit'),
        ];

        foreach ($report['accountsData'] as $row) {
            $this->rows[] = [
                (string) ($row['account_number'] ?? ''),
                (string) ($row['account_name'] ?? ''),
                (float) ($row['movement_debit'] ?? 0),
                (float) ($row['movement_credit'] ?? 0),
                (float) ($row['balance_debit'] ?? 0),
                (float) ($row['balance_credit'] ?? 0),
            ];
        }

        $this->rows[] = [
            '',
            __('messages.accounts_balance_carry_forward'),
            (float) $report['grandTotals']['movement_debit'],
            (float) $report['grandTotals']['movement_credit'],
            (float) $report['grandTotals']['balance_debit'],
            (float) $report['grandTotals']['balance_credit'],
        ];

        $this->lastDataRow = count($this->rows);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function columnFormats(): array
    {
        return [
            'C' => '#,##0',
            'D' => '#,##0',
            'E' => '#,##0',
            'F' => '#,##0',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:F1')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(1)->setRowHeight(30);

        return [
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
            $this->lastDataRow => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'EEF2F7'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        $title = (string) __('messages.accounts_balance_title');
        $title = str_replace(['\\', '/', '?', '*', '[', ']', ':'], '', $title);
        $title = trim($title);

        if ($title === '') {
            $title = 'Balance';
        }

        return function_exists('mb_substr') ? mb_substr($title, 0, 31) : substr($title, 0, 31);
    }
}
