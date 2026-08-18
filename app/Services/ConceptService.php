<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ConceptService
{
    public function __construct() {}

    public function getConceptsMap(string $templateCode): array
    {
        return DB::connection('mrp')->table('concepts')
            ->where('template_code', $templateCode)
            ->orderBy('sort_order')
            ->get([
                'concept_code',
                'concept_description',
                'um',
                'sort_order',
            ])
            ->mapWithKeys(fn ($concept) => [
                $concept->concept_code => [
                    'description' => $concept->concept_description,
                    'unit' => trim($concept->um),
                    'order' => (int) $concept->sort_order,
                ],
            ])
            ->toArray();
    }
}
