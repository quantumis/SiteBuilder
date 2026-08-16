<?php
/**
 * Site Builder — [sb_regulators] shortcode.
 *
 * Renders a geo-aware list of external links to gambling regulators,
 * responsible-gambling programs, self-exclusion registries, and industry
 * organizations. Intended for use in footer templates by third-party header/
 * footer authors — they only need to embed the shortcode, no PHP knowledge:
 *
 *     <?php echo do_shortcode('[sb_regulators]'); ?>
 *
 * Full parameter list:
 *     [sb_regulators]
 *         geo="DE"           Explicit ISO GEO code (DE, USA, UK, etc.). If
 *                            omitted, resolved from the WordPress locale via
 *                            sb_regulators_geo_from_locale().
 *         category="all"     Filter: regulator | responsible-gambling |
 *                            self-exclusion | organization | all
 *         title=""           Explicit heading. Omit the attribute entirely to
 *                            use the localized default "Regulators" from theme
 *                            i18n (36 locales supported). Pass an empty string
 *                            explicitly (title="") to render with no heading.
 *         layout="list"      list (<ul>) or inline (comma-separated span)
 *         title_class=""     Extra CSS class(es) appended to the <h3>. Pass
 *                            the footer's native heading class (e.g.
 *                            "sb-footer-rich-heading") to make the shortcode
 *                            visually integrate with the footer without any
 *                            extra CSS.
 *         list_class=""      Extra CSS class(es) appended to the <ul>. Pass
 *                            the footer's native list class (e.g.
 *                            "sb-footer-rich-list") so the footer's link
 *                            styles (color, spacing, hover) apply automatically.
 *
 * All external links get rel="nofollow noopener" and target="_blank" —
 * standard for regulator links in affiliate footers.
 *
 * Design philosophy: the shortcode outputs HTML with semantic classes
 * (sb-regulators, sb-regulators-title, sb-regulators-list, sb-regulators-item,
 * sb-regulators-cat-{category}) but ships NO CSS. This keeps the shortcode
 * independent of the footer variant catalogue — adding a new footer never
 * requires touching this file. Footer authors have two integration paths:
 *
 *   1. Pass title_class / list_class parameters with their footer's native
 *      classes → styles apply automatically, zero extra CSS.
 *   2. Write their own CSS in the footer's stylesheet targeting the semantic
 *      classes (.sb-footer-xxx .sb-regulators-title { ... }) → full control.
 *
 * Data source: Регуляторы_по_ГЕО_проверено.xlsx from the SEO team (July 2026),
 * embedded as a PHP array below so there's zero I/O cost per render. Adding
 * a new GEO or link means editing this file and regenerating themes.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sb_regulators_data')) {
    function sb_regulators_data() {
        // Data from Регуляторы_по_ГЕО_проверено.xlsx (SEO team, July 2026).
        // Cleaned in v1.1.10: original Russian annotations stripped (they were
        // added by the SEO team for internal reference; site output should stay
        // in the local language / universal Latin abbreviations).
        static $data = [
            'AT' => [
                ['name' => 'BMF', 'url' => 'https://www.bmf.gv.at', 'cat' => 'regulator'],
                ['name' => 'PlaySponsible (Spielen mit Verantwortung)', 'url' => 'https://www.playsponsible.at', 'cat' => 'responsible-gambling'],
            ],
            'AU' => [
                ['name' => 'ACMA', 'url' => 'https://www.acma.gov.au', 'cat' => 'regulator'],
                ['name' => 'BetStop', 'url' => 'https://www.betstop.gov.au', 'cat' => 'self-exclusion'],
                ['name' => 'Gambling Help Online', 'url' => 'https://www.gamblinghelponline.org.au', 'cat' => 'responsible-gambling'],
            ],
            'BE' => [
                ['name' => 'Gaming Commission', 'url' => 'https://www.gamingcommission.be', 'cat' => 'regulator'],
            ],
            'BG' => [
                ['name' => 'NRA', 'url' => 'https://nra.bg', 'cat' => 'regulator'],
            ],
            'BR' => [
                ['name' => 'SPA/MF — Secretaria de Prêmios e Apostas', 'url' => 'https://www.gov.br/fazenda/pt-br', 'cat' => 'regulator'],
                ['name' => 'Jogadores Anônimos', 'url' => 'https://jogadoresanonimos.com.br', 'cat' => 'responsible-gambling'],
            ],
            'CA' => [
                ['name' => 'AGCO (Ontario)', 'url' => 'https://www.agco.ca/en', 'cat' => 'regulator'],
                ['name' => 'iGaming Ontario', 'url' => 'https://www.igamingontario.ca/en', 'cat' => 'regulator'],
                ['name' => 'ConnexOntario', 'url' => 'https://www.connexontario.ca', 'cat' => 'responsible-gambling'],
                ['name' => 'Responsible Gambling Council', 'url' => 'https://responsiblegambling.org', 'cat' => 'responsible-gambling'],
            ],
            'CH' => [
                ['name' => 'Gespa', 'url' => 'https://www.gespa.ch', 'cat' => 'regulator'],
                ['name' => 'ESBK/CFMJ', 'url' => 'https://www.esbk.admin.ch', 'cat' => 'regulator'],
                ['name' => 'SOS-Spielsucht', 'url' => 'https://www.sos-spielsucht.ch', 'cat' => 'responsible-gambling'],
            ],
            'CZ' => [
                ['name' => 'Ministerstvo financí ČR', 'url' => 'https://mf.gov.cz', 'cat' => 'regulator'],
                ['name' => 'Celní správa ČR', 'url' => 'https://celnisprava.gov.cz', 'cat' => 'regulator'],
            ],
            'DE' => [
                ['name' => 'GGL', 'url' => 'https://www.gluecksspiel-behoerde.de', 'cat' => 'regulator'],
                ['name' => 'GGL Whitelist', 'url' => 'https://www.gluecksspiel-behoerde.de/de/fuer-spielende/uebersicht-erlaubter-anbieter-whitelist', 'cat' => 'regulator'],
                ['name' => 'Check dein Spiel (BIÖG, ex-BZgA)', 'url' => 'https://www.check-dein-spiel.de', 'cat' => 'responsible-gambling'],
            ],
            'DK' => [
                ['name' => 'Spillemyndigheden', 'url' => 'https://www.spillemyndigheden.dk', 'cat' => 'regulator'],
                ['name' => 'ROFUS', 'url' => 'https://www.rofus.nu', 'cat' => 'self-exclusion'],
                ['name' => 'StopSpillet', 'url' => 'https://www.stopspillet.dk', 'cat' => 'responsible-gambling'],
                ['name' => 'Center for Ludomani', 'url' => 'https://www.ludomani.dk', 'cat' => 'responsible-gambling'],
            ],
            'EE' => [
                ['name' => 'Maksu- ja Tolliamet (EMTA)', 'url' => 'https://www.emta.ee', 'cat' => 'regulator'],
            ],
            'ES' => [
                ['name' => 'DGOJ', 'url' => 'https://www.ordenacionjuego.es', 'cat' => 'regulator'],
                ['name' => 'FEJAR', 'url' => 'https://fejar.org', 'cat' => 'responsible-gambling'],
            ],
            'FI' => [
                ['name' => 'Poliisihallitus — Supervision of gambling', 'url' => 'https://poliisi.fi/en/supervision-of-gambling', 'cat' => 'regulator'],
                ['name' => 'Peluuri', 'url' => 'https://www.peluuri.fi', 'cat' => 'responsible-gambling'],
                ['name' => 'Veikkaus', 'url' => 'https://www.veikkaus.fi', 'cat' => 'self-exclusion'],
            ],
            'FR' => [
                ['name' => 'ANJ', 'url' => 'https://anj.fr', 'cat' => 'regulator'],
                ['name' => 'Joueurs Info Service', 'url' => 'https://www.joueurs-info-service.fr', 'cat' => 'responsible-gambling'],
            ],
            'GR' => [
                ['name' => 'HGC (Hellenic Gaming Commission)', 'url' => 'https://hgc.gov.gr', 'cat' => 'regulator'],
                ['name' => 'OKANA', 'url' => 'https://www.okana.gr', 'cat' => 'responsible-gambling'],
            ],
            'HR' => [
                ['name' => 'Porezna uprava (Min. financija)', 'url' => 'https://porezna-uprava.gov.hr', 'cat' => 'regulator'],
            ],
            'HU' => [
                ['name' => 'SZTFH', 'url' => 'https://sztfh.hu', 'cat' => 'regulator'],
            ],
            'IE' => [
                ['name' => 'GRAI', 'url' => 'https://www.grai.ie', 'cat' => 'regulator'],
                ['name' => 'Problem Gambling Ireland', 'url' => 'https://www.problemgambling.ie', 'cat' => 'responsible-gambling'],
            ],
            'IS' => [
                ['name' => 'Dómsmálaráðuneytið', 'url' => 'https://www.stjornarradid.is', 'cat' => 'regulator'],
                ['name' => 'SÁÁ', 'url' => 'https://www.saa.is', 'cat' => 'responsible-gambling'],
            ],
            'IT' => [
                ['name' => 'ADM', 'url' => 'https://www.adm.gov.it', 'cat' => 'regulator'],
                ['name' => 'ISS (Telefono Verde 800 558822)', 'url' => 'https://www.iss.it', 'cat' => 'responsible-gambling'],
            ],
            'LI' => [
                ['name' => 'Amt für Volkswirtschaft (AVW)', 'url' => 'https://www.llv.li', 'cat' => 'regulator'],
            ],
            'LT' => [
                ['name' => 'Lošimų priežiūros tarnyba', 'url' => 'https://lpt.lrv.lt', 'cat' => 'regulator'],
            ],
            'LU' => [
                ['name' => 'Ministère de la Justice', 'url' => 'https://mj.gouvernement.lu', 'cat' => 'regulator'],
            ],
            'LV' => [
                ['name' => 'IAUI', 'url' => 'https://www.iaui.gov.lv', 'cat' => 'regulator'],
            ],
            'NL' => [
                ['name' => 'Kansspelautoriteit (KSA)', 'url' => 'https://kansspelautoriteit.nl', 'cat' => 'regulator'],
                ['name' => 'CRUKS', 'url' => 'https://www.cruks.nl', 'cat' => 'self-exclusion'],
                ['name' => 'Loket Kansspel', 'url' => 'https://www.loketkansspel.nl', 'cat' => 'responsible-gambling'],
            ],
            'NO' => [
                ['name' => 'Lotteritilsynet', 'url' => 'https://lottstift.no', 'cat' => 'regulator'],
                ['name' => 'Hjelpelinjen', 'url' => 'https://hjelpelinjen.no', 'cat' => 'responsible-gambling'],
            ],
            'NZ' => [
                ['name' => 'Department of Internal Affairs (DIA)', 'url' => 'https://www.dia.govt.nz', 'cat' => 'regulator'],
                ['name' => 'Gambling Helpline NZ', 'url' => 'https://gamblinghelpline.co.nz', 'cat' => 'responsible-gambling'],
                ['name' => 'Safer Gambling Aotearoa', 'url' => 'https://www.safergambling.org.nz', 'cat' => 'responsible-gambling'],
            ],
            'PE' => [
                ['name' => 'MINCETUR / DGJCMT', 'url' => 'https://www.gob.pe/mincetur', 'cat' => 'regulator'],
            ],
            'PL' => [
                ['name' => 'Ministerstwo Finansów', 'url' => 'https://www.gov.pl/web/finanse', 'cat' => 'regulator'],
                ['name' => 'Rejestr domen zakazanych', 'url' => 'https://hazard.mf.gov.pl', 'cat' => 'regulator'],
                ['name' => 'Uzależnienia behawioralne (KCPU)', 'url' => 'https://uzaleznieniabehawioralne.pl', 'cat' => 'responsible-gambling'],
            ],
            'PT' => [
                ['name' => 'SRIJ', 'url' => 'https://www.srij.turismodeportugal.pt/pt', 'cat' => 'regulator'],
                ['name' => 'Jogo Responsável', 'url' => 'https://jogoresponsavel.pt', 'cat' => 'responsible-gambling'],
                ['name' => 'SICAD', 'url' => 'https://www.sicad.pt', 'cat' => 'responsible-gambling'],
            ],
            'RO' => [
                ['name' => 'ONJN', 'url' => 'https://onjn.gov.ro', 'cat' => 'regulator'],
                ['name' => 'Joc Responsabil', 'url' => 'https://jocresponsabil.ro', 'cat' => 'responsible-gambling'],
            ],
            'SE' => [
                ['name' => 'Spelinspektionen', 'url' => 'https://www.spelinspektionen.se', 'cat' => 'regulator'],
                ['name' => 'Spelpaus', 'url' => 'https://www.spelpaus.se', 'cat' => 'self-exclusion'],
                ['name' => 'Stödlinjen', 'url' => 'https://stodlinjen.se', 'cat' => 'responsible-gambling'],
            ],
            'SI' => [
                ['name' => 'FURS', 'url' => 'https://www.fu.gov.si', 'cat' => 'regulator'],
                ['name' => 'Ministrstvo za finance', 'url' => 'https://www.gov.si', 'cat' => 'regulator'],
            ],
            'SK' => [
                ['name' => 'Úrad pre reguláciu hazardných hier', 'url' => 'https://www.urhh.sk', 'cat' => 'regulator'],
            ],
            'TR' => [
                ['name' => 'Spor Toto Teşkilatı', 'url' => 'https://www.sportoto.gov.tr', 'cat' => 'regulator'],
                ['name' => 'Milli Piyango İdaresi (MPİ)', 'url' => 'https://www.mpi.gov.tr', 'cat' => 'regulator'],
            ],
            'UK' => [
                ['name' => 'UK Gambling Commission', 'url' => 'https://www.gamblingcommission.gov.uk', 'cat' => 'regulator'],
                ['name' => 'GAMSTOP', 'url' => 'https://www.gamstop.co.uk', 'cat' => 'self-exclusion'],
                ['name' => 'GambleAware', 'url' => 'https://www.gambleaware.org', 'cat' => 'responsible-gambling'],
                ['name' => 'GamCare', 'url' => 'https://www.gamcare.org.uk', 'cat' => 'responsible-gambling'],
            ],
            'USA' => [
                ['name' => 'NCPG', 'url' => 'https://www.ncpgambling.org', 'cat' => 'responsible-gambling'],
                ['name' => '1-800-GAMBLER', 'url' => 'https://www.1800gambler.net', 'cat' => 'responsible-gambling'],
                ['name' => 'American Gaming Association', 'url' => 'https://www.americangaming.org', 'cat' => 'organization'],
            ],
        ];

        return $data;
    }
}

if (!function_exists('sb_regulators_geo_from_locale')) {
    /**
     * Map a WordPress locale (get_locale()) to a GEO code used in our dataset.
     * The mapping isn't always obvious — English speakers exist in many countries
     * (en_GB → UK, en_US → USA, en_AU → AU, en_CA → CA, en_IE → IE, en_NZ → NZ),
     * German too (de_DE → DE, de_AT → AT, de_CH → CH), Portuguese (pt_BR vs
     * pt_PT), Dutch (nl_NL vs nl_BE). Returns empty string on unknown locale —
     * shortcode then renders nothing (safer than showing wrong-country links).
     */
    function sb_regulators_geo_from_locale($locale = null) {
        static $map = [
            // German
            'de_DE' => 'DE', 'de_AT' => 'AT', 'de_CH' => 'CH',
            // English
            'en_GB' => 'UK', 'en_US' => 'USA', 'en_AU' => 'AU',
            'en_CA' => 'CA', 'en_IE' => 'IE', 'en_NZ' => 'NZ',
            // Portuguese
            'pt_PT' => 'PT', 'pt_BR' => 'BR',
            // Dutch
            'nl_NL' => 'NL', 'nl_BE' => 'BE',
            // Spanish
            'es_ES' => 'ES', 'es_PE' => 'PE',
            // Romance
            'fr_FR' => 'FR', 'it_IT' => 'IT', 'ro_RO' => 'RO',
            // Slavic
            'pl_PL' => 'PL', 'cs_CZ' => 'CZ', 'sk_SK' => 'SK',
            'hr_HR' => 'HR', 'sl_SI' => 'SI', 'bg_BG' => 'BG',
            // Scandinavian / Nordic
            'da_DK' => 'DK', 'sv_SE' => 'SE', 'fi_FI' => 'FI',
            'nb_NO' => 'NO', 'is_IS' => 'IS',
            // Baltic
            'et_EE' => 'EE', 'lv'    => 'LV', 'lt_LT' => 'LT',
            // Others
            'el'    => 'GR', 'hu_HU' => 'HU', 'tr_TR' => 'TR',
            'lb_LU' => 'LU',
        ];
        $locale = $locale ?: (function_exists('get_locale') ? get_locale() : '');
        return $map[$locale] ?? '';
    }
}

