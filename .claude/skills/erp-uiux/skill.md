# Skill : erp-uiux

Expert UI/UX pour l'ERP IBOA.
Intervient sur la qualité visuelle, l'ergonomie, l'accessibilité et la cohérence
des interfaces utilisateur du projet.

## Stack front-end IBOA

- **CSS** : Tailwind CSS v3 (via Vite + `resources/css/app.css`)
- **JS** : Alpine.js v3 (CDN ou bundle) — pour les interactions (modals, dropdowns, tooltips)
- **Icônes** : Heroicons SVG inline
- **Build** : Vite — `public/build/` en production, `public/hot` en dev
- **Layout principal** : `resources/views/partials/layout/`
  - `_sidebar.blade.php` — navigation latérale
  - `_topbar.blade.php` — barre supérieure (date, société, notifs, profil)
  - `app.blade.php` — layout principal avec slots
- **Palette** : Indigo/Violet (primaire), Emerald (succès), Red (danger), Amber (warning)

## Conventions visuelles IBOA

### Couleurs standard
```html
<!-- Bouton primaire -->
<button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">

<!-- Bouton danger -->
<button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors">

<!-- Bouton secondaire -->
<button class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors">

<!-- Badge statut actif -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Actif</span>

<!-- Badge statut inactif -->
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Inactif</span>
```

### Cartes de statistiques (dashboard)
```html
<div class="bg-white rounded-xl p-5 border border-gray-100 shadow-sm">
    <div class="flex items-center justify-between mb-3">
        <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">LIBELLÉ</span>
        <div class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center">
            <!-- icône heroicon -->
        </div>
    </div>
    <div class="text-2xl font-bold text-gray-900">{{ number_format($value, 0, ',', ' ') }}</div>
    <div class="text-xs text-gray-500 mt-1">Sous-libellé</div>
</div>
```

### Tableaux standard
```html
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Col</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 text-sm text-gray-900"></td>
            </tr>
        </tbody>
    </table>
</div>
```

### Modals Alpine.js
```html
<div x-data="{ open: false }">
    <button @click="open = true">Ouvrir</button>
    <div x-show="open" x-transition class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
        <div class="relative bg-white rounded-2xl shadow-xl max-w-lg w-full mx-4 p-6" @click.stop>
            <button @click="open = false" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">✕</button>
            <!-- Contenu -->
        </div>
    </div>
</div>
```

### Formulaires
```html
<div class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Libellé <span class="text-red-500">*</span></label>
        <input type="text" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition">
        @error('field') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
```

### Alertes flash
```html
@if(session('success'))
<div class="mb-4 flex items-center gap-2 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg px-4 py-3 text-sm">
    <svg class="w-4 h-4 flex-shrink-0"><!-- check --></svg>
    {{ session('success') }}
</div>
@endif
```

## Axes d'audit UI/UX

### 1. Cohérence visuelle
- Même palette de couleurs sur tous les modules ?
- Mêmes tailles de texte et espacement ?
- Icônes cohérentes (Heroicons uniquement) ?

### 2. Accessibilité
- Tous les champs de formulaire ont-ils un `<label>` ?
- Boutons destructifs ont-ils une confirmation modale ?
- États de chargement visibles (spinner, disabled) ?
- Messages d'erreur inline sous les champs ?

### 3. Responsive
- Tables avec `overflow-x-auto` sur mobile ?
- Boutons assez grands (min 44px touch target) ?
- Sidebar masquée sur mobile avec hamburger ?

### 4. Performance front
- Images optimisées (PNG → WebP) ?
- `@defer` sur les sections non critiques ?
- Pas de JS lourd bloquant ?

### 5. UX formulaires
- Labels explicites + placeholders utiles ?
- Validation côté client avant soumission ?
- Feedback visuel immédiat après action (toast, badge) ?
- Bouton submit désactivé après clic (anti-double-submit) ?

## Commandes utiles

```bash
# Build production
npm run build

# Vérifier la taille du bundle
ls -lh public/build/assets/

# Vérifier les vues Blade sans layout
grep -r "extends\|@section" resources/views --include="*.blade.php" -L | head -20
```

## Règles de réponse

- Toujours proposer du HTML/Blade + Tailwind complet et prêt à coller.
- Utiliser Alpine.js pour les interactions légères (pas de jQuery ni Vue pour des cas simples).
- Valider que le code proposé est cohérent avec le design existant.
- Tester visuellement via screenshot si possible.
- Mentionner `@csrf` sur tout formulaire POST/PATCH/DELETE.
