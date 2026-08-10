<?php
// templates/theme/headers/liquid-underline.php
if (!defined('ABSPATH')) exit;

/**
 * Обычная горизонтальная навигация без фона у пунктов. Один общий
 * элемент .sb-liquid-indicator (пилюля-подчёркивание) плавно перетекает
 * между пунктами меню при наведении — позиция и ширина пересчитываются
 * через JS (getBoundingClientRect), переход задаётся упругим cubic-bezier.
 */
class SB_Walker_Liquid_Underline_Menu extends Walker_Nav_Menu {
    private $max_items = 5;
    private $max_top_level = 6;
    private $item_counter = 0;
    private $top_level_counter = 0;
    private $parent_url = '';
    private $parent_title = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            $this->item_counter = 0;
            $output .= '<div class="sb-liquid-dropdown"><div class="sb-liquid-dropdown-grid">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            if ($this->item_counter > $this->max_items && !empty($this->parent_url)) {
                $output .= '<a href="' . esc_attr($this->parent_url) . '" class="sb-liquid-view-all" title="' . esc_attr($this->parent_title) . '">';
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

        $li_classes = ['sb-liquid-item'];
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

            if ($has_children) {
                $label = sprintf('Toggle %s submenu', esc_attr($item->title));
                $output .= '<span class="sb-liquid-pill">';
                $output .= '<a class="sb-liquid-pill-link"' . $attributes . '>' . esc_html($item->title) . '</a>';
                $output .= '<button class="sb-liquid-trigger" type="button" aria-expanded="false" aria-label="' . $label . '">';
                $output .= '<svg class="sb-liquid-caret" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $output .= '</button>';
                $output .= '</span>';
            } else {
                $output .= '<a class="sb-liquid-link"' . $attributes . '>' . esc_html($item->title) . '</a>';
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
?>
<header class="sb-header-liquid-underline" role="banner">
    <div class="sb-header-liquid-inner">
        <?php if (is_front_page()): ?>
            <h1 class="sb-header-liquid-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </h1>
        <?php else: ?>
            <div class="sb-header-liquid-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </div>
        <?php endif; ?>

        <nav class="sb-header-liquid-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <span class="sb-liquid-indicator" aria-hidden="true"></span>
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'sb-header-liquid-menu',
                'depth'          => 2,
                'fallback_cb'    => false,
                'walker'         => new SB_Walker_Liquid_Underline_Menu(),
            ]); ?>
        </nav>

        <button class="sb-header-liquid-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
