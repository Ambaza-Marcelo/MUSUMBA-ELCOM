<?php

namespace App\Exports\Accounting;

use App\Support\LedgerReportBuilder;
use App\Models\Accounting\AccountJournalEntryDetail;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LedgerExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    protected $dateDebut;
    protected $dateFin;
    protected $accountIds;
    /** @var array<int, string> */
    protected $pieceDocumentTokens;
    protected $parentAccountIds;
    protected $ledgerRows;

    /**
     * @param  array<int>|null  $accountIds
     * @param  array<int, string>  $pieceDocumentTokens
     * @param  array<int>|null  $parentAccountIds
     */
    public function __construct($dateDebut, $dateFin, $accountIds = null, array $pieceDocumentTokens = [], $parentAccountIds = null)
    {
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateFin;
        $this->accountIds = $accountIds;
        $this->pieceDocumentTokens = $pieceDocumentTokens;
        $this->parentAccountIds = $parentAccountIds;
        $this->prepareData();
    }

    protected function prepareData()
    {
        $query = AccountJournalEntryDetail::with('account')
            ->whereBetween('date', [$this->dateDebut, $this->dateFin]);

        LedgerReportBuilder::applyAccountFilter($query, $this->accountIds, $this->parentAccountIds);

        if (!empty($this->pieceDocumentTokens)) {
            $query->wherePieceOrDocumentIn($this->pieceDocumentTokens);
        }

        $datas = $query->orderBy('date')->orderBy('created_at')->orderBy('id')->get();
        $built = LedgerReportBuilder::buildRows($datas);
        $this->ledgerRows = collect($built['ledgerRows']);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->ledgerRows;
    }

    public function map($row): array
    {
        return [
            $row['account_label'],
            $row['created_at'],
            $row['operation_date'],
            $row['cj'],
            $row['document_no'],
            $row['label'],
            number_format($row['debit'], 0, ',', ' '),
            number_format($row['credit'], 0, ',', ' '),
            number_format($row['balance_debit'], 0, ',', ' '),
            number_format($row['balance_credit'], 0, ',', ' '),
        ];
    }

    public function headings(): array
    {
        return [
            __('messages.Compte'),
            __('messages.ledger_creation_date'),
            __('messages.ledger_operation_date'),
            __('messages.N° pièce'),
            __('messages.N° Document'),
            __('messages.Libellé écriture'),
            __('messages.MOUVEMENTS') . ' - ' . __('messages.DÉBIT'),
            __('messages.MOUVEMENTS') . ' - ' . __('messages.CRÉDIT'),
            __('messages.SOLDES') . ' - ' . __('messages.DÉBIT'),
            __('messages.SOLDES') . ' - ' . __('messages.CRÉDIT'),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 16,
            'C' => 14,
            'D' => 8,
            'E' => 15,
            'F' => 35,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    public function title(): string
    {
        return __('messages.Grand livre des comptes');
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rows = $this->ledgerRows;
                foreach ($rows as $index => $row) {
                    if (!empty($row['is_first_of_account'])) {
                        $excelRow = $index + 2;
                        $sheet->getStyle('A' . $excelRow . ':J' . $excelRow)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('E9F1FB');
                    }
                }
            },
        ];
    }
}
