
        
        <header class="sticky top-0 z-20 flex-shrink-0" x-data="{ ms: false }"
                style="background:rgba(255,255,255,.82);
                       backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
                       border-bottom:1px solid rgba(99,102,241,.1);
                       box-shadow:0 1px 0 rgba(255,255,255,.9),0 2px 8px rgba(15,23,42,.06);">
        <div class="h-14 flex items-center gap-3 px-4 lg:px-6">
            
            <button @click="$store.sidebar.open = !$store.sidebar.open"
                    class="lg:hidden flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            
            <nav class="hidden sm:flex items-center gap-1 text-sm text-gray-500 min-w-0">
                <?php echo $__env->yieldContent('breadcrumb'); ?>
            </nav>

            
            <button @click="$dispatch('open-palette')"
                    class="hidden md:flex items-center gap-2 flex-1 max-w-xs mx-4 px-3 py-1.5 text-sm text-gray-400 bg-gray-50 border border-gray-200 rounded-lg hover:border-indigo-300 hover:bg-white hover:text-gray-600 transition-all cursor-text">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                </svg>
                <span class="flex-1 text-left">Rechercher…</span>
                <kbd class="flex-shrink-0 text-[10px] font-semibold bg-white border border-gray-200 rounded px-1.5 py-0.5 text-gray-400 shadow-sm">Ctrl K</kbd>
            </button>

            
            <div class="hidden"
                 x-data="{
                    q: '', results: [], open: false, loading: false,
                    async search() {
                        if (this.q.length < 2) { this.results = []; this.open = false; return; }
                        this.loading = true;
                        try {
                            const r = await fetch('<?php echo e(route('search')); ?>?q=' + encodeURIComponent(this.q), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                            if (!r.ok) { return; }
                            const d = await r.json();
                            this.results = d.results ?? [];
                            this.open = this.results.length > 0;
                        } catch (_) {
                            // Requête annulée (navigation Turbo) ou réseau indisponible — silencieux
                        } finally {
                            this.loading = false;
                        }
                    }
                 }"
                 @click.outside="open = false">
                <div class="relative w-full">
                    <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                        <svg x-show="!loading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <svg x-show="loading" class="w-4 h-4 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </div>
                    <input type="text" x-model="q" @input.debounce.300ms="search()" @focus="q.length >= 2 && (open = results.length > 0)"
                           placeholder="Rechercher…"
                           class="w-full pl-9 pr-4 py-1.5 text-sm bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:bg-white transition-all placeholder-gray-400">
                    
                    <div x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden max-h-80 overflow-y-auto">
                        <template x-for="item in results" :key="item.url + item.label">
                            <a :href="item.url"
                               class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                                <span class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                                      :class="{
                                          'bg-blue-100 text-blue-700': item.color==='blue',
                                          'bg-orange-100 text-orange-700': item.color==='orange',
                                          'bg-emerald-100 text-emerald-700': item.color==='emerald',
                                          'bg-indigo-100 text-indigo-700': item.color==='indigo',
                                          'bg-violet-100 text-violet-700': item.color==='violet',
                                          'bg-red-100 text-red-700': item.color==='red',
                                      }"
                                      x-text="item.type.charAt(0)"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate" x-text="item.label"></p>
                                    <p class="text-xs text-gray-500 truncate" x-text="item.sublabel || item.type"></p>
                                </div>
                                <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full flex-shrink-0" x-text="item.type"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            
            <div class="flex items-center gap-2">
                
                <button @click="ms = !ms"
                        class="md:hidden flex items-center justify-center w-8 h-8 rounded-lg transition-colors"
                        :class="ms ? 'bg-indigo-50 text-indigo-600' : 'text-gray-500 hover:bg-gray-100'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                </button>
                
                <button @click="$store.darkMode.toggle()"
                        :title="$store.darkMode.dark ? 'Mode clair' : 'Mode sombre'"
                        class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                    <svg x-show="!$store.darkMode.dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <svg x-show="$store.darkMode.dark" class="w-4 h-4 text-amber-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.592-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.592z"/>
                    </svg>
                </button>

                
                <span class="hidden xl:block text-xs text-gray-400 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-200">
                    <?php echo e(now()->locale('fr')->isoFormat('ddd D MMM YYYY')); ?>

                </span>

                
                <?php
                    $activeCompany = currentCompany();
                    $user = auth()->user();
                    $allCompanies = $user->hasRole('super-admin')
                        ? \App\Models\Company::orderBy('name')->get()
                        : collect([$activeCompany]);
                ?>
                <div class="hidden lg:block relative"
                     x-data="{ open: false }"
                     @click.outside="open = false">

                    
                    <button @click="open = !open"
                            type="button"
                            class="flex items-center gap-1.5 text-xs font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-full border border-indigo-200 transition-colors focus:outline-none"
                            title="Changer de société">
                        <svg class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span class="max-w-[140px] truncate"><?php echo e($activeCompany->name); ?></span>
                        <?php if($allCompanies->count() > 1): ?>
                            <svg class="w-3 h-3 text-indigo-400 flex-shrink-0" :class="open ? 'rotate-180' : ''" style="transition:transform .2s" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        <?php endif; ?>
                    </button>

                    
                    <?php if($allCompanies->count() > 1): ?>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                         class="absolute left-0 top-full mt-2 w-64 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden py-1"
                         style="display:none">

                        <p class="px-3 py-2 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Changer de société</p>

                        <?php $__currentLoopData = $allCompanies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $co): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <form method="POST" action="<?php echo e(route('company.switch', $co)); ?>">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-sm hover:bg-indigo-50 transition-colors text-left
                                           <?php echo e($co->id === $activeCompany->id ? 'text-indigo-700 font-semibold bg-indigo-50/60' : 'text-gray-700'); ?>">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold flex-shrink-0
                                             <?php echo e($co->id === $activeCompany->id ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500'); ?>">
                                    <?php echo e(strtoupper(substr($co->name, 0, 1))); ?>

                                </span>
                                <span class="flex-1 truncate"><?php echo e($co->name); ?></span>
                                <?php if($co->id === $activeCompany->id): ?>
                                <svg class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <?php endif; ?>
                            </button>
                        </form>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endif; ?>
                </div>

                
                <div class="relative" x-data="{ open: false, unread: 0, items: [] }"
                     x-init="fetch('<?php echo e(route('notifications.recent')); ?>').then(r=>r.ok?r.json():null).then(d=>{ if(d){ unread=d.unread; items=d.items; } }).catch(()=>{})"
                     @click.outside="open = false">
                    <button @click="open = !open; if(open) fetch('<?php echo e(route('notifications.recent')); ?>').then(r=>r.ok?r.json():null).then(d=>{ if(d){ unread=d.unread; items=d.items; } }).catch(()=>{})"
                            class="relative w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span x-show="unread > 0" x-text="unread > 9 ? '9+' : unread"
                              class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-0.5 leading-none"></span>
                    </button>
                    
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                            <span class="text-sm font-bold text-gray-900">Notifications</span>
                            <a href="<?php echo e(route('notifications.index')); ?>" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Tout voir</a>
                        </div>
                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                            <template x-if="items.length === 0">
                                <div class="py-8 text-center text-sm text-gray-400">Aucune notification</div>
                            </template>
                            <template x-for="n in items" :key="n.id">
                                <a :href="n.url ? `/notifications/${n.id}/read` : '#'"
                                   class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 transition-colors"
                                   :class="n.read ? '' : 'bg-indigo-50/40'">
                                    <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs"
                                         :class="{
                                             'bg-red-100 text-red-600': n.color==='red',
                                             'bg-orange-100 text-orange-600': n.color==='orange',
                                             'bg-green-100 text-green-600': n.color==='green',
                                             'bg-indigo-100 text-indigo-600': n.color==='indigo',
                                         }">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-900 truncate" x-text="n.title"></p>
                                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2 leading-snug" x-text="n.message"></p>
                                        <p class="text-[10px] text-gray-400 mt-1" x-text="n.created_at"></p>
                                    </div>
                                    <span x-show="!n.read" class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0 mt-1.5"></span>
                                </a>
                            </template>
                        </div>
                        <div class="px-4 py-2.5 border-t border-gray-100 bg-gray-50">
                            <button @click="fetch('/notifications/mark-all-read',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('[name=csrf-token]').content}}).then(r=>{ if(r.ok){ unread=0; items=items.map(i=>({...i,read:true})); } }).catch(()=>{})"
                                    class="text-xs text-gray-500 hover:text-indigo-600 font-medium w-full text-center transition-colors">
                                Tout marquer comme lu
                            </button>
                        </div>
                    </div>
                </div>

                
                
                <button @click="$dispatch('open-shortcuts')"
                        title="Raccourcis clavier (?)"
                        class="hidden sm:flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors text-sm font-bold">
                    ?
                </button>

                
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('invoices.create')): ?>
                <a href="<?php echo e(route('ventes.factures.create')); ?>"
                   class="flex items-center gap-1.5 px-3 py-1.5 text-white text-sm font-semibold rounded-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-px"
                   style="background:linear-gradient(135deg,#6366F1,#8B5CF6);box-shadow:0 2px 8px rgba(99,102,241,.4);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span class="hidden sm:inline">Facture</span>
                </a>
                <?php endif; ?>

                
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button @click="open = !open"
                            class="flex items-center gap-2 rounded-xl px-2 py-1 hover:bg-gray-100 transition-colors focus:outline-none">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                             style="background:linear-gradient(135deg,#6366F1,#8B5CF6);box-shadow:0 0 0 2px rgba(99,102,241,.2),0 2px 8px rgba(99,102,241,.3);">
                            <span class="text-xs font-bold text-white"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 2))); ?></span>
                        </div>
                        <div class="hidden lg:block text-left">
                            <p class="text-xs font-semibold text-gray-800 leading-tight"><?php echo e(auth()->user()->name); ?></p>
                            <p class="text-[10px] text-gray-400 leading-tight"><?php echo e(auth()->user()->getRoleNames()->first() ?? 'Utilisateur'); ?></p>
                        </div>
                        <svg class="hidden lg:block w-3.5 h-3.5 text-gray-400 transition-transform duration-150" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 top-full mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 z-50 overflow-hidden"
                         style="display:none;">
                        
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                                     style="background:linear-gradient(135deg,#6366F1,#8B5CF6);">
                                    <span class="text-sm font-bold text-white"><?php echo e(strtoupper(substr(auth()->user()->name, 0, 2))); ?></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate"><?php echo e(auth()->user()->name); ?></p>
                                    <p class="text-xs text-gray-400 truncate"><?php echo e(auth()->user()->getRoleNames()->first() ?? 'Utilisateur'); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-2">
                            <a href="<?php echo e(route('profile.edit')); ?>"
                               class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Mon profil
                            </a>
                            <a href="<?php echo e(route('profile.edit')); ?>#password"
                               class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                <svg class="w-4 h-4 flex-shrink-0 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Changer mot de passe
                            </a>
                        </div>
                        
                        <div class="p-2 border-t border-gray-100">
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                    </svg>
                                    Déconnexion
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div x-show="ms"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="md:hidden border-t border-gray-100 px-4 py-3"
             style="display:none;"
             x-data="{
                 q: '', results: [], open: false, loading: false,
                 async search() {
                     if (this.q.length < 2) { this.results = []; this.open = false; return; }
                     this.loading = true;
                     try {
                         const r = await fetch('<?php echo e(route('search')); ?>?q=' + encodeURIComponent(this.q), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
                         if (!r.ok) { return; }
                         const d = await r.json();
                         this.results = d.results ?? [];
                         this.open = this.results.length > 0;
                     } catch (_) {}
                     finally { this.loading = false; }
                 }
             }"
             @click.outside="open = false">
            <div class="relative">
                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                    <svg x-show="!loading" class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                    </svg>
                    <svg x-show="loading" class="w-4 h-4 text-indigo-400 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                </div>
                <input type="search" x-model="q" @input.debounce.300ms="search()"
                       placeholder="Rechercher…"
                       x-ref="mobileInput"
                       x-effect="ms && $nextTick(() => $refs.mobileInput?.focus())"
                       class="w-full pl-9 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:bg-white transition-all"
                       style="font-size:16px;">
                
                <div x-show="open" class="absolute top-full left-0 right-0 mt-1.5 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden max-h-72 overflow-y-auto">
                    <template x-for="item in results" :key="item.url + item.label">
                        <a :href="item.url" @click="ms = false; open = false"
                           class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0">
                            <span class="flex-shrink-0 w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold"
                                  :class="{
                                      'bg-blue-100 text-blue-700': item.color==='blue',
                                      'bg-orange-100 text-orange-700': item.color==='orange',
                                      'bg-emerald-100 text-emerald-700': item.color==='emerald',
                                      'bg-indigo-100 text-indigo-700': item.color==='indigo',
                                      'bg-violet-100 text-violet-700': item.color==='violet',
                                      'bg-red-100 text-red-700': item.color==='red',
                                  }"
                                  x-text="item.type.charAt(0)"></span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="item.label"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="item.sublabel || item.type"></p>
                            </div>
                        </a>
                    </template>
                </div>
            </div>
        </div>
        </header>
<?php /**PATH C:\laragon\www\iboa\resources\views/partials/layout/_topbar.blade.php ENDPATH**/ ?>