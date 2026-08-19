<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Filtros compartidos para listados de clientes (índice y pestaña agente).
 */
class ClientListFilterService
{
    /**
     * @param  Builder<\App\Models\Client>|Relation<\App\Models\Client>  $query
     */
    public function apply(Request $request, Builder|Relation $query): void
    {
        if ($request->filled('date')) {
            $date = $request->date;
            $query->where(function ($q) use ($date) {
                $q->whereHas('loadDates', fn ($q2) => $q2->whereDate('loaded_at', $date))
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereDoesntHave('loadDates')
                            ->where(fn ($q3) => $q3->whereDate('loaded_at', $date)
                                ->orWhere(fn ($q4) => $q4->whereNull('loaded_at')->whereDate('created_at', $date)));
                    });
            });
        }

        if ($request->filled('search')) {
            $s = trim((string) $request->search);
            if ($s !== '') {
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', '%'.$s.'%')
                        ->orWhere('lastname', 'like', '%'.$s.'%')
                        ->orWhere('phone', 'like', '%'.$s.'%')
                        ->orWhere('email', 'like', '%'.$s.'%')
                        ->orWhere('document', 'like', '%'.$s.'%');
                });
            }
        }

        if ($request->filled('status')) {
            $st = trim((string) $request->status);
            if ($st !== '') {
                $query->where('status', $st);
            }
        }
    }
}
