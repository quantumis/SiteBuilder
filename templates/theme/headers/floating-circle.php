<?php
// templates/theme/headers/floating-circle.php
if (!defined('ABSPATH')) exit;

class SB_Walker_Floating_Circle_Menu extends Walker_Nav_Menu {
    private $max_items = 5;
    private $max_top_level = 6;
    private $item_counter = 0;
    private $top_level_counter = 0;
    private $parent_url = '';
    private $parent_title = '';

    public function start_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            $this->item_counter = 0;
            $output .= '<div class="sb-mega-grid-data" hidden>';
        }
    }

    public function end_lvl(&$output, $depth = 0, $args = null) {
        if ($depth === 0) {
            if ($this->item_counter > $this->max_items && !empty($this->parent_url)) {
                $output .= '<a href="' . esc_attr($this->parent_url) . '" class="sb-floating-view-all" title="' . esc_attr($this->parent_title) . '">';
                $output .= '<svg width="32" height="22" viewBox="0 0 16 16" fill="none"><circle cx="3" cy="8" r="1.5" fill="currentColor"/><circle cx="8" cy="8" r="1.5" fill="currentColor"/><circle cx="13" cy="8" r="1.5" fill="currentColor"/></svg>';
                $output .= '</a>';
            }
            $output .= '</div>';
            $this->item_counter = 0;
            $this->parent_url = '';
            $this->parent_title = '';
        }
    }

    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0) {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $has_children = in_array('menu-item-has-children', $classes, true);

        $li_classes = [];
        if (in_array('current-menu-item', $classes, true) || in_array('current-menu-ancestor', $classes, true)) {
            $li_classes[] = 'current';
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

            $output .= '<li' . ($li_classes ? ' class="' . esc_attr(implode(' ', $li_classes)) . '"' : '') . '>';

            $attributes = ' href="' . esc_attr($item->url) . '"';
            $attributes .= ' title="' . esc_attr(!empty($item->attr_title) ? $item->attr_title : $item->title) . '"';
            if (!empty($item->target)) {
                $attributes .= ' target="' . esc_attr($item->target) . '"';
            }
            if (!empty($item->xfn)) {
                $attributes .= ' rel="' . esc_attr($item->xfn) . '"';
            }

            if ($has_children) {
                $label = esc_html(sb_t('open_submenu_for', $item->title));
                $output .= '<span class="sb-item-group">';
                $output .= '<a class="sb-parent-link"' . $attributes . '>' . esc_html($item->title) . '</a>';
                $output .= '<button class="sb-trigger" type="button" aria-expanded="false" aria-label="' . $label . '">';
                $output .= '<svg class="sb-trigger-caret" viewBox="0 0 8 8" fill="none" aria-hidden="true"><path d="M1 2.5L4 5.5L7 2.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $output .= '</button>';
                $output .= '</span>';
            } else {
                $output .= '<a' . $attributes . '>' . esc_html($item->title) . '</a>';
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

/**
 * Достаёт top-level пункты меню 'primary' и делит их пополам:
 * первая половина уйдёт в левый nav, вторая — в правый.
 */
function sb_get_floating_circle_menu_halves() {
    $locations = get_nav_menu_locations();
    if (empty($locations['primary'])) {
        return [null, [], []];
    }
    $menu = wp_get_nav_menu_object($locations['primary']);
    if (!$menu) {
        return [null, [], []];
    }

    $items = wp_get_nav_menu_items($menu->term_id);
    if (empty($items)) {
        return [$menu, [], []];
    }

    $top_level_ids = [];
    foreach ($items as $item) {
        if ((int) $item->menu_item_parent === 0) {
            $top_level_ids[] = (int) $item->ID;
        }
    }

    // Если один элемент - помещаем его только в левую часть
    if (count($top_level_ids) === 1) {
        return [$menu, $top_level_ids, []];
    }

    $split = (int) floor(count($top_level_ids) / 2);
    $left_ids = array_slice($top_level_ids, 0, $split);
    $right_ids = array_slice($top_level_ids, $split);

    return [$menu, $left_ids, $right_ids];
}

list($sb_fc_menu, $sb_fc_left_ids, $sb_fc_right_ids) = sb_get_floating_circle_menu_halves();

/**
 * Фильтр, оставляющий в дереве меню только пункты из нужной половины
 * (top-level id из sb_top_level_ids + все их потомки).
 */
function sb_filter_menu_items_by_top_level($sorted_menu_items, $args) {
    if (empty($args->sb_top_level_ids)) {
        return $sorted_menu_items;
    }
    $allowed_ids = array_flip($args->sb_top_level_ids);
    $changed = true;
    while ($changed) {
        $changed = false;
        foreach ($sorted_menu_items as $item) {
            $parent = (int) $item->menu_item_parent;
            if ($parent !== 0 && isset($allowed_ids[$parent]) && !isset($allowed_ids[(int) $item->ID])) {
                $allowed_ids[(int) $item->ID] = true;
                $changed = true;
            }
        }
    }

    return array_values(array_filter($sorted_menu_items, function ($item) use ($allowed_ids) {
        return isset($allowed_ids[(int) $item->ID]);
    }));
}
add_filter('wp_nav_menu_objects', 'sb_filter_menu_items_by_top_level', 10, 2);
?>
<header class="sb-header-floating-circle" role="banner">
    <div class="sb-header-floating-circle-inner">

        <nav class="sb-header-floating-circle-nav sb-header-floating-circle-nav-left" aria-label="<?php echo esc_attr(sb_t('primary_menu_left')); ?>">
            <?php if ($sb_fc_menu): wp_nav_menu([
                'menu'             => $sb_fc_menu,
                'container'        => false,
                'menu_class'       => 'sb-menu',
                'depth'            => 2,
                'fallback_cb'      => false,
                'items_wrap'       => '<ul class="%2$s">%3$s</ul>',
                'walker'           => new SB_Walker_Floating_Circle_Menu(),
                'sb_top_level_ids' => $sb_fc_left_ids,
            ]); endif; ?>
        </nav>

        <div class="sb-header-floating-circle-brand">
            <?php if (has_custom_logo()) {
                the_custom_logo();
            } else {
                echo '<a href="' . esc_url(home_url('/')) . '" aria-label="' . esc_attr(sb_t('home')) . ' — ' . esc_attr(get_bloginfo('name')) . '"><span>' . esc_html(get_bloginfo('name')) . '</span></a>';
            } ?>
            <div class="sb-mega-connector" aria-hidden="true"></div>
        </div>

        <nav class="sb-header-floating-circle-nav sb-header-floating-circle-nav-right" aria-label="<?php echo esc_attr(sb_t('primary_menu_right')); ?>">
            <?php if ($sb_fc_menu && !empty($sb_fc_right_ids)): wp_nav_menu([
                'menu'             => $sb_fc_menu,
                'container'        => false,
                'menu_class'       => 'sb-menu',
                'depth'            => 2,
                'fallback_cb'      => false,
                'items_wrap'       => '<ul class="%2$s">%3$s</ul>',
                'walker'           => new SB_Walker_Floating_Circle_Menu(),
                'sb_top_level_ids' => $sb_fc_right_ids,
            ]); endif; ?>
        </nav>

        <button class="sb-header-floating-circle-toggle" aria-label="<?php echo esc_attr(sb_t('primary_menu')); ?>" aria-expanded="false">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </div>

    <div class="sb-header-floating-circle-mobile-menu">
        <ul class="sb-menu"></ul>
    </div>
</header>

<div class="sb-mega-backdrop"></div>
<div class="sb-mega-shell">
    <div class="sb-mega-shell-inner">
        <div class="sb-mega-panel">
            <div class="sb-mega-grid"></div>
        </div>
    </div>
</div>