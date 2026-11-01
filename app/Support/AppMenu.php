<?php

namespace App\Support;

use App\Models\User;
use App\Support\Challenges\Challenges;
use App\Support\Messenger\Messenger;
use App\Support\Store\StoreCart;
use App\Support\Store\StoreSettings;
use Illuminate\Http\Request;

/** Menu latéral du fil : une entrée par espace ouvert au membre, dans l'ordre d'affichage. */
final class AppMenu
{
    /**
     * « Mon bureau » pour un titulaire de poste, « Mon club » pour un simple membre ou inscrit.
     */
    public static function officeLabel(User $user): string
    {
        return MyOffice::holdsPosition($user) ? 'Mon bureau' : 'Mon club';
    }

    /**
     * @return array<int, array{key: string, label: string, href: string, icon: string, active: bool, badge: int}>
     */
    public static function for(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        $item = static fn (string $key, string $label, string $href, string $icon, bool $active = false, int $badge = 0): array => compact('key', 'label', 'href', 'icon', 'active', 'badge');

        $items = [$item('home', 'Accueil', route('home'), 'fa-solid fa-house', true)];

        if (Challenges::enabled()) {
            $items[] = $item('challenges', 'Défis', route('challenges.index'), 'fa-solid fa-trophy');
        }

        if (MyOffice::enabled() && (MyOffice::holdsPosition($user) || MyOfficeSpace::hasActivity($user))) {
            $holds = MyOffice::holdsPosition($user);
            $items[] = $item('office', self::officeLabel($user), route('office.index'), $holds ? 'fa-solid fa-briefcase' : 'fa-solid fa-people-group');
        }

        if (Messenger::enabled()) {
            $items[] = $item('messenger', 'T.Link Messenger', route('messenger.index'), 'fa-regular fa-comments', false, Messenger::unreadCount($user));
        }

        if (StoreSettings::enabled()) {
            $items[] = $item('store', 'Tunistore', route('store.index'), 'fa-solid fa-bag-shopping', false, StoreCart::count($user));
        }

        $items[] = $item('academy', 'Tunivisions Academy', route('academy.index'), 'fa-solid fa-graduation-cap');

        if (AdminMenu::hasAccess(AdminContext::of($request))) {
            $items[] = $item('admin', 'Administration', route('admin.dashboard'), 'fa-solid fa-list-check');
        }

        return $items;
    }
}
