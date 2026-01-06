<?php

namespace SmartCms\Lang;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use SmartCms\Lang\Models\Language;

class Languages
{
    public Collection $languages;

    public ?Language $currentLanguage;

    private $currentLanguageInitialized = false;

    public function __construct()
    {
        $this->languages = Language::query()->get();
    }

    public function current(): Language
    {
        // return $this->currentLanguage ?? $this->default();
        if (! $this->currentLanguageInitialized || ! $this->currentLanguage) {
            return $this->default();
        }

        return $this->currentLanguage;
    }

    public function default(): Language
    {
        return $this->languages->where('is_default', true)->first() ?? $this->languages->first();
    }

    public function get(int $id): Language
    {
        return $this->languages->where('id', $id)->first();
    }

    public function getMulti(array $ids): Collection
    {
        return $this->languages->whereIn('id', $ids)->sort(function ($a, $b) {
            $main_lang = main_lang_id();
            if ($a->id === $main_lang && $b->id !== $main_lang) {
                return -1;
            }
            if ($b->id === $main_lang && $a->id !== $main_lang) {
                return 1;
            }

            return $a->id <=> $b->id;
        })->values();
    }

    public function setCurrent(string $slug)
    {
        $this->currentLanguage = $this->languages->where('slug', $slug)->first() ?? $this->default();
        $this->currentLanguageInitialized = true;

        return $this;
    }

    public function isFrontendAvailable(string $slug): bool
    {
        return $this->languages->where('is_frontend_active', true)->where('slug', $slug)->count() > 0;
    }

    public function isAdminAvailable(string $slug): bool
    {
        return $this->languages->where('is_admin_active', true)->where('slug', $slug)->count() > 0;
    }

    public function all(): Collection
    {
        return $this->languages;
    }

    public function getBySlug(string $slug): ?Language
    {
        return $this->languages->where('slug', $slug)->first();
    }

    public function frontLanguages(): Collection
    {
        return $this->languages->where('is_frontend_active', true);
    }

    public function adminLanguages(): Collection
    {
        return $this->languages->where('is_admin_active', true);
    }

    public function getUrlForAllLanguages(): array
    {
        $currentRoute = Route::current();
        $params = Route::current()->parameters();
        $routeName = $currentRoute->getName();

        return $this->frontLanguages()->mapWithKeys(function ($locale) use ($routeName, $params) {
            $routeName = str_replace('.lang', '', $routeName);

            return [$locale->slug => route($routeName.'.lang', array_merge($params, ['lang' => $locale->slug]))];
        })->toArray();
    }

    /**
     * Fallback list for RTL languages not supported by Filament
     * Currently only Pashto (ps) is not in Filament's language files
     */
    private const RTL_FALLBACK_LANGUAGES = ['ps'];

    /**
     * Cache for RTL detection results
     */
    private static array $rtlCache = [];

    /**
     * Check if language is RTL using Filament translations
     * Falls back to hardcoded list for unsupported languages
     * Results are cached for performance
     */
    public function isRtl(string $slug): bool
    {
        return self::isLanguageRtl($slug);
    }

    /**
     * Static method to check if language is RTL
     * Uses Filament's translation system with caching
     * Can be called without service instantiation
     *
     * @param  string  $slug  Language code (e.g., 'ar', 'en', 'ps')
     * @return bool True if language is RTL
     */
    public static function isLanguageRtl(string $slug): bool
    {
        // Return cached result if available
        if (array_key_exists($slug, self::$rtlCache)) {
            return self::$rtlCache[$slug];
        }

        // Use Filament's translation system with locale parameter (doesn't change app locale)
        $direction = __('filament-panels::layout.direction', [], $slug);

        // If translation key is not found, check fallback list
        $isRtl = str_starts_with($direction, 'filament-panels::')
            ? in_array($slug, self::RTL_FALLBACK_LANGUAGES)
            : $direction === 'rtl';

        // Cache the result
        self::$rtlCache[$slug] = $isRtl;

        return $isRtl;
    }
}
