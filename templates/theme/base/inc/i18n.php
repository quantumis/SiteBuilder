<?php
/**
 * Site Builder — theme i18n.
 *
 * Self-contained translation dictionary for strings used by the generated theme.
 * Lives inside the theme (NOT the plugin), so theme works standalone if the
 * plugin is later disabled. Mirrors the locale list from similar-post.php
 * so both files speak the same language set.
 *
 * Usage in templates:
 *   <?php echo esc_html(sb_t('home')); ?>
 *
 * To add a new locale: append it to $sb_theme_translations below.
 * To add a new key: append to every locale (or at least to 'default').
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_t')) {

    global $sb_theme_translations;
    $sb_theme_translations = [
        'default' => [ // English fallback used for any unknown locale
            'home'              => 'Home',
            'privacy_policy'    => 'Privacy policy',
            'rights_reserved'   => 'All rights reserved',
            'navigation'        => 'Navigation',
            'site'              => 'Site',
            'primary_menu'      => 'Primary menu',
            'footer_menu'       => 'Footer menu',
            'back_to_top'       => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'pt_PT' => [
            'home' => 'Início', 'privacy_policy' => 'Política de privacidade', 'rights_reserved' => 'Todos os direitos reservados',
            'navigation' => 'Navegação', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu do rodapé', 'back_to_top' => 'Topo', 'articles' => 'Artigos', 'section_overview' => 'Visão geral', 'previous' => 'Anterior', 'next' => 'Seguinte', 'page_of' => 'Página %1$d de %2$d',
        ],
        'pt_BR' => [
            'home' => 'Início', 'privacy_policy' => 'Política de privacidade', 'rights_reserved' => 'Todos os direitos reservados',
            'navigation' => 'Navegação', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu do rodapé', 'back_to_top' => 'Topo', 'articles' => 'Artigos', 'section_overview' => 'Visão geral', 'previous' => 'Anterior', 'next' => 'Próxima', 'page_of' => 'Página %1$d de %2$d',
        ],
        'it_IT' => [
            'home' => 'Home', 'privacy_policy' => 'Informativa sulla privacy', 'rights_reserved' => 'Tutti i diritti riservati',
            'navigation' => 'Navigazione', 'site' => 'Sito', 'primary_menu' => 'Menu principale', 'footer_menu' => 'Menu a piè di pagina', 'back_to_top' => 'Torna su', 'articles' => 'Articoli', 'section_overview' => 'Panoramica', 'previous' => 'Precedente', 'next' => 'Successivo', 'page_of' => 'Pagina %1$d di %2$d',
        ],
        'de_DE' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fußzeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d',
        ],
        'de_AT' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fußzeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d',
        ],
        'de_CH' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fusszeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d',
        ],
        'es_ES' => [
            'home' => 'Inicio', 'privacy_policy' => 'Política de privacidad', 'rights_reserved' => 'Todos los derechos reservados',
            'navigation' => 'Navegación', 'site' => 'Sitio', 'primary_menu' => 'Menú principal', 'footer_menu' => 'Menú del pie', 'back_to_top' => 'Arriba', 'articles' => 'Artículos', 'section_overview' => 'Descripción general', 'previous' => 'Anterior', 'next' => 'Siguiente', 'page_of' => 'Página %1$d de %2$d',
        ],
        'es_PE' => [
            'home' => 'Inicio', 'privacy_policy' => 'Política de privacidad', 'rights_reserved' => 'Todos los derechos reservados',
            'navigation' => 'Navegación', 'site' => 'Sitio', 'primary_menu' => 'Menú principal', 'footer_menu' => 'Menú del pie', 'back_to_top' => 'Arriba', 'articles' => 'Artículos', 'section_overview' => 'Descripción general', 'previous' => 'Anterior', 'next' => 'Siguiente', 'page_of' => 'Página %1$d de %2$d',
        ],
        'fr_FR' => [
            'home' => 'Accueil', 'privacy_policy' => 'Politique de confidentialité', 'rights_reserved' => 'Tous droits réservés',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu de pied de page', 'back_to_top' => 'Haut de page', 'articles' => 'Articles', 'section_overview' => 'Vue d\'ensemble', 'previous' => 'Précédent', 'next' => 'Suivant', 'page_of' => 'Page %1$d sur %2$d',
        ],
        'pl_PL' => [
            'home' => 'Strona główna', 'privacy_policy' => 'Polityka prywatności', 'rights_reserved' => 'Wszelkie prawa zastrzeżone',
            'navigation' => 'Nawigacja', 'site' => 'Strona', 'primary_menu' => 'Menu główne', 'footer_menu' => 'Menu w stopce', 'back_to_top' => 'Do góry', 'articles' => 'Artykuły', 'section_overview' => 'Przegląd', 'previous' => 'Poprzednia', 'next' => 'Następna', 'page_of' => 'Strona %1$d z %2$d',
        ],
        'cs_CZ' => [
            'home' => 'Domů', 'privacy_policy' => 'Zásady ochrany osobních údajů', 'rights_reserved' => 'Všechna práva vyhrazena',
            'navigation' => 'Navigace', 'site' => 'Stránka', 'primary_menu' => 'Hlavní menu', 'footer_menu' => 'Menu v zápatí', 'back_to_top' => 'Nahoru', 'articles' => 'Články', 'section_overview' => 'Přehled', 'previous' => 'Předchozí', 'next' => 'Další', 'page_of' => 'Stránka %1$d z %2$d',
        ],
        'da_DK' => [
            'home' => 'Hjem', 'privacy_policy' => 'Privatlivspolitik', 'rights_reserved' => 'Alle rettigheder forbeholdes',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Hovedmenu', 'footer_menu' => 'Footer-menu', 'back_to_top' => 'Til toppen', 'articles' => 'Artikler', 'section_overview' => 'Oversigt', 'previous' => 'Forrige', 'next' => 'Næste', 'page_of' => 'Side %1$d af %2$d',
        ],
        'nl_NL' => [
            'home' => 'Home', 'privacy_policy' => 'Privacybeleid', 'rights_reserved' => 'Alle rechten voorbehouden',
            'navigation' => 'Navigatie', 'site' => 'Site', 'primary_menu' => 'Hoofdmenu', 'footer_menu' => 'Voettekstmenu', 'back_to_top' => 'Naar boven', 'articles' => 'Artikelen', 'section_overview' => 'Overzicht', 'previous' => 'Vorige', 'next' => 'Volgende', 'page_of' => 'Pagina %1\\$d van %2\\$d',
        ],
        'nl_BE' => [
            'home' => 'Home', 'privacy_policy' => 'Privacybeleid', 'rights_reserved' => 'Alle rechten voorbehouden',
            'navigation' => 'Navigatie', 'site' => 'Site', 'primary_menu' => 'Hoofdmenu', 'footer_menu' => 'Voettekstmenu', 'back_to_top' => 'Naar boven', 'articles' => 'Artikelen', 'section_overview' => 'Overzicht', 'previous' => 'Vorige', 'next' => 'Volgende', 'page_of' => 'Pagina %1\\$d van %2\\$d',
        ],
        'el' => [
            'home' => 'Αρχική', 'privacy_policy' => 'Πολιτική απορρήτου', 'rights_reserved' => 'Με επιφύλαξη παντός δικαιώματος',
            'navigation' => 'Πλοήγηση', 'site' => 'Ιστότοπος', 'primary_menu' => 'Κύριο μενού', 'footer_menu' => 'Μενού υποσέλιδου', 'back_to_top' => 'Στην κορυφή', 'articles' => 'Άρθρα', 'section_overview' => 'Επισκόπηση', 'previous' => 'Προηγούμενο', 'next' => 'Επόμενο', 'page_of' => 'Σελίδα %1$d από %2$d',
        ],
        'ro_RO' => [
            'home' => 'Acasă', 'privacy_policy' => 'Politica de confidențialitate', 'rights_reserved' => 'Toate drepturile rezervate',
            'navigation' => 'Navigare', 'site' => 'Site', 'primary_menu' => 'Meniu principal', 'footer_menu' => 'Meniu subsol', 'back_to_top' => 'Sus', 'articles' => 'Articole', 'section_overview' => 'Prezentare generală', 'previous' => 'Anterior', 'next' => 'Următor', 'page_of' => 'Pagina %1$d din %2$d',
        ],
        'sv_SE' => [
            'home' => 'Hem', 'privacy_policy' => 'Integritetspolicy', 'rights_reserved' => 'Alla rättigheter förbehållna',
            'navigation' => 'Navigation', 'site' => 'Webbplats', 'primary_menu' => 'Huvudmeny', 'footer_menu' => 'Sidfotsmeny', 'back_to_top' => 'Till toppen', 'articles' => 'Artiklar', 'section_overview' => 'Översikt', 'previous' => 'Föregående', 'next' => 'Nästa', 'page_of' => 'Sida %1$d av %2$d',
        ],
        'fi_FI' => [
            'home' => 'Etusivu', 'privacy_policy' => 'Tietosuojakäytäntö', 'rights_reserved' => 'Kaikki oikeudet pidätetään',
            'navigation' => 'Navigointi', 'site' => 'Sivusto', 'primary_menu' => 'Päävalikko', 'footer_menu' => 'Alatunnisteen valikko', 'back_to_top' => 'Ylös', 'articles' => 'Artikkelit', 'section_overview' => 'Yleiskuvaus', 'previous' => 'Edellinen', 'next' => 'Seuraava', 'page_of' => 'Sivu %1$d / %2$d',
        ],
        'bg_BG' => [
            'home' => 'Начало', 'privacy_policy' => 'Политика за поверителност', 'rights_reserved' => 'Всички права запазени',
            'navigation' => 'Навигация', 'site' => 'Сайт', 'primary_menu' => 'Главно меню', 'footer_menu' => 'Меню в долен колонтитул', 'back_to_top' => 'Нагоре', 'articles' => 'Статии', 'section_overview' => 'Общ преглед', 'previous' => 'Предишна', 'next' => 'Следваща', 'page_of' => 'Страница %1$d от %2$d',
        ],
        'en_IE' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'en_CA' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'en_AU' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'en_US' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'en_GB' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'en_NZ' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d',
        ],
        'et_EE' => [
            'home' => 'Avaleht', 'privacy_policy' => 'Privaatsuspoliitika', 'rights_reserved' => 'Kõik õigused kaitstud',
            'navigation' => 'Navigeerimine', 'site' => 'Sait', 'primary_menu' => 'Peamenüü', 'footer_menu' => 'Jaluse menüü', 'back_to_top' => 'Üles', 'articles' => 'Artiklid', 'section_overview' => 'Ülevaade', 'previous' => 'Eelmine', 'next' => 'Järgmine', 'page_of' => 'Lehekülg %1$d / %2$d',
        ],
        'sl_SI' => [
            'home' => 'Domov', 'privacy_policy' => 'Politika zasebnosti', 'rights_reserved' => 'Vse pravice pridržane',
            'navigation' => 'Navigacija', 'site' => 'Spletna stran', 'primary_menu' => 'Glavni meni', 'footer_menu' => 'Meni v nogi', 'back_to_top' => 'Na vrh', 'articles' => 'Članki', 'section_overview' => 'Pregled', 'previous' => 'Prejšnja', 'next' => 'Naslednja', 'page_of' => 'Stran %1$d od %2$d',
        ],
        'sk_SK' => [
            'home' => 'Domov', 'privacy_policy' => 'Zásady ochrany osobných údajov', 'rights_reserved' => 'Všetky práva vyhradené',
            'navigation' => 'Navigácia', 'site' => 'Stránka', 'primary_menu' => 'Hlavné menu', 'footer_menu' => 'Menu v päte', 'back_to_top' => 'Nahor', 'articles' => 'Články', 'section_overview' => 'Prehľad', 'previous' => 'Predchádzajúca', 'next' => 'Ďalšia', 'page_of' => 'Stránka %1$d z %2$d',
        ],
        'hr_HR' => [
            'home' => 'Početna', 'privacy_policy' => 'Pravila o privatnosti', 'rights_reserved' => 'Sva prava pridržana',
            'navigation' => 'Navigacija', 'site' => 'Stranica', 'primary_menu' => 'Glavni izbornik', 'footer_menu' => 'Izbornik podnožja', 'back_to_top' => 'Na vrh', 'articles' => 'Članci', 'section_overview' => 'Pregled', 'previous' => 'Prethodna', 'next' => 'Sljedeća', 'page_of' => 'Stranica %1$d od %2$d',
        ],
        'hu_HU' => [
            'home' => 'Kezdőlap', 'privacy_policy' => 'Adatvédelmi szabályzat', 'rights_reserved' => 'Minden jog fenntartva',
            'navigation' => 'Navigáció', 'site' => 'Webhely', 'primary_menu' => 'Főmenü', 'footer_menu' => 'Lábléc menü', 'back_to_top' => 'Vissza a tetejére', 'articles' => 'Cikkek', 'section_overview' => 'Áttekintés', 'previous' => 'Előző', 'next' => 'Következő', 'page_of' => 'Oldal %1$d / %2$d',
        ],
        'is_IS' => [
            'home' => 'Heim', 'privacy_policy' => 'Persónuverndarstefna', 'rights_reserved' => 'Allur réttur áskilinn',
            'navigation' => 'Leiðsögn', 'site' => 'Vefur', 'primary_menu' => 'Aðalvalmynd', 'footer_menu' => 'Fótvalmynd', 'back_to_top' => 'Upp', 'articles' => 'Greinar', 'section_overview' => 'Yfirlit', 'previous' => 'Fyrri', 'next' => 'Næsta', 'page_of' => 'Síða %1$d af %2$d',
        ],
        'lb_LU' => [
            'home' => 'Doheem', 'privacy_policy' => 'Dateschutz', 'rights_reserved' => 'All Rechter virbehalen',
            'navigation' => 'Navigatioun', 'site' => 'Säit', 'primary_menu' => 'Haaptmenü', 'footer_menu' => 'Fousszeilmenü', 'back_to_top' => 'No uewen', 'articles' => 'Artikelen', 'section_overview' => 'Iwwersiicht', 'previous' => 'Zréck', 'next' => 'Weider', 'page_of' => 'Säit %1$d vu %2$d',
        ],
        'lv' => [
            'home' => 'Sākums', 'privacy_policy' => 'Konfidencialitātes politika', 'rights_reserved' => 'Visas tiesības aizsargātas',
            'navigation' => 'Navigācija', 'site' => 'Vietne', 'primary_menu' => 'Galvenā izvēlne', 'footer_menu' => 'Kājenes izvēlne', 'back_to_top' => 'Uz augšu', 'articles' => 'Raksti', 'section_overview' => 'Pārskats', 'previous' => 'Iepriekšējā', 'next' => 'Nākamā', 'page_of' => 'Lapa %1$d no %2$d',
        ],
        'nb_NO' => [
            'home' => 'Hjem', 'privacy_policy' => 'Personvernerklæring', 'rights_reserved' => 'Alle rettigheter forbeholdt',
            'navigation' => 'Navigasjon', 'site' => 'Nettsted', 'primary_menu' => 'Hovedmeny', 'footer_menu' => 'Bunntekstmeny', 'back_to_top' => 'Til toppen', 'articles' => 'Artikler', 'section_overview' => 'Oversikt', 'previous' => 'Forrige', 'next' => 'Neste', 'page_of' => 'Side %1$d av %2$d',
        ],
        'tr_TR' => [
            'home' => 'Ana sayfa', 'privacy_policy' => 'Gizlilik politikası', 'rights_reserved' => 'Tüm hakları saklıdır',
            'navigation' => 'Gezinti', 'site' => 'Site', 'primary_menu' => 'Ana menü', 'footer_menu' => 'Alt bilgi menüsü', 'back_to_top' => 'Yukarı', 'articles' => 'Makaleler', 'section_overview' => 'Genel bakış', 'previous' => 'Önceki', 'next' => 'Sonraki', 'page_of' => 'Sayfa %1$d / %2$d',
        ],
    ];

    /**
     * Translate a key for the current WordPress locale. Falls back to English
     * ('default') for unknown locales or missing keys.
     */
    function sb_t($key) {
        global $sb_theme_translations;
        $locale = get_locale();
        if (isset($sb_theme_translations[$locale][$key])) {
            return $sb_theme_translations[$locale][$key];
        }
        if (isset($sb_theme_translations['default'][$key])) {
            return $sb_theme_translations['default'][$key];
        }
        return $key;
    }
}
