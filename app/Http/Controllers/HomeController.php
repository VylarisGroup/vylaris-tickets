<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        $reseller = app('tenant.reseller');
        $q = trim((string) $request->query('q'));
        $category = (string) $request->query('kategorie');
        $category = array_key_exists($category, config('tickets.categories')) ? $category : '';

        $base = Event::query()
            ->where('status', 'published')->where('is_blocked', false)->where('is_private', false)
            ->where('starts_at', '>=', now()->subDay())
            ->whereHas('organizer', function ($query) use ($reseller) {
                $query->where('is_active', true);
                $reseller ? $query->where('reseller_id', $reseller->id) : $query->whereNull('reseller_id');
            });

        // Nur Kategorien anbieten, in denen es auch Events gibt.
        $categories = (clone $base)->whereNotNull('category')->distinct()->pluck('category')
            ->filter(fn ($c) => isset(config('tickets.categories')[$c]))->values();

        $events = $base->with('organizer')
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('city', 'like', $like)->orWhere('venue_name', 'like', $like));
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('starts_at')->limit(60)->get();

        return view('home', compact('events', 'categories', 'q', 'category'));
    }
}
