<?php
/**
 * Block Name: Latest News
 * Description: A block for use across the whole wp.org network.
 *
 * @package wporg
 */

namespace WordPressdotorg\MU_Plugins\Latest_News;

/**
 * Determines whether we can switch blogs.
 *
 * @return boolean
 */
function should_switch_to_blog() {
	return function_exists( 'is_multisite' ) && is_multisite();
}

/**
 * Renders the `wporg/latest-news` block on server.
 *
 * @param array $attributes The block attributes.
 *
 * @return string Returns the block content with received post items.
 */
function render_block( $attributes ) {
	$defaults = array(
		'perPage' => 3,
	);
	$attributes = wp_parse_args( $attributes, $defaults );
	$blog_switched = false;

	// Check if we can switch to the News blog.
	// @todo Prevent switch if Rosetta, Rosetta should use local posts.
	if ( should_switch_to_blog() && ( isset( $attributes['blogId'] ) && 0 !== $attributes['blogId'] ) ) {
		switch_to_blog( (int) $attributes['blogId'] );
		$blog_switched = true;
	}

	$cache_key = 'wporg-latest-news-' . $attributes['perPage'];
	$posts = get_transient( $cache_key );
	if ( ! $posts ) {
		$posts = wp_get_recent_posts(
			array(
				'numberposts' => $attributes['perPage'],
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $posts ) ) {
			return $posts->get_error_message();
		}

		if ( empty( $posts ) ) {
			return __( 'No posts found.', 'wporg' );
		}

		// Set Cache
		set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
	}

	$list_items = '';

	foreach ( $posts as $post ) {
		$title_element = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_html( get_permalink( $post['ID'] ) ),
			esc_html( $post['post_title'] )
		);

		$category_element = '';
		$category = get_the_category( $post['ID'] );

		if ( ! empty( $category ) ) {
			if ( isset( $category[0] ) ) {
				$category_element = sprintf(
					'<a href="%1$s" class="wp-block-wporg-latest-news__category">%2$s</a>',
					esc_html( get_category_link( $category[0]->term_id ) ),
					esc_html( $category[0]->name )
				);
			}
		}

		$date = new \DateTime( $post['post_date'] );
		$date_element = sprintf(
			'<time datetime="%1$s">%2$s</time>',
			$date->format( 'c' ),
			$date->format( 'F j, Y' )
		);

		$list_items .= sprintf(
			'<li>%1$s <div class="wp-block-wporg-latest-news__details">%2$s %3$s %4$s</div></li>',
			$title_element,
			$category_element,
			! empty( $category_element ) ? '<span>·</span>' : '',
			$date_element,
		);
	}

	$popular_categories = '';

	if ( $attributes['showCategories'] ) {
		// Get the most popular categories.
		$cache_key = 'wporg-latest-news-popular-categories';
		$popular_categories_list = get_transient( $cache_key );

		if ( ! $popular_categories_list ) {
			$popular_categories_list = get_categories(
				array(
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 3,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $popular_categories_list ) ) {
				return $popular_categories_list->get_error_message();
			}

			// Set Cache
			set_transient( $cache_key, $popular_categories_list, HOUR_IN_SECONDS );
		}

		if ( ! empty( $popular_categories_list ) ) {
			$popular_categories = '<div class="wp-block-wporg-latest-news__popular-categories">Popular categories: ';

			foreach ( $popular_categories_list as $category ) {
				$popular_categories .= sprintf(
					'<a href="%1$s">%2$s</a>, ',
					esc_attr( get_category_link( $category->term_id ) ),
					esc_html( $category->name )
				);
			}

			$popular_categories = rtrim( $popular_categories, ', ' );
			$popular_categories .= '.</div>';
		}
	}

	if ( $blog_switched ) {
		restore_current_blog();
	}

	$wrapper_attributes = get_block_wrapper_attributes();

	return sprintf(
		'<ul %1$s>%2$s</ul>%3$s',
		$wrapper_attributes,
		$list_items,
		$popular_categories
	);
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function block_init() {
	register_block_type(
		__DIR__ . '/build',
		array(
			'render_callback' => __NAMESPACE__ . '\render_block',
		)
	);

	register_block_style(
		'wporg/latest-news',
		array(
			'name'         => 'cards',
			'label'        => __( 'Cards', 'wporg' ),
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\block_init' );
