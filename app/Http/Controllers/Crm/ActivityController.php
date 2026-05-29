<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $query = CrmActivity::forCompany($companyId)->with(['contact', 'opportunity', 'user']);

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->input('status')) {
            match($status) {
                'done'    => $query->where('is_done', true),
                'pending' => $query->where('is_done', false),
                'overdue' => $query->overdue(),
                default   => null,
            };
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        $activities = $query
            ->orderByRaw("CASE WHEN is_done = 0 AND due_at < NOW() THEN 0 ELSE 1 END")
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END")
            ->orderBy('due_at')
            ->paginate(30)
            ->withQueryString();

        $users   = User::where('company_id', $companyId)->orderBy('name')->get();
        $filters = $request->only(['type', 'status', 'user_id']);

        return view('crm.activities.index', compact('activities', 'users', 'filters'));
    }

    public function create(Request $request)
    {
        $companyId   = Auth::user()->company_id;
        $contacts    = CrmContact::forCompany($companyId)->orderBy('name')->get();
        $opps        = CrmOpportunity::forCompany($companyId)->active()->orderBy('title')->get();
        $users       = User::where('company_id', $companyId)->orderBy('name')->get();
        $contactId   = $request->input('contact_id');
        $opportunityId = $request->input('opportunity_id');

        return view('crm.activities.create', compact('contacts', 'opps', 'users', 'contactId', 'opportunityId'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateActivity($request);
        $validated['company_id'] = Auth::user()->company_id;
        if (!isset($validated['user_id'])) {
            $validated['user_id'] = Auth::id();
        }

        CrmActivity::create($validated);

        // Rediriger intelligemment selon le contexte
        if ($request->filled('crm_contact_id')) {
            return redirect()->route('crm.contacts.show', $request->input('crm_contact_id'))
                ->with('success', 'Activité enregistrée.');
        }
        if ($request->filled('crm_opportunity_id')) {
            return redirect()->route('crm.opportunities.show', $request->input('crm_opportunity_id'))
                ->with('success', 'Activité enregistrée.');
        }
        return redirect()->route('crm.activities.index')
            ->with('success', 'Activité enregistrée.');
    }

    public function edit(CrmActivity $activity)
    {
        $this->authorizeCompany($activity);
        $companyId = Auth::user()->company_id;
        $contacts  = CrmContact::forCompany($companyId)->orderBy('name')->get();
        $opps      = CrmOpportunity::forCompany($companyId)->active()->orderBy('title')->get();
        $users     = User::where('company_id', $companyId)->orderBy('name')->get();

        return view('crm.activities.edit', compact('activity', 'contacts', 'opps', 'users'));
    }

    public function update(Request $request, CrmActivity $activity)
    {
        $this->authorizeCompany($activity);
        $validated = $this->validateActivity($request);
        $activity->update($validated);

        return redirect()->route('crm.activities.index')
            ->with('success', 'Activité mise à jour.');
    }

    public function destroy(CrmActivity $activity)
    {
        $this->authorizeCompany($activity);
        $activity->delete();
        return redirect()->back()->with('success', 'Activité supprimée.');
    }

    /** Marquer comme fait / à faire */
    public function toggleDone(CrmActivity $activity)
    {
        $this->authorizeCompany($activity);
        $activity->update([
            'is_done' => !$activity->is_done,
            'done_at' => $activity->is_done ? null : now(),
        ]);
        return redirect()->back()->with('success', 'Activité mise à jour.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function authorizeCompany(CrmActivity $act): void
    {
        abort_if($act->company_id !== Auth::user()->company_id, 403);
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'type'                => ['required', 'in:appel,email,rdv,note,tache'],
            'subject'             => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string', 'max:5000'],
            'crm_contact_id'      => ['nullable', 'exists:crm_contacts,id'],
            'crm_opportunity_id'  => ['nullable', 'exists:crm_opportunities,id'],
            'user_id'             => ['nullable', 'exists:users,id'],
            'due_at'              => ['nullable', 'date'],
            'done_at'             => ['nullable', 'date'],
            'priority'            => ['required', 'in:low,normal,high'],
            'is_done'             => ['nullable', 'boolean'],
            'duration_minutes'    => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
