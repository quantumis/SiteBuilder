<?php
// templates/theme/headers/particle-wave.php
if (!defined('ABSPATH')) exit;

class SB_Walker_Particle_Wave_Menu extends Walker_Nav_Menu {
    private $max_items = 5; // Максимум элементов до "View All"
    private $max_top_level = 6; // Максимум top-level пунктов меню
    private $item_counter = 0;
    private $top_level_counter = 0;
    private $parent_url = '';
    private $parent_title = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            $this->item_counter = 0;
            $output .= '<div class="sb-particle-dropdown"><div class="sb-particle-dropdown-grid">';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            // Добавляем кнопку "View All" если элементов было больше max_items
            if ($this->item_counter > $this->max_items && !empty($this->parent_url)) {
                $output .= '<a href="' . esc_attr($this->parent_url) . '" class="sb-particle-view-all" title="' . esc_attr($this->parent_title) . '">';
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

        $li_classes = ['sb-particle-item'];
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

            // Сохраняем URL и заголовок родителя для кнопки "View All"
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
                $output .= '<div class="sb-particle-pill">';
                $output .= '<a class="sb-particle-pill-link"' . $attributes . '><span class="sb-particle-link-text">' . esc_html($item->title) . '</span></a>';
                $output .= '<button class="sb-particle-trigger" type="button" aria-expanded="false" aria-label="' . $label . '">';
                $output .= '<svg class="sb-particle-caret" viewBox="0 0 10 6" fill="none" aria-hidden="true"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $output .= '</button>';
                $output .= '</div>';
            } else {
                $output .= '<a class="sb-particle-link"' . $attributes . '><span class="sb-particle-link-text">' . esc_html($item->title) . '</span></a>';
            }
            return;
        }

        if ($depth === 1) {
            $this->item_counter++;
            
            // Показываем только первые max_items элементов
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
<header class="sb-header-particle-wave" role="banner">
    <canvas class="sb-header-particle-canvas"></canvas>
    
    <div class="sb-header-particle-inner">
        <?php if (is_front_page()): ?>
            <h1 class="sb-header-particle-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </h1>
        <?php else: ?>
            <div class="sb-header-particle-brand">
                <?php if (has_custom_logo()) {
                    the_custom_logo();
                } else {
                    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html(get_bloginfo('name')) . '</a>';
                } ?>
            </div>
        <?php endif; ?>

        <nav class="sb-header-particle-nav" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>">
            <?php wp_nav_menu([
                'theme_location' => 'primary',
                'container'      => false,
                'menu_class'     => 'sb-header-particle-menu',
                'depth'          => 2,
                'fallback_cb'    => false,
                'walker'         => new SB_Walker_Particle_Wave_Menu(),
            ]); ?>
        </nav>

        <button class="sb-header-particle-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>
</header>
