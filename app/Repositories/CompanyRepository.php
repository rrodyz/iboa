<?php
namespace App\Repositories;

use App\Models\Company;

class CompanyRepository extends BaseRepository
{
    public function __construct(Company $model)
    {
        parent::__construct($model);
    }

    public function firstOrCreate(): Company
    {
        return Company::firstOrCreate([], ['name' => 'Ma Société', 'country' => 'Burkina Faso']);
    }

    public function getWithRelations(): Company
    {
        return Company::with(['bankAccounts', 'documentSettings', 'warehouses', 'currentFiscalYear'])->firstOrFail();
    }
}
