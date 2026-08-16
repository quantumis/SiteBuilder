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
            'back_to_top'       => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'pt_PT' => [
            'home' => 'Início', 'privacy_policy' => 'Política de privacidade', 'rights_reserved' => 'Todos os direitos reservados',
            'navigation' => 'Navegação', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu do rodapé', 'back_to_top' => 'Topo', 'articles' => 'Artigos', 'section_overview' => 'Visão geral', 'previous' => 'Anterior', 'next' => 'Seguinte', 'page_of' => 'Página %1$d de %2$d', 'legal' => 'Informações legais', 'contact' => 'Contactos', 'close' => 'Fechar', 'not_found_title' => 'Página não encontrada', 'not_found_message' => 'A página que procurava pode ter sido movida ou já não existe.', 'back_home' => 'Voltar à página inicial', 'toc' => 'Índice', 'content_by_team' => 'Conteúdo criado pela equipa %s', 'page_n' => 'Página %d', 'regulators' => 'Reguladores',
        ],
        'pt_BR' => [
            'home' => 'Início', 'privacy_policy' => 'Política de privacidade', 'rights_reserved' => 'Todos os direitos reservados',
            'navigation' => 'Navegação', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu do rodapé', 'back_to_top' => 'Topo', 'articles' => 'Artigos', 'section_overview' => 'Visão geral', 'previous' => 'Anterior', 'next' => 'Próxima', 'page_of' => 'Página %1$d de %2$d', 'legal' => 'Informações legais', 'contact' => 'Contato', 'close' => 'Fechar', 'not_found_title' => 'Página não encontrada', 'not_found_message' => 'A página que você procurava pode ter sido movida ou não existe mais.', 'back_home' => 'Voltar ao início', 'toc' => 'Sumário', 'content_by_team' => 'Conteúdo criado pela equipe %s', 'page_n' => 'Página %d', 'regulators' => 'Reguladores',
        ],
        'it_IT' => [
            'home' => 'Home', 'privacy_policy' => 'Informativa sulla privacy', 'rights_reserved' => 'Tutti i diritti riservati',
            'navigation' => 'Navigazione', 'site' => 'Sito', 'primary_menu' => 'Menu principale', 'footer_menu' => 'Menu a piè di pagina', 'back_to_top' => 'Torna su', 'articles' => 'Articoli', 'section_overview' => 'Panoramica', 'previous' => 'Precedente', 'next' => 'Successivo', 'page_of' => 'Pagina %1$d di %2$d', 'legal' => 'Note legali', 'contact' => 'Contatti', 'close' => 'Chiudi', 'not_found_title' => 'Pagina non trovata', 'not_found_message' => 'La pagina che cercavi potrebbe essere stata spostata o non esiste più.', 'back_home' => 'Torna alla home', 'toc' => 'Indice', 'content_by_team' => 'Contenuto creato dal team %s', 'page_n' => 'Pagina %d', 'regulators' => 'Regolatori',
        ],
        'de_DE' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fußzeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d', 'legal' => 'Rechtliches', 'contact' => 'Kontakt', 'close' => 'Schließen', 'not_found_title' => 'Seite nicht gefunden', 'not_found_message' => 'Die gesuchte Seite wurde möglicherweise verschoben oder existiert nicht mehr.', 'back_home' => 'Zur Startseite', 'toc' => 'Inhaltsverzeichnis', 'content_by_team' => 'Inhalt erstellt vom Team %s', 'page_n' => 'Seite %d', 'regulators' => 'Regulierungsbehörden',
        ],
        'de_AT' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fußzeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d', 'legal' => 'Rechtliches', 'contact' => 'Kontakt', 'close' => 'Schließen', 'not_found_title' => 'Seite nicht gefunden', 'not_found_message' => 'Die gesuchte Seite wurde möglicherweise verschoben oder existiert nicht mehr.', 'back_home' => 'Zur Startseite', 'toc' => 'Inhaltsverzeichnis', 'content_by_team' => 'Inhalt erstellt vom Team %s', 'page_n' => 'Seite %d', 'regulators' => 'Regulierungsbehörden',
        ],
        'de_CH' => [
            'home' => 'Startseite', 'privacy_policy' => 'Datenschutz', 'rights_reserved' => 'Alle Rechte vorbehalten',
            'navigation' => 'Navigation', 'site' => 'Seite', 'primary_menu' => 'Hauptmenü', 'footer_menu' => 'Fusszeilenmenü', 'back_to_top' => 'Nach oben', 'articles' => 'Artikel', 'section_overview' => 'Übersicht', 'previous' => 'Zurück', 'next' => 'Weiter', 'page_of' => 'Seite %1$d von %2$d', 'legal' => 'Rechtliches', 'contact' => 'Kontakt', 'close' => 'Schliessen', 'not_found_title' => 'Seite nicht gefunden', 'not_found_message' => 'Die gesuchte Seite wurde möglicherweise verschoben oder existiert nicht mehr.', 'back_home' => 'Zur Startseite', 'toc' => 'Inhaltsverzeichnis', 'content_by_team' => 'Inhalt erstellt vom Team %s', 'page_n' => 'Seite %d', 'regulators' => 'Regulierungsbehörden',
        ],
        'es_ES' => [
            'home' => 'Inicio', 'privacy_policy' => 'Política de privacidad', 'rights_reserved' => 'Todos los derechos reservados',
            'navigation' => 'Navegación', 'site' => 'Sitio', 'primary_menu' => 'Menú principal', 'footer_menu' => 'Menú del pie', 'back_to_top' => 'Arriba', 'articles' => 'Artículos', 'section_overview' => 'Descripción general', 'previous' => 'Anterior', 'next' => 'Siguiente', 'page_of' => 'Página %1$d de %2$d', 'legal' => 'Aviso legal', 'contact' => 'Contacto', 'close' => 'Cerrar', 'not_found_title' => 'Página no encontrada', 'not_found_message' => 'La página que buscabas puede haber sido movida o ya no existe.', 'back_home' => 'Volver al inicio', 'toc' => 'Índice', 'content_by_team' => 'Contenido creado por el equipo de %s', 'page_n' => 'Página %d', 'regulators' => 'Reguladores',
        ],
        'es_PE' => [
            'home' => 'Inicio', 'privacy_policy' => 'Política de privacidad', 'rights_reserved' => 'Todos los derechos reservados',
            'navigation' => 'Navegación', 'site' => 'Sitio', 'primary_menu' => 'Menú principal', 'footer_menu' => 'Menú del pie', 'back_to_top' => 'Arriba', 'articles' => 'Artículos', 'section_overview' => 'Descripción general', 'previous' => 'Anterior', 'next' => 'Siguiente', 'page_of' => 'Página %1$d de %2$d', 'legal' => 'Aviso legal', 'contact' => 'Contacto', 'close' => 'Cerrar', 'not_found_title' => 'Página no encontrada', 'not_found_message' => 'La página que buscabas puede haber sido movida o ya no existe.', 'back_home' => 'Volver al inicio', 'toc' => 'Índice', 'content_by_team' => 'Contenido creado por el equipo de %s', 'page_n' => 'Página %d', 'regulators' => 'Reguladores',
        ],
        'fr_FR' => [
            'home' => 'Accueil', 'privacy_policy' => 'Politique de confidentialité', 'rights_reserved' => 'Tous droits réservés',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Menu principal', 'footer_menu' => 'Menu de pied de page', 'back_to_top' => 'Haut de page', 'articles' => 'Articles', 'section_overview' => 'Vue d\'ensemble', 'previous' => 'Précédent', 'next' => 'Suivant', 'page_of' => 'Page %1$d sur %2$d', 'legal' => 'Mentions légales', 'contact' => 'Contact', 'close' => 'Fermer', 'not_found_title' => 'Page introuvable', 'not_found_message' => 'La page que vous cherchiez a peut-être été déplacée ou n\'existe plus.', 'back_home' => 'Retour à l\'accueil', 'toc' => 'Sommaire', 'content_by_team' => 'Contenu créé par l\'équipe %s', 'page_n' => 'Page %d', 'regulators' => 'Régulateurs',
        ],
        'pl_PL' => [
            'home' => 'Strona główna', 'privacy_policy' => 'Polityka prywatności', 'rights_reserved' => 'Wszelkie prawa zastrzeżone',
            'navigation' => 'Nawigacja', 'site' => 'Strona', 'primary_menu' => 'Menu główne', 'footer_menu' => 'Menu w stopce', 'back_to_top' => 'Do góry', 'articles' => 'Artykuły', 'section_overview' => 'Przegląd', 'previous' => 'Poprzednia', 'next' => 'Następna', 'page_of' => 'Strona %1$d z %2$d', 'legal' => 'Informacje prawne', 'contact' => 'Kontakt', 'close' => 'Zamknij', 'not_found_title' => 'Strona nie znaleziona', 'not_found_message' => 'Szukana strona mogła zostać przeniesiona lub już nie istnieje.', 'back_home' => 'Powrót do strony głównej', 'toc' => 'Spis treści', 'content_by_team' => 'Treść stworzona przez zespół %s', 'page_n' => 'Strona %d', 'regulators' => 'Regulatorzy',
        ],
        'cs_CZ' => [
            'home' => 'Domů', 'privacy_policy' => 'Zásady ochrany osobních údajů', 'rights_reserved' => 'Všechna práva vyhrazena',
            'navigation' => 'Navigace', 'site' => 'Stránka', 'primary_menu' => 'Hlavní menu', 'footer_menu' => 'Menu v zápatí', 'back_to_top' => 'Nahoru', 'articles' => 'Články', 'section_overview' => 'Přehled', 'previous' => 'Předchozí', 'next' => 'Další', 'page_of' => 'Stránka %1$d z %2$d', 'legal' => 'Právní informace', 'contact' => 'Kontakt', 'close' => 'Zavřít', 'not_found_title' => 'Stránka nebyla nalezena', 'not_found_message' => 'Hledaná stránka mohla být přesunuta nebo již neexistuje.', 'back_home' => 'Zpět na hlavní stránku', 'toc' => 'Obsah', 'content_by_team' => 'Obsah vytvořil tým %s', 'page_n' => 'Stránka %d', 'regulators' => 'Regulátoři',
        ],
        'da_DK' => [
            'home' => 'Hjem', 'privacy_policy' => 'Privatlivspolitik', 'rights_reserved' => 'Alle rettigheder forbeholdes',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Hovedmenu', 'footer_menu' => 'Footer-menu', 'back_to_top' => 'Til toppen', 'articles' => 'Artikler', 'section_overview' => 'Oversigt', 'previous' => 'Forrige', 'next' => 'Næste', 'page_of' => 'Side %1$d af %2$d', 'legal' => 'Juridisk', 'contact' => 'Kontakt', 'close' => 'Luk', 'not_found_title' => 'Siden blev ikke fundet', 'not_found_message' => 'Den side, du ledte efter, kan være flyttet eller findes ikke længere.', 'back_home' => 'Tilbage til forsiden', 'toc' => 'Indholdsfortegnelse', 'content_by_team' => 'Indhold skabt af %s-teamet', 'page_n' => 'Side %d', 'regulators' => 'Regulatorer',
        ],
        'nl_NL' => [
            'home' => 'Home', 'privacy_policy' => 'Privacybeleid', 'rights_reserved' => 'Alle rechten voorbehouden',
            'navigation' => 'Navigatie', 'site' => 'Site', 'primary_menu' => 'Hoofdmenu', 'footer_menu' => 'Voettekstmenu', 'back_to_top' => 'Naar boven', 'articles' => 'Artikelen', 'section_overview' => 'Overzicht', 'previous' => 'Vorige', 'next' => 'Volgende', 'page_of' => 'Pagina %1\\$d van %2\\$d', 'legal' => 'Juridisch', 'contact' => 'Contact', 'close' => 'Sluiten', 'not_found_title' => 'Pagina niet gevonden', 'not_found_message' => 'De pagina die je zocht is mogelijk verplaatst of bestaat niet meer.', 'back_home' => 'Terug naar de startpagina', 'toc' => 'Inhoudsopgave', 'content_by_team' => 'Inhoud gemaakt door het %s-team', 'page_n' => 'Pagina %d', 'regulators' => 'Toezichthouders',
        ],
        'nl_BE' => [
            'home' => 'Home', 'privacy_policy' => 'Privacybeleid', 'rights_reserved' => 'Alle rechten voorbehouden',
            'navigation' => 'Navigatie', 'site' => 'Site', 'primary_menu' => 'Hoofdmenu', 'footer_menu' => 'Voettekstmenu', 'back_to_top' => 'Naar boven', 'articles' => 'Artikelen', 'section_overview' => 'Overzicht', 'previous' => 'Vorige', 'next' => 'Volgende', 'page_of' => 'Pagina %1\\$d van %2\\$d', 'legal' => 'Juridisch', 'contact' => 'Contact', 'close' => 'Sluiten', 'not_found_title' => 'Pagina niet gevonden', 'not_found_message' => 'De pagina die je zocht is mogelijk verplaatst of bestaat niet meer.', 'back_home' => 'Terug naar de startpagina', 'toc' => 'Inhoudsopgave', 'content_by_team' => 'Inhoud gemaakt door het %s-team', 'page_n' => 'Pagina %d', 'regulators' => 'Toezichthouders',
        ],
        'el' => [
            'home' => 'Αρχική', 'privacy_policy' => 'Πολιτική απορρήτου', 'rights_reserved' => 'Με επιφύλαξη παντός δικαιώματος',
            'navigation' => 'Πλοήγηση', 'site' => 'Ιστότοπος', 'primary_menu' => 'Κύριο μενού', 'footer_menu' => 'Μενού υποσέλιδου', 'back_to_top' => 'Στην κορυφή', 'articles' => 'Άρθρα', 'section_overview' => 'Επισκόπηση', 'previous' => 'Προηγούμενο', 'next' => 'Επόμενο', 'page_of' => 'Σελίδα %1$d από %2$d', 'legal' => 'Νομικά', 'contact' => 'Επικοινωνία', 'close' => 'Κλείσιμο', 'not_found_title' => 'Η σελίδα δεν βρέθηκε', 'not_found_message' => 'Η σελίδα που αναζητούσατε μπορεί να έχει μετακινηθεί ή να μην υπάρχει πλέον.', 'back_home' => 'Επιστροφή στην αρχική', 'toc' => 'Περιεχόμενα', 'content_by_team' => 'Το περιεχόμενο δημιουργήθηκε από την ομάδα %s', 'page_n' => 'Σελίδα %d', 'regulators' => 'Ρυθμιστικές αρχές',
        ],
        'ro_RO' => [
            'home' => 'Acasă', 'privacy_policy' => 'Politica de confidențialitate', 'rights_reserved' => 'Toate drepturile rezervate',
            'navigation' => 'Navigare', 'site' => 'Site', 'primary_menu' => 'Meniu principal', 'footer_menu' => 'Meniu subsol', 'back_to_top' => 'Sus', 'articles' => 'Articole', 'section_overview' => 'Prezentare generală', 'previous' => 'Anterior', 'next' => 'Următor', 'page_of' => 'Pagina %1$d din %2$d', 'legal' => 'Informații legale', 'contact' => 'Contact', 'close' => 'Închide', 'not_found_title' => 'Pagină negăsită', 'not_found_message' => 'Pagina pe care o căutai poate fi mutată sau nu mai există.', 'back_home' => 'Înapoi la pagina principală', 'toc' => 'Cuprins', 'content_by_team' => 'Conținut creat de echipa %s', 'page_n' => 'Pagina %d', 'regulators' => 'Autorități de reglementare',
        ],
        'sv_SE' => [
            'home' => 'Hem', 'privacy_policy' => 'Integritetspolicy', 'rights_reserved' => 'Alla rättigheter förbehållna',
            'navigation' => 'Navigation', 'site' => 'Webbplats', 'primary_menu' => 'Huvudmeny', 'footer_menu' => 'Sidfotsmeny', 'back_to_top' => 'Till toppen', 'articles' => 'Artiklar', 'section_overview' => 'Översikt', 'previous' => 'Föregående', 'next' => 'Nästa', 'page_of' => 'Sida %1$d av %2$d', 'legal' => 'Juridiskt', 'contact' => 'Kontakt', 'close' => 'Stäng', 'not_found_title' => 'Sidan hittades inte', 'not_found_message' => 'Sidan du letade efter kan ha flyttats eller finns inte längre.', 'back_home' => 'Tillbaka till startsidan', 'toc' => 'Innehåll', 'content_by_team' => 'Innehållet skapat av %s-teamet', 'page_n' => 'Sida %d', 'regulators' => 'Tillsynsmyndigheter',
        ],
        'fi_FI' => [
            'home' => 'Etusivu', 'privacy_policy' => 'Tietosuojakäytäntö', 'rights_reserved' => 'Kaikki oikeudet pidätetään',
            'navigation' => 'Navigointi', 'site' => 'Sivusto', 'primary_menu' => 'Päävalikko', 'footer_menu' => 'Alatunnisteen valikko', 'back_to_top' => 'Ylös', 'articles' => 'Artikkelit', 'section_overview' => 'Yleiskuvaus', 'previous' => 'Edellinen', 'next' => 'Seuraava', 'page_of' => 'Sivu %1$d / %2$d', 'legal' => 'Oikeudellinen', 'contact' => 'Yhteystiedot', 'close' => 'Sulje', 'not_found_title' => 'Sivua ei löytynyt', 'not_found_message' => 'Etsimäsi sivu on saatettu siirtää tai sitä ei enää ole olemassa.', 'back_home' => 'Palaa etusivulle', 'toc' => 'Sisällysluettelo', 'content_by_team' => 'Sisällön on luonut %s-tiimi', 'page_n' => 'Sivu %d', 'regulators' => 'Valvontaviranomaiset',
        ],
        'bg_BG' => [
            'home' => 'Начало', 'privacy_policy' => 'Политика за поверителност', 'rights_reserved' => 'Всички права запазени',
            'navigation' => 'Навигация', 'site' => 'Сайт', 'primary_menu' => 'Главно меню', 'footer_menu' => 'Меню в долен колонтитул', 'back_to_top' => 'Нагоре', 'articles' => 'Статии', 'section_overview' => 'Общ преглед', 'previous' => 'Предишна', 'next' => 'Следваща', 'page_of' => 'Страница %1$d от %2$d', 'legal' => 'Правна информация', 'contact' => 'Контакти', 'close' => 'Затвори', 'not_found_title' => 'Страницата не е намерена', 'not_found_message' => 'Страницата, която търсите, може да е преместена или вече не съществува.', 'back_home' => 'Обратно към началото', 'toc' => 'Съдържание', 'content_by_team' => 'Съдържанието е създадено от екипа на %s', 'page_n' => 'Страница %d', 'regulators' => 'Регулаторни органи',
        ],
        'en_IE' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'en_CA' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'en_AU' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'en_US' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'en_GB' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'en_NZ' => [
            'home' => 'Home', 'privacy_policy' => 'Privacy policy', 'rights_reserved' => 'All rights reserved',
            'navigation' => 'Navigation', 'site' => 'Site', 'primary_menu' => 'Primary menu', 'footer_menu' => 'Footer menu', 'back_to_top' => 'Back to top', 'articles' => 'Articles', 'section_overview' => 'Section overview', 'previous' => 'Previous', 'next' => 'Next', 'page_of' => 'Page %1$d of %2$d', 'legal' => 'Legal', 'contact' => 'Contact', 'close' => 'Close', 'not_found_title' => 'Page not found', 'not_found_message' => 'The page you were looking for may have been moved or no longer exists.', 'back_home' => 'Back to home', 'toc' => 'Table of contents', 'content_by_team' => 'Content created by the %s team', 'page_n' => 'Page %d', 'regulators' => 'Regulators',
        ],
        'et_EE' => [
            'home' => 'Avaleht', 'privacy_policy' => 'Privaatsuspoliitika', 'rights_reserved' => 'Kõik õigused kaitstud',
            'navigation' => 'Navigeerimine', 'site' => 'Sait', 'primary_menu' => 'Peamenüü', 'footer_menu' => 'Jaluse menüü', 'back_to_top' => 'Üles', 'articles' => 'Artiklid', 'section_overview' => 'Ülevaade', 'previous' => 'Eelmine', 'next' => 'Järgmine', 'page_of' => 'Lehekülg %1$d / %2$d', 'legal' => 'Juriidiline', 'contact' => 'Kontakt', 'close' => 'Sulge', 'not_found_title' => 'Lehte ei leitud', 'not_found_message' => 'Otsitud leht võib olla teisaldatud või seda enam ei eksisteeri.', 'back_home' => 'Tagasi avalehele', 'toc' => 'Sisukord', 'content_by_team' => 'Sisu on loonud %s meeskond', 'page_n' => 'Lehekülg %d', 'regulators' => 'Regulaatorid',
        ],
        'sl_SI' => [
            'home' => 'Domov', 'privacy_policy' => 'Politika zasebnosti', 'rights_reserved' => 'Vse pravice pridržane',
            'navigation' => 'Navigacija', 'site' => 'Spletna stran', 'primary_menu' => 'Glavni meni', 'footer_menu' => 'Meni v nogi', 'back_to_top' => 'Na vrh', 'articles' => 'Članki', 'section_overview' => 'Pregled', 'previous' => 'Prejšnja', 'next' => 'Naslednja', 'page_of' => 'Stran %1$d od %2$d', 'legal' => 'Pravno', 'contact' => 'Stik', 'close' => 'Zapri', 'not_found_title' => 'Strani ni bilo mogoče najti', 'not_found_message' => 'Iskana stran je bila morda premaknjena ali ne obstaja več.', 'back_home' => 'Nazaj na začetno stran', 'toc' => 'Kazalo', 'content_by_team' => 'Vsebino je ustvarila ekipa %s', 'page_n' => 'Stran %d', 'regulators' => 'Regulatorji',
        ],
        'sk_SK' => [
            'home' => 'Domov', 'privacy_policy' => 'Zásady ochrany osobných údajov', 'rights_reserved' => 'Všetky práva vyhradené',
            'navigation' => 'Navigácia', 'site' => 'Stránka', 'primary_menu' => 'Hlavné menu', 'footer_menu' => 'Menu v päte', 'back_to_top' => 'Nahor', 'articles' => 'Články', 'section_overview' => 'Prehľad', 'previous' => 'Predchádzajúca', 'next' => 'Ďalšia', 'page_of' => 'Stránka %1$d z %2$d', 'legal' => 'Právne informácie', 'contact' => 'Kontakt', 'close' => 'Zavrieť', 'not_found_title' => 'Stránka nebola nájdená', 'not_found_message' => 'Hľadaná stránka mohla byť presunutá alebo už neexistuje.', 'back_home' => 'Späť na hlavnú stránku', 'toc' => 'Obsah', 'content_by_team' => 'Obsah vytvoril tím %s', 'page_n' => 'Stránka %d', 'regulators' => 'Regulátori',
        ],
        'hr_HR' => [
            'home' => 'Početna', 'privacy_policy' => 'Pravila o privatnosti', 'rights_reserved' => 'Sva prava pridržana',
            'navigation' => 'Navigacija', 'site' => 'Stranica', 'primary_menu' => 'Glavni izbornik', 'footer_menu' => 'Izbornik podnožja', 'back_to_top' => 'Na vrh', 'articles' => 'Članci', 'section_overview' => 'Pregled', 'previous' => 'Prethodna', 'next' => 'Sljedeća', 'page_of' => 'Stranica %1$d od %2$d', 'legal' => 'Pravno', 'contact' => 'Kontakt', 'close' => 'Zatvori', 'not_found_title' => 'Stranica nije pronađena', 'not_found_message' => 'Tražena stranica je možda premještena ili više ne postoji.', 'back_home' => 'Natrag na početnu', 'toc' => 'Sadržaj', 'content_by_team' => 'Sadržaj je izradio tim %s', 'page_n' => 'Stranica %d', 'regulators' => 'Regulatori',
        ],
        'hu_HU' => [
            'home' => 'Kezdőlap', 'privacy_policy' => 'Adatvédelmi szabályzat', 'rights_reserved' => 'Minden jog fenntartva',
            'navigation' => 'Navigáció', 'site' => 'Webhely', 'primary_menu' => 'Főmenü', 'footer_menu' => 'Lábléc menü', 'back_to_top' => 'Vissza a tetejére', 'articles' => 'Cikkek', 'section_overview' => 'Áttekintés', 'previous' => 'Előző', 'next' => 'Következő', 'page_of' => 'Oldal %1$d / %2$d', 'legal' => 'Jogi információk', 'contact' => 'Kapcsolat', 'close' => 'Bezárás', 'not_found_title' => 'Az oldal nem található', 'not_found_message' => 'A keresett oldalt lehet, hogy áthelyezték vagy már nem létezik.', 'back_home' => 'Vissza a főoldalra', 'toc' => 'Tartalomjegyzék', 'content_by_team' => 'A tartalmat a %s csapata készítette', 'page_n' => '%d. oldal', 'regulators' => 'Szabályozó hatóságok',
        ],
        'is_IS' => [
            'home' => 'Heim', 'privacy_policy' => 'Persónuverndarstefna', 'rights_reserved' => 'Allur réttur áskilinn',
            'navigation' => 'Leiðsögn', 'site' => 'Vefur', 'primary_menu' => 'Aðalvalmynd', 'footer_menu' => 'Fótvalmynd', 'back_to_top' => 'Upp', 'articles' => 'Greinar', 'section_overview' => 'Yfirlit', 'previous' => 'Fyrri', 'next' => 'Næsta', 'page_of' => 'Síða %1$d af %2$d', 'legal' => 'Lögfræðilegt', 'contact' => 'Hafa samband', 'close' => 'Loka', 'not_found_title' => 'Síða fannst ekki', 'not_found_message' => 'Síðan sem þú leitaðir að hefur mögulega verið flutt eða er ekki lengur til.', 'back_home' => 'Til baka á forsíðu', 'toc' => 'Efnisyfirlit', 'content_by_team' => 'Efnið er búið til af %s-teyminu', 'page_n' => 'Síða %d', 'regulators' => 'Eftirlitsaðilar',
        ],
        'lb_LU' => [
            'home' => 'Doheem', 'privacy_policy' => 'Dateschutz', 'rights_reserved' => 'All Rechter virbehalen',
            'navigation' => 'Navigatioun', 'site' => 'Säit', 'primary_menu' => 'Haaptmenü', 'footer_menu' => 'Fousszeilmenü', 'back_to_top' => 'No uewen', 'articles' => 'Artikelen', 'section_overview' => 'Iwwersiicht', 'previous' => 'Zréck', 'next' => 'Weider', 'page_of' => 'Säit %1$d vu %2$d', 'legal' => 'Rechtleches', 'contact' => 'Kontakt', 'close' => 'Zoumaachen', 'not_found_title' => 'Säit net fonnt', 'not_found_message' => 'D\'Säit no där Dir gesicht hutt, kann ëmgezunn ginn oder existéiert net méi.', 'back_home' => 'Zréck op d\'Haaptsäit', 'toc' => 'Inhaltsverzeechnes', 'content_by_team' => 'Inhalt erstallt vum %s-Team', 'page_n' => 'Säit %d', 'regulators' => 'Regulateuren',
        ],
        'lv' => [
            'home' => 'Sākums', 'privacy_policy' => 'Konfidencialitātes politika', 'rights_reserved' => 'Visas tiesības aizsargātas',
            'navigation' => 'Navigācija', 'site' => 'Vietne', 'primary_menu' => 'Galvenā izvēlne', 'footer_menu' => 'Kājenes izvēlne', 'back_to_top' => 'Uz augšu', 'articles' => 'Raksti', 'section_overview' => 'Pārskats', 'previous' => 'Iepriekšējā', 'next' => 'Nākamā', 'page_of' => 'Lapa %1$d no %2$d', 'legal' => 'Juridiskā informācija', 'contact' => 'Kontakti', 'close' => 'Aizvērt', 'not_found_title' => 'Lapa nav atrasta', 'not_found_message' => 'Meklētā lapa, iespējams, ir pārvietota vai vairs neeksistē.', 'back_home' => 'Atpakaļ uz sākumu', 'toc' => 'Saturs', 'content_by_team' => 'Saturu izveidoja %s komanda', 'page_n' => '%d. lapa', 'regulators' => 'Regulatori',
        ],
        'nb_NO' => [
            'home' => 'Hjem', 'privacy_policy' => 'Personvernerklæring', 'rights_reserved' => 'Alle rettigheter forbeholdt',
            'navigation' => 'Navigasjon', 'site' => 'Nettsted', 'primary_menu' => 'Hovedmeny', 'footer_menu' => 'Bunntekstmeny', 'back_to_top' => 'Til toppen', 'articles' => 'Artikler', 'section_overview' => 'Oversikt', 'previous' => 'Forrige', 'next' => 'Neste', 'page_of' => 'Side %1$d av %2$d', 'legal' => 'Juridisk', 'contact' => 'Kontakt', 'close' => 'Lukk', 'not_found_title' => 'Siden ble ikke funnet', 'not_found_message' => 'Siden du lette etter kan ha blitt flyttet eller finnes ikke lenger.', 'back_home' => 'Tilbake til forsiden', 'toc' => 'Innholdsfortegnelse', 'content_by_team' => 'Innhold laget av %s-teamet', 'page_n' => 'Side %d', 'regulators' => 'Tilsynsmyndigheter',
        ],
        'tr_TR' => [
            'home' => 'Ana sayfa', 'privacy_policy' => 'Gizlilik politikası', 'rights_reserved' => 'Tüm hakları saklıdır',
            'navigation' => 'Gezinti', 'site' => 'Site', 'primary_menu' => 'Ana menü', 'footer_menu' => 'Alt bilgi menüsü', 'back_to_top' => 'Yukarı', 'articles' => 'Makaleler', 'section_overview' => 'Genel bakış', 'previous' => 'Önceki', 'next' => 'Sonraki', 'page_of' => 'Sayfa %1$d / %2$d', 'legal' => 'Yasal', 'contact' => 'İletişim', 'close' => 'Kapat', 'not_found_title' => 'Sayfa bulunamadı', 'not_found_message' => 'Aradığınız sayfa taşınmış veya artık mevcut olmayabilir.', 'back_home' => 'Ana sayfaya dön', 'toc' => 'İçindekiler', 'content_by_team' => '%s ekibi tarafından oluşturulmuş içerik', 'page_n' => 'Sayfa %d', 'regulators' => 'Düzenleyici kurumlar',
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