if (!function_exists('sb_regulators_shortcode')) {
    function sb_regulators_shortcode($atts = []) {
        $atts = shortcode_atts([
            'geo'         => '',
            'category'    => 'all',
            // Sentinel value distinguishes "title not passed" (fall back to
            // localized default) from title="" (author explicitly requests
            // no heading). WP's array_merge inside shortcode_atts preserves
            // the sentinel only when the user didn't pass the attribute.
            'title'       => '__sb_default__',
            'layout'      => 'list',
            'title_class' => '',
            'list_class'  => '',
        ], $atts, 'sb_regulators');

        // Resolve GEO
        $geo = strtoupper(trim((string)$atts['geo']));
        if ($geo === '') $geo = sb_regulators_geo_from_locale();
        if ($geo === '') return '';

        $data = sb_regulators_data();
        if (!isset($data[$geo])) return '';

        // Filter by category
        $items = $data[$geo];
        $cat = strtolower(trim((string)$atts['category']));
        if ($cat !== '' && $cat !== 'all') {
            $items = array_values(array_filter($items, function ($it) use ($cat) {
                return $it['cat'] === $cat;
            }));
        }
        if (empty($items)) return '';

        $layout = ($atts['layout'] === 'inline') ? 'inline' : 'list';

        // Title resolution:
        //   - Attribute omitted entirely   → fall back to the localized
        //                                     "Regulators" from theme i18n
        //   - title="" explicitly          → author wants no heading, respect it
        //   - title="Foo"                  → use as-is
        //
        // The '__sb_default__' sentinel from the shortcode_atts defaults lets
        // us tell "omitted" from "empty string" — which shortcode_atts alone
        // can't do (it treats both as ''). Fallback also guards against a
        // missing translation: sb_t() returns the key itself if unmatched,
        // so we double-check and fall back to English "Regulators".
        if ($atts['title'] === '__sb_default__') {
            $title = function_exists('sb_t') ? sb_t('regulators') : 'Regulators';
            if ($title === 'regulators') $title = 'Regulators';
        } else {
            $title = trim((string)$atts['title']);
        }

        // Build classes — semantic base + optional user-supplied classes.
        // Users pass their footer's native classes here to make our HTML
        // inherit the footer's own heading/list styling without any extra CSS.
        $title_extra = trim((string)$atts['title_class']);
        $list_extra  = trim((string)$atts['list_class']);
        $title_cls = 'sb-regulators-title' . ($title_extra !== '' ? ' ' . $title_extra : '');
        $list_cls  = 'sb-regulators-list'  . ($list_extra  !== '' ? ' ' . $list_extra  : '');

        // Render
        $out = '<div class="sb-regulators sb-regulators-' . esc_attr($layout) . '" data-geo="' . esc_attr($geo) . '">';
        if ($title !== '') {
            $out .= '<h3 class="' . esc_attr($title_cls) . '">' . esc_html($title) . '</h3>';
        }
        if ($layout === 'inline') {
            $links = [];
            foreach ($items as $it) {
                $links[] = '<a href="' . esc_url($it['url']) . '" class="sb-regulators-link sb-regulators-cat-' . esc_attr($it['cat']) . '" target="_blank" rel="nofollow noopener">' . esc_html($it['name']) . '</a>';
            }
            $out .= '<span class="sb-regulators-inline">' . implode(', ', $links) . '</span>';
        } else {
            $out .= '<ul class="' . esc_attr($list_cls) . '">';
            foreach ($items as $it) {
                $out .= '<li class="sb-regulators-item sb-regulators-cat-' . esc_attr($it['cat']) . '">'
                     .  '<a href="' . esc_url($it['url']) . '" target="_blank" rel="nofollow noopener">' . esc_html($it['name']) . '</a>'
                     .  '</li>';
            }
            $out .= '</ul>';
        }
        $out .= '</div>';
        return $out;
    }
    add_shortcode('sb_regulators', 'sb_regulators_shortcode');
}
