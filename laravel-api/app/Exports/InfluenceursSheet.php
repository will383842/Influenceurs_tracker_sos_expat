<?php

namespace App\Exports;

use App\Models\Influenceur;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class InfluenceursSheet implements FromQuery, WithHeadings, WithTitle, WithMapping
{
    public function title(): string
    {
        return 'Influenceurs';
    }

    public function query()
    {
        return Influenceur::with(['assignedToUser:id,name']);
    }

    public function headings(): array
    {
        return [
            'ID', 'Type', 'Nom', 'Entreprise', 'Handle', 'Plateforme', 'Followers',
            'Statut', 'Score', 'Pays', 'Langue', 'Email', 'Téléphone', 'Niche',
            'Deal (€)', 'Probabilité', 'Source',
            'Assigné à', 'Dernier contact', 'Date partenariat', 'Notes',
        ];
    }

    public function map($inf): array
    {
        return [
            $inf->id,
            $inf->contact_type instanceof \App\Enums\ContactType ? $inf->contact_type->label() : $inf->contact_type,
            $inf->name,
            $inf->company,
            $inf->handle,
            $inf->primary_platform,
            $inf->followers,
            $inf->status,
            $inf->score,
            $inf->country,
            $inf->language,
            $inf->email,
            $inf->phone,
            $inf->niche,
            $inf->deal_value_cents ? number_format($inf->deal_value_cents / 100, 2) : '',
            $inf->deal_probability ? $inf->deal_probability . '%' : '',
            $inf->source,
            $inf->assignedToUser?->name,
            $inf->last_contact_at?->toDateString(),
            $inf->partnership_date?->toDateString(),
            $inf->notes,
        ];
    }
}
