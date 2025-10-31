# Plugin Improvements Checklist

This file tracks pending improvements for the AnWP Blocks Everywhere plugin.

## ✅ Completed (Session 1)

- [x] **Task 1:** Fix class_exists check from `'AnWP_'` to `'AnWP_Blocks_Everywhere'` (Line 43)
- [x] **Task 2:** Add `page-attributes` support to CPT for menu_order functionality (Line 192)
- [x] **Task 3:** Fix cache invalidation to only run for anwp_be post type (Lines 123-124, 365-369)

---

## 🔴 High Priority (Fix Next)

### Task 4: Add Asset File Validation
**Location:** `enqueue_block_assets()` method (Line 243)

**Problem:** If build file doesn't exist, code will fatal error
```php
$asset_file = self::include_file( 'build/sidebar/index.asset' );
wp_enqueue_script( ..., $asset_file['dependencies'], $asset_file['version'], ... );
```

**Fix:**
```php
$asset_file = self::include_file( 'build/sidebar/index.asset' );
if ( ! $asset_file || ! is_array( $asset_file ) ) {
    return;
}
wp_enqueue_script( 'anwp-be-block-scripts', self::url( 'build/sidebar/index.js' ), $asset_file['dependencies'], $asset_file['version'], false );
```

**Impact:** Prevents fatal errors if build files are missing

---

### Task 5: Fix N+1 Query Issue
**Location:** `get_blocks_data()` method (Lines 289-291)

**Problem:** Separate query for each meta value
```php
foreach ( $posts as $post ) {
    $hook_name = get_post_meta( $post->ID, '_anwp_be_hook', true );
    $priority  = (int) get_post_meta( $post->ID, '_anwp_be_priority', true ) ?: 10;
```

**Fix Option 1 - Use update_post_meta_cache:**
```php
$posts = get_posts( $args );

// Prime meta cache for all posts at once
if ( ! empty( $posts ) ) {
    update_post_meta_cache( wp_list_pluck( $posts, 'ID' ) );
}

$blocks_data = [];
foreach ( $posts as $post ) {
    // Now these hit cache instead of database
    $hook_name = get_post_meta( $post->ID, '_anwp_be_hook', true );
    $priority  = (int) get_post_meta( $post->ID, '_anwp_be_priority', true ) ?: 10;
    // ... rest of code
}
```

**Fix Option 2 - Use WP_Query with meta:**
```php
$query = new WP_Query( [
    'post_type'      => 'anwp_be',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
    'meta_query'     => [
        [
            'key'     => '_anwp_be_hook',
            'value'   => '',
            'compare' => '!=',
        ],
    ],
] );

$blocks_data = [];
while ( $query->have_posts() ) {
    $query->the_post();
    $post_id   = get_the_ID();
    $hook_name = get_post_meta( $post_id, '_anwp_be_hook', true );
    $priority  = (int) get_post_meta( $post_id, '_anwp_be_priority', true ) ?: 10;
    // ... rest of code
}
wp_reset_postdata();
```

**Impact:** With 50 posts: reduces ~100 queries to ~1-3 queries

---

### Task 6: Add Frontend-Only Check
**Location:** `register_dynamic_hooks()` method (Line 314)

**Problem:** Runs on admin pages, AJAX, REST API unnecessarily
```php
add_action( 'wp', [ $this, 'register_dynamic_hooks' ] );
```

**Fix:**
```php
public function register_dynamic_hooks() {
    // Only run on frontend template requests
    if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
        return;
    }

    $blocks_data = $this->get_blocks_data();
    // ... rest of method
}
```

**Impact:** Avoids unnecessary database queries on admin/AJAX/REST requests

---

## ⚠️ Medium Priority

### Task 7: Add Priority Column in Admin
**Location:** `columns()` method (Line 134)

**Current:** Only shows hook name
**Fix:** Add priority column for better debugging

```php
public function columns( $columns ) {
    $new_columns = [
        'anwp_be_hook'     => esc_html__( 'Hook', 'anwp-blocks-everywhere' ),
        'anwp_be_priority' => esc_html__( 'Priority', 'anwp-blocks-everywhere' ),
    ];
    return array_merge( $columns, $new_columns );
}

public function columns_display( $column, $post_id ) {
    if ( 'anwp_be_hook' === $column ) {
        echo esc_html( get_post_meta( $post_id, '_anwp_be_hook', true ) );
    } elseif ( 'anwp_be_priority' === $column ) {
        $priority = get_post_meta( $post_id, '_anwp_be_priority', true );
        echo esc_html( $priority ?: '10' );
    }
}
```

---

### Task 8: Add Publish Validation
**Location:** Add new method

**Problem:** Users can publish posts without setting a hook

**Fix:** Add validation on publish
```php
// In hooks() method:
add_action( 'transition_post_status', [ $this, 'validate_on_publish' ], 10, 3 );

// New method:
public function validate_on_publish( $new_status, $old_status, $post ) {
    if ( 'anwp_be' !== $post->post_type ) {
        return;
    }

    if ( 'publish' === $new_status && 'publish' !== $old_status ) {
        $hook = get_post_meta( $post->ID, '_anwp_be_hook', true );

        if ( empty( $hook ) ) {
            // Prevent publishing
            wp_update_post( [
                'ID'          => $post->ID,
                'post_status' => 'draft',
            ] );

            // Show admin notice
            set_transient( 'anwp_be_validation_error_' . $post->ID, __( 'Cannot publish: Please specify an action hook.', 'anwp-blocks-everywhere' ), 30 );
        }
    }
}

// Add admin notice display
add_action( 'admin_notices', [ $this, 'show_validation_errors' ] );

public function show_validation_errors() {
    $post_id = get_the_ID();
    if ( $error = get_transient( 'anwp_be_validation_error_' . $post_id ) ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
        delete_transient( 'anwp_be_validation_error_' . $post_id );
    }
}
```

