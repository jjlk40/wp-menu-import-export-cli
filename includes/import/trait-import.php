<?php

trait WPB_Menu_Import {
	/**
	 * Import menu JSON main functionality.
	 *
	 * @param  string   $file   The current JSON file to import.
	 *
	 * @return bool             Whether is imported or not.
	 */
	private function start_import( $file ) {
		$decoded_json = json_decode( file_get_contents( $file ), true );
		$locations    = get_nav_menu_locations();
		$menus        = ! is_array( $decoded_json ) ? array( $decoded_json ) : $decoded_json;

		array_map( array( $this, 'start_set_menu_item' ), $menus, $locations );

		return true;
	}

	/**
	 * Start to set the menu items.
	 *
	 * @param array   $menu        The menu item container.
	 * @param array   $locations   The WordPress menu locations.
	 */
	private function start_set_menu_item( $menu, $locations ) {
		$menu_id  = $this->get_menu_id( $menu, $locations );
		$new_menu = array();

		if ( null === $menu_id ) {
			return;
		}

		if ( ! isset( $menu['items'] ) || ! is_array( $menu['items'] ) ) {
			return;
		}

		foreach ( $menu['items'] as $menu_item ) {
			$get_method          = 'get_menu_data_by_' . $menu_item['type'];
			$menu_data_defaults  = array(
				'menu-item-title'  => isset( $menu_item['title'] ) ? $menu_item['title'] : false,
				'menu-item-status' => 'publish',
			);
			$menu_data_raw       = $this->$get_method( $menu_item, $menu_data_defaults );

			if ( empty( $menu_data_raw ) ) {
				continue;
			}

			$menu_data         = array_merge( $menu_data_defaults, $menu_data_raw );
			$slug              = $menu_item['slug'];
			$new_menu[ $slug ] = array();

			if ( isset( $menu_item['parent'] ) ) {
				$new_menu[ $slug ]['parent']      = $menu_item['parent'];
				$menu_data['menu-item-parent-id'] = isset( $new_menu[ $menu_item['parent'] ]['id'] ) ? $new_menu[ $menu_item['parent'] ]['id'] : 0;
			}

			$new_menu[ $slug ]['id'] = wp_update_nav_menu_item( $menu_id[0], 0, $menu_data );

			// if current user doesn't have caps to insert term (because we are doing cli) then we need to handle that here
			wp_set_object_terms( $new_menu[ $slug ]['id'], $menu_id, 'nav_menu' );
		}
	}

	/**
	 * Get menu data by custom url.
	 *
	 * @param array   $menu_item   The current menu item data.
	 * @param array   $defaults    The default values.
	 *
	 * @return array               The menu data.
	 */
	private function get_menu_data_by_custom( $menu_item, $defaults ) {
		$url = $menu_item['url'];

		return array(
			'menu-item-url' => 'http' === substr( $url, 0, 4 ) ? esc_url( $url ) : home_url( $url ),
			'menu-item-title' => $defaults['menu-item-title'] ?: $menu_item['url'],
		);
	}

	/**
	 * Get menu data by taxonomy.
	 *
	 * @param array   $menu_item   The current menu item data.
	 * @param array   $defaults    The default values.
	 *
	 * @return array               The menu data.
	 */
	private function get_menu_data_by_taxonomy( $menu_item, $defaults ) {
		$term = get_term_by( 'name', $menu_item['term'], $menu_item['taxonomy'] );

		if ( ! $term ) {
			return array();
		}

		return array(
			'menu-item-type'      => 'taxonomy',
			'menu-item-object'    => $term->taxonomy,
			'menu-item-object-id' => $term->term_id,
			'menu-item-title'     => $defaults['menu-item-title'] ?: $term->name,
		);
	}

	/**
	 * Get menu data by post type.
	 *
	 * @param array   $menu_item   The current menu item data.
	 * @param array   $defaults    The default values.
	 *
	 * @return array               The menu data.
	 */
	private function get_menu_data_by_post_type( $menu_item, $defaults ) {
		$page = get_page_by_path( $menu_item['page'] );

		if ( ! $page ) {
			return array();
		}

		return array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $page->ID,
			'menu-item-title'     => $defaults['menu-item-title'] ?: $page->post_title,
		);
	}

	/**
	 * Get the current menu id.
	 *
	 * @param array   $menu        The current menu to get the id.
	 * @param array   $locations   The current WordPress nav locations.
	 *
	 * @return array|WP_Error|null
	 */
	private function get_menu_id( $menu, $locations ) {
		$menu_id = null;

		if ( isset( $menu['location'] ) && isset( $locations[ $menu['location'] ] ) ) {
			$location_id     = $locations[ $menu['location'] ];
			$nav_menu_object = wp_get_nav_menu_object( $location_id );

			if ( $nav_menu_object ) {
				return $locations[ $menu['location'] ];
			}
		}

		if ( isset( $menu['name'] ) ) {
			$nav_menu_object = wp_get_nav_menu_object( $menu['name'] );
			$menu_id         = $nav_menu_object ? $nav_menu_object->term_id : wp_create_nav_menu( $menu['name'] );
		}

		return array( $menu_id );
	}
}
