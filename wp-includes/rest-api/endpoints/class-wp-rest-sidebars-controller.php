<?php
/**
 * REST API: WP_REST_Sidebars_Controller class
 *
 * @package WordPress
 * @subpackage REST_API
 * @since 5.8.0
 *
 * Copyright (C) 2015  Martin Pettersson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Martin Pettersson <martin_pettersson@outlook.com>
 * @copyright 2015 Martin Pettersson
 * @license   GPLv2
 * @link      https://github.com/martin-pettersson/wp-rest-api-sidebars
 */

/**
 * Core class used to manage a site's sidebars.
 *
 * @since 5.8.0
 *
 * @see WP_REST_Controller
 */
class WP_REST_Sidebars_Controller extends WP_REST_Controller {

	/**
	 * Sidebars controller constructor.
	 *
	 * @since 5.8.0
	 */
	public function __construct() {
		$this->namespace = 'wp/v2';
		$this->rest_base = 'sidebars';
	}

	/**
	 * Registers the controllers routes.
	 *
	 * @since 5.8.0
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'id'      => array(
							'description' => __( 'The id of a registered sidebar' ),
							'type'        => 'string',
						),
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Checks if a given request has access to get sidebars.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return $this->do_permissions_check();
	}

	/**
	 * Retrieves the list of sidebars (active or inactive).
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$data = array();
		foreach ( (array) wp_get_sidebars_widgets() as $id => $widgets ) {
			$sidebar = $this->get_sidebar( $id );

			if ( ! $sidebar ) {
				continue;
			}

			$data[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $sidebar, $request )
			);
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Checks if a given request has access to get a single sidebar.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		return $this->do_permissions_check();
	}

	/**
	 * Retrieves one sidebar from the collection.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$sidebar = $this->get_sidebar( $request['id'] );

		if ( ! $sidebar ) {
			return new WP_Error( 'rest_sidebar_not_found', __( 'No sidebar exists with that id.' ), array( 'status' => 404 ) );
		}

		return $this->prepare_item_for_response( $sidebar, $request );
	}

	/**
	 * Checks if a given request has access to update sidebars.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function update_item_permissions_check( $request ) {
		return $this->do_permissions_check();
	}

	/**
	 * Updates a sidebar.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		if ( isset( $request['widgets'] ) ) {
			$sidebars = wp_get_sidebars_widgets();

			foreach ( $sidebars as $sidebar_id => $widgets ) {
				foreach ( $widgets as $i => $widget_id ) {
					// This automatically removes the passed widget ids from any other sidebars in use.
					if ( $sidebar_id !== $request['id'] && in_array( $widget_id, $request['widgets'], true ) ) {
						unset( $sidebars[ $sidebar_id ][ $i ] );
					}

					// This automatically removes omitted widget ids to the inactive sidebar.
					if ( $sidebar_id === $request['id'] && ! in_array( $widget_id, $request['widgets'], true ) ) {
						$sidebars['wp_inactive_widgets'][] = $widget_id;
					}
				}
			}

			$sidebars[ $request['id'] ] = $request['widgets'];

			wp_set_sidebars_widgets( $sidebars );
		}

		$request['context'] = 'edit';

		$sidebar = $this->get_sidebar( $request['id'] );

		/**
		 * Fires after a sidebar is updated via the REST API.
		 *
		 * @since 5.8.0
		 * @param array           $sidebar  The updated sidebar.
		 * @param WP_REST_Request $request  Request object.
		 */
		do_action( 'rest_save_sidebar', $sidebar, $request );

