<?php
// templates/theme/headers/balanced-center.php
if (!defined('ABSPATH')) exit;

/**
 * Walker строит dropdown-структуру из настоящего wp_nav_menu().
 * Top-level пункты — pill-кнопки. Если у пункта есть дети (depth 0 -> 1),
 * рядом с текстом ссылки появляется отдельная кнопка-стрелка, которая
 * открывает .sb-balanced-dropdown — плавающую панель под конкретной pill.
 * Клик по самому тексту — обычная ссылка, идёт куда угодно.
 * Третий уровень вложенности не рендерится (WP отдаёт его в depth 1,
 * дальше просто игнорируем — как и во floating-circle).
 */
class SB_Walker_Balanced_Center_Menu extends Walker_Nav_Menu {
    private $max_items = 5;
    private $max_top_level = 6;
    private $item_counter = 0;
    private $top_level_counter = 0;
    private $parent_url = '';
    private $parent_title = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            $this->item_counter = 0;
            $output .= '<div class="sb-balanced-dropdown"><div class="sb-balanced-dropdown-grid">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            if ($this->item_counter > $this->max_items && !empty($this->parent_url)) {
                $output .= '<a href="' . esc_attr($this->parent_url) . '" class="sb-balanced-view-all" title="' . esc_attr($this->parent_title) . '">';
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

        $li_classes = ['sb-balanced-item'];
        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $li_classes[] = 'current-menu-item';
        }
        if ($has_children) {
            $li_classes[] = 'menu-item-has-children';
        }

        if ($depth === 0) {
            $this->top_level_counter++;
            
            // Не показываем элементы после 6-го
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
                $output .= '<span class="sb-balanced-pill">';
                $output .= '<a class="sb-balanced-link"' . $attributes . '>' . esc_html($item->title) . '</a>';
                $output .= '<button class="sb-balanced-trigger" type="button" aria-expanded="false" aria-label="' . $label . '">';
                $output .= '<svg class="sb-balanced-caret" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $output .= '</button>';
                $output .= '</span>';
            } else {
                $output .= '<a class="sb-balanced-link sb-balanced-link-plain"' . $attributes . '>' . esc_html($item->title) . '</a>';
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
<header class="sb-header-balanced-center" role="banner">
    <div class="sb-header-balanced-center-inner">
        <?php if (is_front_page()): ?>
            <h1 class="sb-header-balanced-center-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </h1>
        <?php else: ?>
            <div class="sb-header-balanced-center-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </div>
        <?php endif; ?>

        <button class="sb-header-balanced-center-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="sb-header-balanced-center-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'sb-menu',
                'depth'          => 2,
                'fallback_cb'    => false,
                'walker'         => new SB_Walker_Balanced_Center_Menu(),
            ]); ?>
        </nav>
    </div>
</header>