---

### Task 9: Make Capabilities Consistent
**Location:** `register_meta()` method (Lines 213-215, 229-231)

**Problem:** CPT requires `manage_options` but meta auth checks `edit_posts`

**Fix:**
```php
'auth_callback' => function () {
    return current_user_can( 'manage_options' );
},
```

Change both meta field auth callbacks to match the CPT capability.

---

### Task 10: Add Conditional Loading Support
**Location:** Add new meta fields and logic

**Problem:** Blocks render on ALL pages - no way to limit to specific contexts

**Fix:** Add conditional meta fields:
- Post types to display on
- Specific page IDs
- Conditionals (is_front_page, is_single, etc.)

This is a larger feature requiring:
1. New meta fields
2. UI in sidebar
3. Conditional logic in `render_blocks_content()`

---

## 💡 Low Priority (Nice to Have)

### Task 11: Make Columns Sortable
Add `manage_edit-anwp_be_sortable_columns` filter to make hook/priority columns sortable.

### Task 12: Add Menu Position
Add `'menu_position' => 20` to CPT args for consistent admin menu placement.

### Task 13: Add Hook Validation
Validate hook names against common WordPress hooks or show warnings for potentially invalid hooks.

### Task 14: Optimize Closure Creation
**Location:** `register_dynamic_hooks()` (Lines 317-325)

**Current:** Creates new closure for each post
```php
foreach ( $blocks_data as $block_data ) {
    add_action( $block_data['hook'], function () use ( $block_data ) {
        $this->render_blocks_content( $block_data );
    }, $block_data['priority'] );
}
```

**Alternative:** Use array callback
```php
private $blocks_by_hook = [];

public function register_dynamic_hooks() {
    $blocks_data = $this->get_blocks_data();

    foreach ( $blocks_data as $block_data ) {
        $hook = $block_data['hook'];
        $priority = $block_data['priority'];

        if ( ! isset( $this->blocks_by_hook[ $hook ][ $priority ] ) ) {
            $this->blocks_by_hook[ $hook ][ $priority ] = [];
            add_action( $hook, [ $this, 'render_hook_blocks' ], $priority );
        }

        $this->blocks_by_hook[ $hook ][ $priority ][] = $block_data;
    }
}

public function render_hook_blocks() {
    $hook = current_filter();
    $priority = has_filter( $hook, [ $this, 'render_hook_blocks' ] );

    if ( isset( $this->blocks_by_hook[ $hook ][ $priority ] ) ) {
        foreach ( $this->blocks_by_hook[ $hook ][ $priority ] as $block_data ) {
            $this->render_blocks_content( $block_data );
        }
    }
}
```

### Task 15: Add Proper Uninstall Hook
Create `uninstall.php` file to clean up on plugin deletion:
```php
// uninstall.php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

// Delete all posts
$posts = get_posts( [
    'post_type'      => 'anwp_be',
    'posts_per_page' => -1,
    'post_status'    => 'any',
] );

foreach ( $posts as $post ) {
    wp_delete_post( $post->ID, true );
}

// Delete transient
delete_transient( 'anwp_be_blocks_data' );
```

### Task 16: Add Multisite Support
Use site-specific transients for multisite compatibility:
```php
// Instead of:
set_transient( 'anwp_be_blocks_data', $blocks_data, 12 * HOUR_IN_SECONDS );

// Use:
if ( is_multisite() ) {
    set_site_transient( 'anwp_be_blocks_data_' . get_current_blog_id(), $blocks_data, 12 * HOUR_IN_SECONDS );
} else {
    set_transient( 'anwp_be_blocks_data', $blocks_data, 12 * HOUR_IN_SECONDS );
}
```

### Task 17: Add REST API Schema
Add proper schema to meta fields for better API documentation:
```php
'show_in_rest' => [
    'schema' => [
        'type'        => 'string',
        'description' => 'WordPress action hook name where blocks will be rendered',
    ],
],
```

### Task 18: Remove Outdated Comment
**Location:** Lines 102-105

Remove or update the comment about CPT_Core and Taxonomy_Core as it's not relevant to this plugin.

### Task 19: Support Hook Arguments
**Location:** `render_blocks_content()` method

Some WordPress hooks pass arguments. Consider accepting and passing them through:
```php
public function register_dynamic_hooks() {
    foreach ( $blocks_data as $block_data ) {
        add_action(
            $block_data['hook'],
            function ( ...$args ) use ( $block_data ) {
                $this->render_blocks_content( $block_data, $args );
            },
            $block_data['priority'],
            99 // Accept unlimited arguments
        );
    }
}

public function render_blocks_content( $block_data, $hook_args = [] ) {
    // ... existing code ...

    // Make hook args available to filters
    $content = apply_filters( 'anwp_be_render_content', $content, $block_data, $hook_args );

    echo $content;
}
```

---

## 📝 Notes

- Priority order: High → Medium → Low
- Each task includes location, problem description, and suggested fix
- Test thoroughly after each implementation
- Consider backward compatibility when making changes
- Run `npm run build` after any JavaScript changes
