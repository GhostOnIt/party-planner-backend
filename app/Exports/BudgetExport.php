<?php

namespace App\Exports;

use App\Models\Event;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BudgetExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected Event $event
    ) {}

    public function collection(): Collection
    {
        return $this->event->budgetItems()->orderBy('category')->get();
    }

    public function headings(): array
    {
        return [
            'Catégorie',
            'Nom',
            'Coût estimé (FCFA)',
            'Coût réel (FCFA)',
            'Payé',
            'Date de paiement',
            'Notes',
        ];
    }

    public function map($item): array
    {
        return [
            $this->getCategoryLabel($item->category),
            $item->name,
            number_format($item->estimated_cost, 0, ',', ' '),
            number_format($item->actual_cost ?? 0, 0, ',', ' '),
            $item->paid ? 'Oui' : 'Non',
            $item->payment_date?->format('d/m/Y') ?? '',
            $item->notes ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1F2937'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return 'Budget';
    }

    protected function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'location' => 'Lieu',
            'catering' => 'Traiteur',
            'decoration' => 'Décoration',
            'entertainment' => 'Animation',
            'photography' => 'Photographie',
            'transportation' => 'Transport',
            'other' => 'Autre',
            default => $category,
        };
    }
}
