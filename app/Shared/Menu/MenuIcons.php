<?php

declare(strict_types=1);

namespace App\Shared\Menu;

/**
 * Noms d'icônes qu'une organisation peut choisir pour une entrée de menu.
 *
 * Une icône est un composant React : la base n'en stocke que le **nom**, et
 * `frontend/src/modules/menu/components/menuIcons.ts` le résout. Cette liste
 * est donc le miroir exact de la table de ce fichier, et le test
 * `MenuIconsTest` refuse qu'elles divergent.
 *
 * Elle sert à valider ce qu'un administrateur choisit : un nom absent de la
 * table frontend retomberait sur l'icône neutre, et l'écran de réglage
 * mentirait sur le résultat. Mieux vaut refuser la saisie que l'afficher
 * autrement qu'annoncé.
 */
final class MenuIcons
{
    /** @var list<string> */
    public const NAMES = [
        'ArrowRightLeft',
        'BarChart3',
        'Bell',
        'Bookmark',
        'Boxes',
        'Building2',
        'Calculator',
        'CalendarRange',
        'ClipboardList',
        'Clock',
        'CreditCard',
        'FileInput',
        'FileOutput',
        'FileText',
        'Flag',
        'Folder',
        'Globe',
        'HandCoins',
        'History',
        'Home',
        'IdCard',
        'Inbox',
        'KeyRound',
        'Layers',
        'LayoutDashboard',
        'List',
        'Lock',
        'Mail',
        'Map',
        'MapPin',
        'MessageSquareWarning',
        'Network',
        'Package',
        'Phone',
        'PieChart',
        'Plug',
        'ReceiptText',
        'Route',
        'Search',
        'Send',
        'Settings',
        'Shield',
        'Star',
        'Tags',
        'Target',
        'Truck',
        'Users',
        'Variable',
        'Wallet',
        'Warehouse',
        'Workflow',
        'Wrench',
        'Zap',
    ];

    public static function knows(string $name): bool
    {
        return in_array($name, self::NAMES, true);
    }
}
