<?php
// templates/theme/headers/split-flap-board.php
if (!defined('ABSPATH')) exit;

/**
 * Хедер в стиле механического табло аэропорта/вокзала (split-flap).
 * Каждая буква лого и пунктов меню рендерится как отдельная "плашка" —
 * JS проигрывает посимвольную прокрутку при загрузке страницы и при
 * hover на пункте меню. Dropdown раскрывается как доп. строка табло.
 */
class SB_Walker_Split_Flap_Board_Menu extends Walker_Nav_Menu {
    private $max_items = 5;
    private $max_top_level = 6;
    private $item_counter = 0;
    private $top_level_counter = 0;
    private $parent_url = '';
    private $parent_title = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            $this->item_counter = 0;
            $output .= '<div class="sb-flap-dropdown"><div class="sb-flap-dropdown-grid">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            if ($this->item_counter > $this->max_items && !empty($this->parent_url)) {
                $output .= '<a href="' . esc_attr($this->parent_url) . '" class="sb-flap-view-all" title="' . esc_attr($this->parent_title) . '">';
                $output .= '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="3" cy="8" r="1.5" fill="currentColor"/><circle cx="8" cy="8" r="1.5" fill="currentColor"/><circle cx="13" cy="8" r="1.5" fill="currentColor"/></svg>';
                $output .= '</a>';
            }
            $output .= '</div></div>';
            $this->item_counter = 0;
            $this->parent_url = '';
            $this->parent_title = '';
        }
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes, true);

        $li_classes = ['sb-flap-item'];
        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $li_classes[] = 'current-menu-item';
        }
        if ($has_children) {
            $li_classes[] = 'menu-item-has-children';
        }

        if ($depth === 0) {
            $this->top_level_counter++;
            
            if ($this->top_level_counter > $this->max_top_level) {
                return;
            }

            if ($has_children) {
                $this->parent_url = $item->url;
                $this->parent_title = $item->title;
            }

            $output .= '<li class="' . esc_attr(implode(' ', $li_classes)) . '">';

            $attributes = ' href="' . esc_attr($item->url) . '"';
            $attributes .= ' title="' . esc_attr(!empty($item->attr_title) ? $item->attr_title : $item->title) . '"';
            if (!empty($item->target)) {
                $attributes .= ' target="' . esc_attr($item->target) . '"';
            }
            if (!empty($item->xfn)) {
                $attributes .= ' rel="' . esc_attr($item->xfn) . '"';
            }

            $flap_markup = '<span class="sb-flap-word" data-flap-text="' . esc_attr($item->title) . '"></span>';

            if ($has_children) {
                $label = sprintf('Toggle %s submenu', esc_attr($item->title));
                $output .= '<span class="sb-flap-pill">';
                $output .= '<a class="sb-flap-link"' . $attributes . '>' . $flap_markup . '</a>';
                $output .= '<button class="sb-flap-trigger" type="button" aria-expanded="false" aria-label="' . $label . '">';
                $output .= '<svg class="sb-flap-caret" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $output .= '</button>';
                $output .= '</span>';
            } else {
                $output .= '<a class="sb-flap-link"' . $attributes . '>' . $flap_markup . '</a>';
            }
            return;
        }

        if ($depth === 1) {
            $this->item_counter++;
            
            if ($this->item_counter <= $this->max_items) {
                $attributes = ' href="' . esc_attr($item->url) . '"';
                if (!empty($item->attr_title)) {
                    $attributes .= ' title="' . esc_attr($item->attr_title) . '"';
                }
                if (!empty($item->target)) {
                    $attributes .= ' target="' . esc_attr($item->target) . '"';
                }
                if (!empty($item->xfn)) {
                    $attributes .= ' rel="' . esc_attr($item->xfn) . '"';
                }
                $output .= '<a' . $attributes . '>' . esc_html($item->title) . '</a>';
            }
        }
    }

    public function end_el(&$output, $item, $depth = 0, $args = null) {
        if ($depth === 0 && $this->top_level_counter <= $this->max_top_level) {
            $output .= '</li>';
        }
    }
}

$sb_site_name = get_bloginfo('name');
?>
<header class="sb-header-split-flap-board" role="banner">
    <div class="sb-header-flap-inner">
        <div class="sb-header-flap-brand">
            <?php if (has_custom_logo()): ?>
                <?php if (is_front_page()): ?><h1 style="margin:0;"><?php the_custom_logo(); ?></h1><?php else: the_custom_logo(); endif; ?>
            <?php else: ?>
                <?php if (is_front_page()): ?>
                    <h1 style="margin:0;">
                        <a href="<?php echo esc_url(home_url('/')); ?>" class="sb-flap-brand-link">
                            <span class="sb-flap-word sb-flap-word-brand" data-flap-text="<?php echo esc_attr($sb_site_name); ?>"></span>
                        </a>
                    </h1>
                <?php else: ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>" class="sb-flap-brand-link">
                        <span class="sb-flap-word sb-flap-word-brand" data-flap-text="<?php echo esc_attr($sb_site_name); ?>"></span>
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <nav class="sb-header-flap-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'sb-header-flap-menu',
                'depth'          => 2,
                'fallback_cb'    => false,
                'walker'         => new SB_Walker_Split_Flap_Board_Menu(),
            ]); ?>
        </nav>

        <button class="sb-header-flap-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
            <span class="sb-flap-toggle-line"></span>
            <span class="sb-flap-toggle-line"></span>
        </button>
    </div>
</header>
