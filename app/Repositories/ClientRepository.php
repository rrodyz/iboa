<?php
namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository extends BaseRepository
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    public function search(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Client::query()
            ->when(isset($filters['search']), fn($q) => $q->where(function($q2) use ($filters) {
                $q2->where('name', 'like', "%{$filters['search']}%")
                   ->orWhere('code', 'like', "%{$filters['search']}%")
                   ->orWhere('phone', 'like', "%{$filters['search']}%")
                   ->orWhere('email', 'like', "%{$filters['search']}%");
            }))
            ->when(!empty($filters['type']), fn($q) => $q->where('type', $filters['type']))
            ->when(!empty($filters['category']), fn($q) => $q->where('category', $filters['category']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): Client
    {
        return Client::with(['contacts', 'addresses', 'assignedCommercial', 'interactions.user'])->findOrFail($id);
    }

    public function generateCode(): string
    {
        $last = Client::withTrashed()->orderByDesc('id')->value('code');
        $num  = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
        return 'CLI-' . str_pad($num, 5, '0', STR_PAD_LEFT);
    }
}
