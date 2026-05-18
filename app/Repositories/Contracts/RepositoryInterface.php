<?php
namespace App\Repositories\Contracts;

interface RepositoryInterface
{
    public function all(array $columns = ['*']): \Illuminate\Database\Eloquent\Collection;
    public function paginate(int $perPage = 20, array $columns = ['*']): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    public function find(int $id, array $columns = ['*']): ?\Illuminate\Database\Eloquent\Model;
    public function findOrFail(int $id): \Illuminate\Database\Eloquent\Model;
    public function create(array $data): \Illuminate\Database\Eloquent\Model;
    public function update(int $id, array $data): \Illuminate\Database\Eloquent\Model;
    public function delete(int $id): bool;
}