		return $this->prepare_item_for_response( $sidebar, $request );
	}

	/**
	 * Checks if the user has permissions to make the request.
	 *
	 * @since 5.8.0
	 *
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	protected function do_permissions_check() {
		// Verify if the current user has edit_theme_options capability.
		// This capability is required to access the widgets screen.
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error(
				'rest_cannot_manage_widgets',
				__( 'Sorry, you are not allowed to manage widgets on this site.' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Retrieves the registered sidebar with the given id.
	 *
	 * @since 5.8.0
	 *
	 * @global array $wp_registered_sidebars The registered sidebars.
	 *
	 * @param string|int $id ID of the sidebar.
	 * @return array|null The discovered sidebar, or null if it is not registered.
	 */
	protected function get_sidebar( $id ) {
		global $wp_registered_sidebars;

		foreach ( (array) $wp_registered_sidebars as $sidebar ) {
			if ( $sidebar['id'] === $id ) {
				return $sidebar;
			}
		}

		if ( 'wp_inactive_widgets' === $id ) {
			return array(
				'id'   => 'wp_inactive_widgets',
				'name' => __( 'Inactive widgets' ),
			);
		}

		return null;
	}

	/**
	 * Prepares a single sidebar output for response.
	 *
	 * @since 5.8.0
	 *
	 * @global array $wp_registered_sidebars The registered sidebars.
	 * @global array $wp_registered_widgets  The registered widgets.
	 *
	 * @param array           $raw_sidebar Sidebar instance.
	 * @param WP_REST_Request $request     Full details about the request.
	 *
	 * @return WP_REST_Response Prepared response object.
	 */
	public function prepare_item_for_response( $raw_sidebar, $request ) {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$id      = $raw_sidebar['id'];
		$sidebar = array( 'id' => $id );

		if ( isset( $wp_registered_sidebars[ $id ] ) ) {
			$registered_sidebar = $wp_registered_sidebars[ $id ];

			$sidebar['status']        = 'active';
			$sidebar['name']          = isset( $registered_sidebar['name'] ) ? $registered_sidebar['name'] : '';
			$sidebar['description']   = isset( $registered_sidebar['description'] ) ? $registered_sidebar['description'] : '';
			$sidebar['class']         = isset( $registered_sidebar['class'] ) ? $registered_sidebar['class'] : '';
			$sidebar['before_widget'] = isset( $registered_sidebar['before_widget'] ) ? $registered_sidebar['before_widget'] : '';
			$sidebar['after_widget']  = isset( $registered_sidebar['after_widget'] ) ? $registered_sidebar['after_widget'] : '';
			$sidebar['before_title']  = isset( $registered_sidebar['before_title'] ) ? $registered_sidebar['before_title'] : '';
			$sidebar['after_title']   = isset( $registered_sidebar['after_title'] ) ? $registered_sidebar['after_title'] : '';
		} else {
			$sidebar['status']      = 'inactive';
			$sidebar['name']        = $raw_sidebar['name'];
			$sidebar['description'] = '';
			$sidebar['class']       = '';
		}

		$fields = $this->get_fields_for_response( $request );
		if ( rest_is_field_included( 'widgets', $fields ) ) {
			$sidebars = wp_get_sidebars_widgets();
			$widgets  = array_filter(
				isset( $sidebars[ $sidebar['id'] ] ) ? $sidebars[ $sidebar['id'] ] : array(),
				static function ( $widget_id ) use ( $wp_registered_widgets ) {
					return isset( $wp_registered_widgets[ $widget_id ] );
				}
			);

			$sidebar['widgets'] = $widgets;
		}

		$schema = $this->get_item_schema();
		$data   = array();
		foreach ( $schema['properties'] as $property_id => $property ) {
			if ( isset( $sidebar[ $property_id ] ) && true === rest_validate_value_from_schema( $sidebar[ $property_id ], $property ) ) {
				$data[ $property_id ] = $sidebar[ $property_id ];
			} elseif ( isset( $property['default'] ) ) {
				$data[ $property_id ] = $property['default'];
			}
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		$response->add_links( $this->prepare_links( $sidebar ) );

		/**
		 * Filters the REST API response for a sidebar.
		 *
		 * @since 5.8.0
		 *
		 * @param WP_REST_Response $response    The response object.
		 * @param array            $raw_sidebar The raw sidebar data.
		 * @param WP_REST_Request  $request     The request object.
		 */
		return apply_filters( 'rest_prepare_sidebar', $response, $raw_sidebar, $request );
	}

	/**
	 * Prepares links for the sidebar.
	 *
	 * @since 5.8.0
	 *
	 * @param array $sidebar Sidebar.
	 *
	 * @return array Links for the given widget.
	 */
	protected function prepare_links( $sidebar ) {
		return array(
			'collection'               => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
			'self'                     => array(
				'href' => rest_url( sprintf( '%s/%s/%s', $this->namespace, $this->rest_base, $sidebar['id'] ) ),
			),
			'https://api.w.org/widget' => array(
				'href'       => add_query_arg( 'sidebar', $sidebar['id'], rest_url( '/wp/v2/widgets' ) ),
				'embeddable' => true,
			),
		);
	}

	/**
	 * Retrieves the block type' schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'sidebar',
			'type'       => 'object',
			'properties' => array(
				'id'            => array(
					'description' => __( 'ID of sidebar.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'name'          => array(
					'description' => __( 'Unique name identifying the sidebar.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'description'   => array(
					'description' => __( 'Description of sidebar.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'class'         => array(
					'description' => __( 'Extra CSS class to assign to the sidebar in the Widgets interface.' ),
					'type'        => 'string',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'before_widget' => array(
					'description' => __( 'HTML content to prepend to each widget\'s HTML output when assigned to this sidebar. Default is an opening list item element.' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'after_widget'  => array(
					'description' => __( 'HTML content to append to each widget\'s HTML output when assigned to this sidebar. Default is a closing list item element.' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'before_title'  => array(
					'description' => __( 'HTML content to prepend to the sidebar title when displayed. Default is an opening h2 element.' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'after_title'   => array(
					'description' => __( 'HTML content to append to the sidebar title when displayed. Default is a closing h2 element.' ),
					'type'        => 'string',
					'default'     => '',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'status'        => array(
					'description' => __( 'Status of sidebar.' ),
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
				),
				'widgets'       => array(
					'description' => __( 'Nested widgets.' ),
					'type'        => 'array',
					'items'       => array(
						'type' => array( 'object', 'string' ),
					),
					'default'     => array(),
					'context'     => array( 'embed', 'view', 'edit' ),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
