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
            $data['logo'] = $this->saveLogoFile($logo, $company);
        }

        $company->update($data);
        return $company->fresh();
    }

    /**
     * [LOGO-UPLOAD-FIX] Sauvegarde le logo en utilisant des appels natifs PHP
     * (move_uploaded_file / copy / file_put_contents) plutôt que Laravel Storage.
     *
     * Sous Windows/Laragon, Symfony\UploadedFile::getRealPath() peut renvoyer false
     * après que le framework ait passé le request à travers certains middlewares
     * (TransformsRequest, etc.), ce qui fait planter Storage::store() avec
     * "Path cannot be empty" car fopen('') échoue.
     *
     * Stratégie défensive en 3 tentatives :
     *   1. Lire $logo->getPathname() (chemin temp Symfony, plus stable)
     *   2. Sinon $logo->getRealPath()
     *   3. Sinon tentative via le stream interne
     */
    private function saveLogoFile(UploadedFile $logo, Company $company): string
    {
        // Résoudre le chemin réel du fichier temporaire avec plusieurs fallbacks
        $tempPath = $logo->getPathname() ?: $logo->getRealPath() ?: '';

        if ($tempPath === '' || !is_file($tempPath)) {
            throw new \RuntimeException(
                "Le fichier image n'a pas pu être traité (fichier temporaire introuvable). "
                . "Réessayez en sélectionnant à nouveau le logo, ou utilisez un autre fichier."
            );
        }

        // Calcul du nom destination
        $ext      = strtolower($logo->getClientOriginalExtension() ?: 'png');
        $name     = \Illuminate\Support\Str::random(40) . '.' . $ext;
        $relPath  = 'logos/' . $name;
        $diskRoot = storage_path('app/public');
        $absDest  = $diskRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);

        // S'assure que le dossier logos/ existe
        $destDir = dirname($absDest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Tentative 1 : move_uploaded_file (fonction PHP native dédiée aux uploads)
        $moved = @move_uploaded_file($tempPath, $absDest);

        // Tentative 2 : copy() classique si move_uploaded_file refuse (tests, CLI)
        if (!$moved) {
            $moved = @copy($tempPath, $absDest);
        }

        // Tentative 3 : lecture binaire + écriture (dernier recours)
        if (!$moved) {
            $content = @file_get_contents($tempPath);
            if ($content === false) {
                throw new \RuntimeException(
                    "Impossible de lire le fichier temporaire pour l'upload du logo. "
                    . "Cela peut venir d'un antivirus qui verrouille le fichier — réessayez."
                );
            }
            $written = @file_put_contents($absDest, $content);
            if ($written === false) {
                throw new \RuntimeException(
                    "Impossible d'écrire le logo dans storage/app/public/logos. "
                    . "Vérifiez les permissions du dossier."
                );
            }
        }

        // Supprime l'ancien logo de manière sécurisée
        $oldLogo = trim((string) $company->logo);
        if ($oldLogo !== '') {
            $oldAbs = $diskRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $oldLogo);
            if (is_file($oldAbs)) {
                @unlink($oldAbs);
            }
        }

        return $relPath;
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
