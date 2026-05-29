<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * [RH-PRO] Gestion des documents employés.
 * Upload sécurisé vers storage/private (non accessible directement par URL).
 * Téléchargement uniquement via cette route authentifiée.
 */
class EmployeeDocumentController extends Controller
{
    /** Upload d'un document pour un employé */
    public function store(Request $request, Employee $employe)
    {
        $company = currentCompany();
        abort_if($employe->company_id !== $company->id, 403);

        $request->validate([
            'document_type' => ['required', 'in:cnib,passeport,contrat,avenant,diplome,attestation,medical,cnss,photo,autre'],
            'label'         => ['required', 'string', 'max:200'],
            'document_file' => ['required', 'file', 'max:10240', // 10 MB
                                'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'document_date' => ['nullable', 'date'],
            'expires_at'    => ['nullable', 'date', 'after_or_equal:document_date'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('document_file');
        $path = $file->store("employees/{$employe->id}/documents", 'private');

        EmployeeDocument::create([
            'employee_id'   => $employe->id,
            'document_type' => $request->document_type,
            'label'         => $request->label,
            'original_name' => $file->getClientOriginalName(),
            'file_path'     => $path,
            'mime_type'     => $file->getMimeType(),
            'file_size'     => $file->getSize(),
            'document_date' => $request->document_date,
            'expires_at'    => $request->expires_at,
            'notes'         => $request->notes,
            'uploaded_by'   => Auth::id(),
        ]);

        return back()->with('success', 'Document ajouté avec succès.');
    }

    /** Téléchargement sécurisé */
    public function download(Employee $employe, EmployeeDocument $document)
    {
        $company = currentCompany();
        abort_if($employe->company_id !== $company->id, 403);
        abort_if($document->employee_id !== $employe->id, 403);

        if (! Storage::disk('private')->exists($document->file_path)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('private')->download(
            $document->file_path,
            $document->original_name,
            ['Content-Type' => $document->mime_type]
        );
    }

    /** Suppression d'un document */
    public function destroy(Employee $employe, EmployeeDocument $document)
    {
        $company = currentCompany();
        abort_if($employe->company_id !== $company->id, 403);
        abort_if($document->employee_id !== $employe->id, 403);

        Storage::disk('private')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }

    /** Upload / mise à jour de la photo de profil */
    public function updatePhoto(Request $request, Employee $employe)
    {
        $company = currentCompany();
        abort_if($employe->company_id !== $company->id, 403);

        $request->validate([
            'photo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($employe->photo_path && Storage::disk('private')->exists($employe->photo_path)) {
            Storage::disk('private')->delete($employe->photo_path);
        }

        $path = $request->file('photo')->store("employees/{$employe->id}/photo", 'private');
        $employe->update(['photo_path' => $path]);

        return back()->with('success', 'Photo mise à jour.');
    }

    /** Accès à la photo de profil */
    public function photo(Employee $employe)
    {
        $company = currentCompany();
        abort_if($employe->company_id !== $company->id, 403);

        if (! $employe->photo_path || ! Storage::disk('private')->exists($employe->photo_path)) {
            abort(404);
        }

        return Storage::disk('private')->response($employe->photo_path);
    }
}
