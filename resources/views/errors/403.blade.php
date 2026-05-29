<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accès refusé — {{ config('app.name') }}</title>
    <style>*,::before,::after{box-sizing:border-box;border-width:0;border-style:solid}body{margin:0;font-family:ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased;background-color:#f9fafb}.flex{display:flex}.items-center{align-items:center}.justify-center{justify-content:center}.min-h-screen{min-height:100vh}.text-center{text-align:center}.max-w-md{max-width:28rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.w-16{width:4rem}.h-16{height:4rem}.rounded-2xl{border-radius:1rem}.mx-auto{margin-left:auto;margin-right:auto}.mb-6{margin-bottom:1.5rem}.w-8{width:2rem}.h-8{height:2rem}.text-3xl{font-size:1.875rem;line-height:2.25rem}.font-bold{font-weight:700}.text-gray-900{color:#111827}.mb-2{margin-bottom:.5rem}.text-gray-500{color:#6b7280}.mb-8{margin-bottom:2rem}.gap-3{gap:.75rem}.gap-2{gap:.5rem}.px-5{padding-left:1.25rem;padding-right:1.25rem}.py-2.5{padding-top:.625rem;padding-bottom:.625rem}.rounded-lg{border-radius:.5rem}.border{border-width:1px}.border-gray-300{border-color:#d1d5db}.text-gray-700{color:#374151}.text-sm{font-size:.875rem;line-height:1.25rem}.font-medium{font-weight:500}.bg-indigo-600{background-color:#4f46e5}.text-white{color:#fff}.w-4{width:1rem}.h-4{height:1rem}.bg-gray-100{background-color:#f3f4f6}.text-gray-400{color:#9ca3af}.bg-red-100{background-color:#fee2e2}.text-red-500{color:#ef4444}.bg-amber-100{background-color:#fef3c7}.text-amber-500{color:#f59e0b}.inline-flex{display:inline-flex}.flex-col{flex-direction:column}@media(min-width:640px){.sm:flex-row{flex-direction:row}}</style>
</head>
<body class="font-sans antialiased bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="text-center max-w-md px-6">
        <div class="w-16 h-16 rounded-2xl bg-red-100 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Accès refusé</h1>
        <p class="text-gray-500 mb-8">
            Vous n'avez pas les permissions nécessaires pour accéder à cette page.
            Contactez votre administrateur si vous pensez qu'il s'agit d'une erreur.
        </p>
        <div class="flex flex-col sm:flex-row gap-3 justify-center">
            <button onclick="history.back()"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retour
            </button>
            <a href="{{ url('/dashboard') }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                Tableau de bord
            </a>
        </div>
    </div>
</body>
</html>
