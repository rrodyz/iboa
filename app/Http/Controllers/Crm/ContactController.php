<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    public function __construct(private ClientService $clientService) {}

    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $query = CrmContact::forCompany($companyId)->with('user');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('company_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('phone', 'like', "%$search%");
            });
        }
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $contacts = $query->latest()->paginate(25)->withQueryString();

        $users   = User::where('company_id', $companyId)->orderBy('name')->get();
        $filters = $request->only(['search', 'type', 'status', 'source', 'user_id']);

        return view('crm.contacts.index', compact('contacts', 'users', 'filters'));
    }

    public function create()
    {
        $users = User::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        return view('crm.contacts.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateContact($request);
        $validated['company_id'] = Auth::user()->company_id;
        $validated['tags'] = $request->filled('tags')
            ? array_filter(array_map('trim', explode(',', $request->input('tags'))))
            : null;

        CrmContact::create($validated);

        return redirect()->route('crm.contacts.index')
            ->with('success', 'Contact créé avec succès.');
    }

    public function show(CrmContact $contact)
    {
        $this->authorizeCompany($contact);
        $contact->load(['user', 'client', 'opportunities.activities', 'activities.user']);

        $activities = $contact->activities()->with('user')->latest('due_at')->get();
        $opps       = $contact->opportunities()->with('user')->latest()->get();

        return view('crm.contacts.show', compact('contact', 'activities', 'opps'));
    }

    public function edit(CrmContact $contact)
    {
        $this->authorizeCompany($contact);
        $users = User::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        return view('crm.contacts.edit', compact('contact', 'users'));
    }

    public function update(Request $request, CrmContact $contact)
    {
        $this->authorizeCompany($contact);
        $validated = $this->validateContact($request);
        $validated['tags'] = $request->filled('tags')
            ? array_filter(array_map('trim', explode(',', $request->input('tags'))))
            : null;

        $contact->update($validated);

        return redirect()->route('crm.contacts.show', $contact)
            ->with('success', 'Contact mis à jour.');
    }

    public function destroy(CrmContact $contact)
    {
        $this->authorizeCompany($contact);
        $contact->delete();
        return redirect()->route('crm.contacts.index')
            ->with('success', 'Contact supprimé.');
    }

    /**
     * Convertit un prospect CRM en Client ERP.
     * Utilise ClientService::create() — même logique que ClientController::store().
     * Le contact est marqué "converted" et lié au client créé.
     */
    public function convert(CrmContact $contact)
    {
        $this->authorizeCompany($contact);

        if ($contact->client_id) {
            return redirect()->route('clients.show', $contact->client_id)
                ->with('info', 'Ce contact est déjà converti en client.');
        }

        // Mapper les champs CRM → Client (champs communs)
        $clientData = [
            'name'    => $contact->name,
            'type'    => 'entreprise',          // défaut — modifiable ensuite
            'email'   => $contact->email,
            'phone'   => $contact->phone,
            'mobile'  => $contact->mobile,
            'website' => $contact->website,
            'address' => $contact->address,
            'city'    => $contact->city,
            'country' => $contact->country ?? 'BF',
            'notes'   => $contact->notes,
            'is_active' => true,
        ];

        // Générer le code via ClientRepository (même logique que ClientController)
        $client = $this->clientService->create($clientData);

        // Marquer le contact comme converti et le lier au client
        $contact->update([
            'status'    => 'converted',
            'client_id' => $client->id,
        ]);

        return redirect()->route('clients.show', $client)
            ->with('success', 'Prospect « ' . $contact->name . ' » converti en client avec succès.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeCompany(CrmContact $contact): void
    {
        abort_if($contact->company_id !== Auth::user()->company_id, 403);
    }

    private function validateContact(Request $request): array
    {
        return $request->validate([
            'type'         => ['required', 'in:prospect,contact,partenaire'],
            'name'         => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title'    => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'mobile'       => ['nullable', 'string', 'max:50'],
            'website'      => ['nullable', 'url', 'max:255'],
            'address'      => ['nullable', 'string', 'max:500'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:5'],
            'source'       => ['required', 'in:direct,referral,web,social,event,other'],
            'score'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'status'       => ['required', 'in:new,contacted,qualified,unqualified,converted,lost'],
            'sector'       => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string', 'max:5000'],
            'user_id'      => ['nullable', 'exists:users,id'],
        ]);
    }
}
