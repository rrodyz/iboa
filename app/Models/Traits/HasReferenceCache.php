<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Trait pour mettre en cache automatiquement les données de référence
 * (devises, taxes, conditions de paiement, etc.).
 *
 * Caractéristiques :
 *  - TTL par défaut : 1 heure
 *  - Invalidation automatique sur save/delete via Model::booted()
 *  - Clé construite à partir du nom de classe : « ref_cache:CurrencyTable:active »
 *
 * Usage :
 *   use HasReferenceCache;
 *   public static function cachedActive(): Collection { return self::cached('active'); }
 *
 * Le caller invoque :
 *   Currency::cachedActive();
 *   TaxRate::cachedAll();
 */
trait HasReferenceCache
{
    /**
     * TTL du cache (secondes). Surchargeable par const sur le model.
     */
    public static function referenceCacheTtl(): int
    {
        return defined(static::class . '::REFERENCE_CACHE_TTL')
            ? static::REFERENCE_CACHE_TTL
            : 3600; // 1 heure
    }

    /**
     * Préfixe de clé (utilisé aussi par le flush).
     */
    protected static function referenceCachePrefix(): string
    {
        return 'ref_cache:' . class_basename(static::class);
    }

    /**
     * Récupère tous les enregistrements actifs, mis en cache.
     */
    public static function cachedActive(): Collection
    {
        return Cache::remember(
            static::referenceCachePrefix() . ':active',
            static::referenceCacheTtl(),
            fn () => static::query()
                ->when(in_array('is_active', (new static)->getFillable()), fn ($q) => $q->where('is_active', true))
                ->orderBy(static::referenceOrderColumn())
                ->get()
        );
    }

    /**
     * Récupère TOUS les enregistrements (actifs ou non), mis en cache.
     */
    public static function cachedAll(): Collection
    {
        return Cache::remember(
            static::referenceCachePrefix() . ':all',
            static::referenceCacheTtl(),
            fn () => static::query()->orderBy(static::referenceOrderColumn())->get()
        );
    }

    /**
     * Colonne de tri par défaut (surchargeable par model).
     */
    protected static function referenceOrderColumn(): string
    {
        return defined(static::class . '::REFERENCE_ORDER_COLUMN')
            ? static::REFERENCE_ORDER_COLUMN
            : 'id';
    }

    /**
     * Vide explicitement le cache de ce model.
     * Appelé automatiquement sur save/delete, mais utilisable manuellement.
     */
    public static function flushReferenceCache(): void
    {
        $prefix = static::referenceCachePrefix();
        Cache::forget($prefix . ':active');
        Cache::forget($prefix . ':all');
    }

    /**
     * Hook Eloquent : invalide le cache à toute modification.
     */
    protected static function bootHasReferenceCache(): void
    {
        static::saved(fn ($model) => static::flushReferenceCache());
        static::deleted(fn ($model) => static::flushReferenceCache());
    }
}
