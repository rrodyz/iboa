{{--
  [CONCURRENCE-MULTI-USER] Champs cachés de protection des formulaires.

  À insérer dans TOUS les formulaires <form> de création et d'édition.

  Usage (formulaire de CRÉATION) :
    <x-form-guard />

  Usage (formulaire d'ÉDITION) :
    <x-form-guard :model="$invoice" />

  Ce composant injecte :
    1. _idempotency_key  — UUID unique par chargement de page (anti-double-clic)
    2. _lock_version     — timestamp updated_at du modèle (verrou optimiste, édition seulement)
--}}
@props(['model' => null])

{{-- Anti-double-soumission : clé unique par chargement de page --}}
<input type="hidden" name="_idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">

@if($model && $model->updated_at)
{{-- Verrou optimiste : détecte si un autre user a sauvé depuis l'ouverture --}}
<input type="hidden" name="_lock_version" value="{{ $model->updated_at->timestamp }}">
@endif
