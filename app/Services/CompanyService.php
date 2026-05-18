<?php
namespace App\Services;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\DocumentSetting;
use App\Repositories\CompanyRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function __construct(private CompanyRepository $repository) {}

    public function getOrCreate(): Company
    {
        return Company::with(['bankAccounts', 'documentSetting', 'currentFiscalYear', 'defaultCurrency'])
            ->first() ?? $this->createDefault();
    }

    private function createDefault(): Company
    {
        return Company::create([
            'name'    => 'Ma Société',
            'country' => 'Burkina Faso',
        ]);
    }

    public function updateGeneral(Company $company, array $data, ?UploadedFile $logo = null): Company
    {
        if ($logo) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $data['logo'] = $logo->store('logos', 'public');
        }
        $company->update($data);
        return $company->fresh();
    }

    public function updateLegal(Company $company, array $data): Company
    {
        $company->update($data);
        return $company->fresh();
    }

    public function upsertBankAccount(Company $company, array $data, ?int $accountId = null): CompanyBankAccount
    {
        return DB::transaction(function () use ($company, $data, $accountId) {
            if (isset($data['is_default']) && $data['is_default']) {
                $company->bankAccounts()->update(['is_default' => false]);
            }
            if ($accountId) {
                $account = $company->bankAccounts()->findOrFail($accountId);
                $account->update($data);
                return $account->fresh();
            }
            return $company->bankAccounts()->create($data);
        });
    }

    public function deleteBankAccount(Company $company, int $accountId): bool
    {
        return $company->bankAccounts()->findOrFail($accountId)->delete();
    }

    public function upsertDocumentSetting(Company $company, array $data, ?UploadedFile $signature = null, ?UploadedFile $stamp = null): DocumentSetting
    {
        if ($signature) {
            $data['signature_image'] = $signature->store('signatures', 'public');
        }
        if ($stamp) {
            $data['stamp_image'] = $stamp->store('stamps', 'public');
        }
        return DocumentSetting::updateOrCreate(
            ['company_id' => $company->id],
            array_merge($data, ['company_id' => $company->id])
        );
    }
}
