<?php
namespace App\Http\Controllers;

use App\Http\Requests\Company\StoreBankAccountRequest;
use App\Http\Requests\Company\UpdateDocumentSettingRequest;
use App\Http\Requests\Company\UpdateGeneralRequest;
use App\Http\Requests\Company\UpdateLegalRequest;
use App\Models\CompanyBankAccount;
use App\Services\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $service)
    {
        $this->middleware('can:company.edit')->except(['show', 'index']);
    }

    public function edit(): View
    {
        $company = $this->service->getOrCreate();
        return view('company.edit', compact('company'));
    }

    public function updateGeneral(UpdateGeneralRequest $request): RedirectResponse
    {
        $company = $this->service->getOrCreate();
        try {
            $this->service->updateGeneral($company, $request->except('logo'), $request->file('logo'));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error',
                "Erreur lors de la mise à jour : " . $e->getMessage()
            );
        }
        return back()->with('success', 'Informations générales mises à jour.');
    }

    public function updateLegal(UpdateLegalRequest $request): RedirectResponse
    {
        $company = $this->service->getOrCreate();
        $this->service->updateLegal($company, $request->validated());
        return back()->with('success', 'Informations légales mises à jour.');
    }

    public function updateDocuments(UpdateDocumentSettingRequest $request): RedirectResponse
    {
        $company = $this->service->getOrCreate();
        $this->service->upsertDocumentSetting(
            $company,
            $request->except(['signature_image', 'stamp_image']),
            $request->file('signature_image'),
            $request->file('stamp_image')
        );
        return back()->with('success', 'Paramètres documents mis à jour.');
    }

    public function storeBankAccount(StoreBankAccountRequest $request): RedirectResponse
    {
        $company = $this->service->getOrCreate();
        $this->service->upsertBankAccount($company, $request->validated());
        return back()->with('success', 'Compte bancaire ajouté.');
    }

    public function updateBankAccount(StoreBankAccountRequest $request, CompanyBankAccount $account): RedirectResponse
    {
        $company = $this->service->getOrCreate();
        $this->service->upsertBankAccount($company, $request->validated(), $account->id);
        return back()->with('success', 'Compte bancaire mis à jour.');
    }

    public function destroyBankAccount(CompanyBankAccount $account): RedirectResponse
    {
        $this->service->deleteBankAccount($account->company, $account->id);
        return back()->with('success', 'Compte bancaire supprimé.');
    }
}
