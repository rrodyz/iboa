# Skill : laravel-security-audit

Auditeur sécurité Laravel 12 pour l'ERP IBOA.
Détecte et corrige les vulnérabilités : injections SQL, XSS, CSRF, IDOR,
élévation de privilèges, secrets exposés, upload non sécurisé.

## Contexte projet

- **Framework** : Laravel 12 / PHP 8.2
- **Auth** : Laravel Breeze + Spatie Permission (`roles` + `permissions`)
- **Middleware** : `auth`, `verified`, `permission:x.y`, `SecurityHeaders`, `InvoiceLockGuard`
- **Chemin** : `C:/laragon/www/iboa`

## Axe 1 — Secrets et configuration

```bash
# .env versionné ?
git log --all --oneline -- .env
git log --all --oneline -- .env.example

# APP_DEBUG en production ?
grep "APP_DEBUG" .env

# Clés dans le code ?
grep -rn "password\s*=\s*['\"]" app --include="*.php"
grep -rn "api_key\s*=\s*['\"]" app --include="*.php"
grep -rn "secret\s*=\s*['\"]" app --include="*.php"
```

**Attendu :**
- `.env` non versionné (dans `.gitignore`) ✅
- `APP_DEBUG=false` en production ✅
- `APP_KEY` non exposé ✅

## Axe 2 — Injections SQL

```bash
# Chercher les raw queries avec concaténation de variables
grep -rn "DB::select\|DB::statement\|whereRaw\|selectRaw\|orderByRaw" app --include="*.php" \
  | grep -v "?.*\$\|bindParam\|->get()"
```

### Patterns dangereux
```php
// ❌ INJECTION possible
DB::select("SELECT * FROM users WHERE name = '$name'");
->whereRaw("status = '$status'");

// ✅ Paramètres liés
DB::select("SELECT * FROM users WHERE name = ?", [$name]);
->whereRaw("status = ?", [$status]);
->where('status', $status);  // Eloquent — sûr par défaut
```

## Axe 3 — XSS (Cross-Site Scripting)

```bash
# Trouver les {!! !!} non filtrés (HTML raw)
grep -rn "{!!" resources/views --include="*.blade.php"
```

**Règle :** `{{ $var }}` est sûr (Blade échappe automatiquement).
`{!! $var !!}` est dangereux — n'utiliser que pour du HTML de confiance (généré en interne).

```bash
# Cas légitimes (HTML généré par l'app)
grep -rn "{!!" resources/views --include="*.blade.php" \
  | grep -v "Blade::\|route(\|asset(\|url("
```

## Axe 4 — CSRF

```bash
# Formulaires sans @csrf
grep -rn "<form" resources/views --include="*.blade.php" -A5 \
  | grep -B5 "method=\"POST\|method=\"PATCH\|method=\"DELETE" \
  | grep -v "@csrf" | grep "<form"
```

Chaque formulaire POST/PATCH/DELETE doit avoir `@csrf`.

## Axe 5 — IDOR (Insecure Direct Object Reference)

```bash
# Routes qui prennent un ID sans vérification de propriété
grep -rn "find(\$id\|findOrFail(\$id\|->find(request(" app/Http/Controllers --include="*.php"
```

### Pattern sécurisé
```php
// ❌ IDOR possible
public function show($id) {
    $invoice = Invoice::findOrFail($id);
    return view('invoice', $invoice);
}

// ✅ Vérification de propriété
public function show(Invoice $invoice) {
    $this->authorize('view', $invoice);  // Policy
    // OU
    abort_unless($invoice->company_id === currentCompany()->id, 403);
    return view('invoice', $invoice);
}
```

## Axe 6 — Autorisation et permissions

```bash
# Routes sans middleware auth
php artisan route:list --json | php -r "
\$routes = json_decode(file_get_contents('php://stdin'), true);
\$unsafe = array_filter(\$routes, fn(\$r) =>
    !in_array('Illuminate\\\\Auth\\\\Middleware\\\\Authenticate', \$r['middleware'] ?? [])
    && !\in_array('web', \$r['middleware'] ?? [])
    && \$r['uri'] !== '/'
    && strpos(\$r['uri'], 'verifier/') === false
);
foreach(\$unsafe as \$r) echo \$r['method'] . ' ' . \$r['uri'] . PHP_EOL;
"

# Contrôleurs sans authorize()
grep -rL "authorize\|middleware('permission" app/Http/Controllers --include="*.php"
```

## Axe 7 — Upload de fichiers

```bash
# Chercher les upload sans validation MIME
grep -rn "store(\|storeAs(\|move(" app --include="*.php" -B5 \
  | grep -v "mimes\|mimetypes\|max:"
```

### Pattern sécurisé
```php
$request->validate([
    'file' => ['required', 'file', 'mimes:pdf,jpg,png', 'max:5120'],
]);
$path = $request->file('file')->store('documents', 'private');
```

**Jamais** stocker dans `public/` directement, toujours via `Storage::disk('private')`.

## Axe 8 — Rate limiting

```bash
# Vérifier le throttle sur les routes sensibles
grep -n "throttle\|RateLimiter" routes/web.php routes/api.php
```

Routes à protéger en priorité :
- `POST /login` — brute force passwords
- `POST /password/*` — reset password
- Toutes les routes API

## Axe 9 — Headers de sécurité

```bash
# Vérifier SecurityHeaders middleware
cat app/Http/Middleware/SecurityHeaders.php
```

Headers attendus :
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'...
Strict-Transport-Security: max-age=31536000 (production HTTPS)
```

## Axe 10 — Logs et traçabilité

```bash
# Vérifier que audit_logs est bien alimenté
php artisan tinker --execute="
echo App\Models\AuditLog::orderByDesc('created_at')->first()->toJson(JSON_PRETTY_PRINT);
"
```

Chaque action critique (création facture, paiement, modification contrat) doit générer un `AuditLog`.

## Format de rapport sécurité

```
## Rapport Sécurité IBOA — {date}

### Vulnérabilités critiques ❌
1. ...

### Failles moyennes ⚠️
2. ...

### Bonnes pratiques ✅
- ...
```

## Règles de réponse

- Toujours vérifier via les outils — ne jamais supposer.
- Proposer un patch de code pour chaque vulnérabilité trouvée.
- Classer par CVSS : Critique > Haute > Moyenne > Basse.
- Ne jamais afficher de secrets dans les réponses.
