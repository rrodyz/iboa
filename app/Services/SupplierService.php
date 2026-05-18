<?php

namespace App\Services;

use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(public readonly SupplierRepository $repository) {}

    public function search(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->search($filters, $perPage);
    }

    public function create(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $contacts  = $data['contacts']  ?? [];
            $addresses = $data['addresses'] ?? [];
            unset($data['contacts'], $data['addresses']);

            // Auto-generate supplier code if not provided (NOT NULL column)
            if (empty($data['code'])) {
                $last = Supplier::withTrashed()->orderByDesc('id')->value('code');
                $num  = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
                $data['code'] = 'FOUR-' . str_pad($num, 5, '0', STR_PAD_LEFT);
            }

            /** @var Supplier $supplier */
            $supplier = $this->repository->create($data);

            foreach ($contacts as $c) {
                if (!empty($c['last_name'])) {
                    $supplier->contacts()->create($c);
                }
            }

            foreach ($addresses as $a) {
                if (!empty($a['address'])) {
                    $supplier->addresses()->create($a);
                }
            }

            return $supplier;
        });
    }

    public function update(Supplier $supplier, array $data): Supplier
    {
        return DB::transaction(function () use ($supplier, $data) {
            $contacts  = $data['contacts']  ?? null;
            $addresses = $data['addresses'] ?? null;
            unset($data['contacts'], $data['addresses']);

            $this->repository->update($supplier->id, $data);

            if ($contacts !== null) {
                $supplier->contacts()->delete();
                foreach ($contacts as $c) {
                    if (!empty($c['last_name'])) {
                        $supplier->contacts()->create($c);
                    }
                }
            }

            if ($addresses !== null) {
                $supplier->addresses()->delete();
                foreach ($addresses as $a) {
                    if (!empty($a['address'])) {
                        $supplier->addresses()->create($a);
                    }
                }
            }

            return $supplier->fresh();
        });
    }

    public function delete(Supplier $supplier): void
    {
        // Check for open purchase orders that are not yet fully received/cancelled
        $openOrders = $supplier->purchaseOrders()
            ->whereIn('status', ['brouillon', 'envoye', 'confirme', 'partiellement_recu'])
            ->count();

        if ($openOrders > 0) {
            throw new \RuntimeException(
                "Impossible de supprimer ce fournisseur : il a {$openOrders} commande(s) en cours."
            );
        }

        $this->repository->delete($supplier->id);
    }
}
