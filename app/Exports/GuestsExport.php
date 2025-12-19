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

class GuestsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    public function __construct(
        protected Event $event
    ) {}

    public function collection(): Collection
    {
        return $this->event->guests()->orderBy('name')->get();
    }

    public function headings(): array
    {
        return [
            'Nom',
            'Email',
            'Téléphone',
            'Statut RSVP',
            'Notes',
            'Check-in',
            'Invitation envoyée',
            'Date de réponse',
        ];
    }

    public function map($guest): array
    {
        return [
            $guest->name,
            $guest->email ?? '',
            $guest->phone ?? '',
            $this->getRsvpLabel($guest->rsvp_status),
            $guest->notes ?? '',
            $guest->checked_in ? 'Oui' : 'Non',
            $guest->invitation_sent_at?->format('d/m/Y H:i') ?? '',
            $guest->responded_at?->format('d/m/Y H:i') ?? '',
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
        return 'Invités';
    }

    protected function getRsvpLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'En attente',
            'accepted' => 'Accepté',
            'declined' => 'Décliné',
            'maybe' => 'Peut-être',
            default => $status,
        };
    }
}
