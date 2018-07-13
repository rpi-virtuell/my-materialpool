<?php

/**
 * My Materialpool
 *
 * @package   MyMaterialpool
 * @author    Frank Neumann-Staude
 * @license   GPL-3.0+
 * @link      https://github.com/rpi-virtuell/my-materialpool
 */

/*
 * Plugin Name:       My Materialpool
 * Plugin URI:        https://github.com/rpi-virtuell/my-materialpool
 * Description:       RPI Virtuell My Materialpool
 * Version:           0.0.1
 * Author:            Frank Neumann-Staude
 * Author URI:        https://staude.net
 * License:           GNU General Public License v3
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path:       /languages
 * Text Domain:       my-materialpool
 * GitHub Plugin URI: https://github.com/rpi-virtuell/my-materialpool
 * GitHub Branch:     master
 * Requires WP:       4.0
 * Requires PHP:      5.3
 */

class MyMaterialpool {

	public static $plugin_url = NULL;

    public  function __construct() {
		self::$plugin_url = plugin_dir_url( __FILE__ );

	    add_action( 'wp_ajax_mympool_delete_all',  array( 'MyMaterialpool', 'callbackDeleteAll' ) );
	    add_action( 'wp_ajax_mympool_import_all',  array( 'MyMaterialpool', 'callbackImportAll' ) );
		if ( is_admin() ){
			add_action( 'admin_menu', array( 'MyMaterialpool', 'addSettingsMenu' ) );
			add_action( 'admin_init', array( 'MyMaterialpool', 'registerSettingMenuSettings' ) );
			add_action( 'admin_enqueue_scripts', array( 'MyMaterialpool', 'registerAdminStyles' ) );

		}
		add_shortcode( 'mymaterialpool', array( 'MyMaterialpool', 'shortcode' ) );
	    add_filter( 'query_vars', array( 'MyMaterialpool', 'add_query_vars_filter' ) );

	    error_log( 'constructor');
	}

	public static function shortcode( $atts ) {
        global $paged;
        global $the_query;
		global $wp;
		$content = <<<EOF
<div>
    <div style="float:left; width: 28%">
        Facetten
EOF;

   		if (!$paged){ $paged = 1;}

		$bildungsstufeArray = array();
		$bildungsstufen = get_query_var("mpoolfacet_bildungsstufe");
		if ( is_array( $bildungsstufen ) ) {
		    foreach ( $bildungsstufen as $bildungsstufe ) {
		        $taxArray[] = array(
		                'taxonomy' => 'bildungsstufe',
                    'field' => 'slug',
                    'terms' => $bildungsstufe,
                );
            }
        }
		$medientypeArray = array();
		$medientypen = get_query_var("mpoolfacet_medientyp");
		if ( is_array( $medientypen ) ) {
			foreach ( $medientypen as $medientype ) {
				$taxArray[] = array(
					'taxonomy' => 'medientyp',
					'field' => 'slug',
					'terms' => $medientype,
				);
			}
		}
		$altersstufeArray = array();
		$altersstufen = get_query_var("mpoolfacet_altersstufe");
		if ( is_array( $altersstufen ) ) {
			foreach ( $altersstufen as $altersstufe ) {
				$taxArray[] = array(
					'taxonomy' => 'altersstufe',
					'field' => 'slug',
					'terms' => $altersstufe,
				);
			}
		}

        $taxquery = array();
        if ( count( $taxArray ) > 1 ) {
            $taxquery['relation'] = 'AND';
        }
        foreach ( $taxArray as $tax ) {
            $taxquery[] = $tax;
        }


        $args = array(
	        'post_type' => 'material',
	        'posts_per_page' => -1,
	        'tax_query' => $taxquery,
        );

		$the_query = new WP_Query( $args );
		$anzahl = $the_query->post_count;
		$args = array(
			'post_type' => 'material',
			'paged'     => $paged,
			'posts_per_page' => 10,
			'tax_query' => $taxquery,
		);

		$the_query = new WP_Query( $args );

		$taxs = get_taxonomies( array( 'public' => true, 'query_var' => true ),  'objects' );
		foreach ($taxs as $tax) {
		    if ( $tax->name != 'medientyp' && $tax->name != 'bildungsstufe' && $tax->name != 'altersstufe' )
			    continue;
			if ( !$tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
				continue;
			} else if ( $tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
				$termID = term_exists( get_query_var( $tax->query_var ) );
				$terms = get_terms( $tax->name, array( 'child_of' => $termID ) );
			} else {
				$terms = get_terms( $tax->name );
			}
			if ( sizeof( $terms ) == 0 )
				continue;

			add_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter'), 10, 3 );
			add_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter'), 10, 3 );
            ob_start();
			wp_list_categories( array(
				'taxonomy' => $tax->name,
				'show_count' => true,
				'title_li' => $tax->labels->name,
			) );
			$content.= ob_get_contents();
			ob_clean();
			remove_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ) );
			remove_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter') );

		}

		$content .= <<<EOF
    </div>
    <div  style="float:left; width: 68%">
EOF;

		if ( $the_query->have_posts() ) {
			$content .= self::get_pagination($the_query);
			$content .= "Anzahl: " . $anzahl;
			$content .= "<br>";
			$content .= "Facetten entfernen: ";
			foreach ( $taxArray as $tax ) {

			    if ( is_array( $tax ) ) {
			        $content .= " <a href='" . self::removeUrl( $_SERVER['REQUEST_URI'] , $tax['taxonomy'], $tax['terms'] ) . "'>". $tax['terms'] . "</a> ";
                }
            }
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				$content .= "<div style='margin-bottom: 30px;border: 1px solid #000000;'>";
				$content .= "<h2>" . get_the_title() . "</h2>";
				$content .= "<div>". get_metadata('post', get_the_ID(), 'material_kurzbeschreibung', true ). "</div>";
				$content .= "<div><a href='". get_metadata('post', get_the_ID(), 'material_url', true ). "'>Zum Material</a></div>";
				$content .= "</div>";
			}
			$content .= self::get_pagination($the_query);
		}

		$content .= <<<EOF
    </div>
</div>
EOF;

        return $content;
    }

    public static function removeUrl ( $url, $tax, $slug ) {

        $url = preg_replace ( '/\/page\/.*\//','',  $url );

	    $url = preg_replace ( '/mpoolfacet_'.$tax.'%5B.*%5D='. $slug.'/','',  $url );
	    $url = preg_replace ( '/mpoolfacet_'.$tax.'%5B%5D='. $slug.'/','',  $url );
	    $url = preg_replace ( '/\&\&/','&',  $url );
	    $url = preg_replace ( '/\?\&/','?',  $url );

        return $url;
    }

	public static function add_query_vars_filter( $vars ) {
		$vars[] = "mpoolfacet_bildungsstufe";
		$vars[] = "mpoolfacet_altersstufe";
		$vars[] = "mpoolfacet_medientyp";
		return $vars;
	}

	public static function term_link_filter( $termlink, $term, $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		return esc_url(  preg_replace ( '/\/page\/.*\//','',  add_query_arg( ' mpoolfacet_'.$tax->query_var.'[]', $term->slug ) ) ) ;
	}

	public static  function get_terms_filter( $terms, $taxonomies, $args ) {
		global $the_query;
		remove_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ) );
		remove_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter') );
		$tax = get_taxonomy( $taxonomies[0] );

		foreach ( $terms as $id => &$term ) {
			$tax_query = $the_query->tax_query->queries;
			$tax_query['relationship'] = 'AND';
			$tax_query[] =	array(
				'taxonomy' 	=> 	$tax->name,
				'field' 	=> 	'slug',
				'terms'		=> 	$term->slug,
			);
			$query = new WP_Query( array( 'tax_query' => $tax_query ) );
			//If this term has no posts, don't display the link
			if ( !$query->found_posts )
				unset( $terms[ $id ] );
			else
				$terms[$id]->count = $query->found_posts;
		}
		add_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter'), 10, 3 );
		add_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter'), 10, 3 );
		return $terms;
	}

	public static function tax_in_query( $tax, $wp_query ) {
        if ( !isset( $wp_query->tax_query ) || !isset( $wp_query->tax_query->queries ) )
			return false;
		foreach ( $wp_query->tax_query->queries as $query )
			if ( is_array( $query ) && array_key_exists( 'taxonomy', $query ) && $query['taxonomy'] == $tax )
				return true;
		return false;
	}

    public static function init() {
	    self::registerMaterialPostType();
	    self::registerTaxonomyBildungsstufe();
	    self::registerTaxonomyMedientyp();
	    self::registerTaxonomyAltersstufe();
    }

    public static function addSettingsMenu() {
		add_submenu_page(
			'options-general.php',
			'My Materialpool',
			'My Materialpool',
			'administrator',
			__FILE__,
			array( 'MyMaterialpool', 'my_cool_plugin_settings_page' )
		);
	}

	public static function registerSettingMenuSettings() {
		register_setting( 'my-materialppool-settings-group', 'mympool-urls' );
		register_setting( 'my-materialppool-settings-group', 'mympool-template' );
		register_setting( 'my-materialppool-settings-group', 'mympool-max-count' );
		register_setting( 'my-materialppool-settings-group', 'mympool-timeout' );
	}

	public static function my_cool_plugin_settings_page() {
		?>
        <div class="wrap">
            <h1>My Materialpool</h1>

            <form method="post" action="options.php">
				<?php settings_fields( 'my-materialppool-settings-group' ); ?>
				<?php do_settings_sections( 'my-materialppool-settings-group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Quellen</th>
                        <td><input type="text" name="mympool-urls" class="large-text code" value="<?php echo esc_attr( get_option('mympool-urls') ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Maximale Anzahl</th>
                        <td><input type="text" name="mympool-max-count" class=" code" value="<?php echo esc_attr( get_option('mympool-max-count') ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Timeout (in Sekunden, default: 5)</th>
                        <td><input type="text" name="mympool-timeout" class=" code" value="<?php echo esc_attr( get_option('mympool-timeout') ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Template</th>
                        <td><textarea name="mympool-template" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-template') ); ?></textarea></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Statistik</th>
                        <td>
                            Materialien: <?php echo self::countMaterial(); ?><br>
                            Bildungsstufen: <?php echo self::countBildungsstufen(); ?><br>
                            Medientypen: <?php echo self::countMedientypen(); ?><br>
                            Altersstufen: <?php echo self::countAltersstufen(); ?><br>
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td><button id="mympoolimportall">Jetzt Importieren</button></td>
                        <td><button id="mympooldeleteall">Alles löschen</button></td>
                    </tr>
                </table>
				<?php submit_button(); ?>
            </form>
        </div>
		<?php
	}

	public static function registerAdminStyles() {
		wp_enqueue_script( 'mymaterialpool-admin-js',  MyMaterialpool::$plugin_url . 'js/admin.js' );
	}

	public static function countMaterial() {
		$count = wp_count_posts( 'material' );
		return $count->publish;
	}

	public static function countBildungsstufen() {
		return wp_count_terms( 'bildungsstufe' );
	}

	public static function countMedientypen() {
		return wp_count_terms( 'medientyp' );
	}

	public static function countAltersstufen() {
		return wp_count_terms( 'altersstufe' );
	}

    public static function importMedientyp() {
	    $url = "https://material.rpi-virtuell.de/wp-json/wp/v2/medientyp/?page=1&per_page=100";
        $response = self::getRemoteMaterial( $url );
	    $body = wp_remote_retrieve_body($response);
	    $data = json_decode($body, true);
	    $header = wp_remote_retrieve_headers( $response );
	    if ( is_array( $data ) ) {
	        // Phase 1 - Parent = 0
		    foreach ( $data as $tax ) {
		        if ( $tax[ 'parent'] == 0 ) {
			        $term = term_exists( $tax[ 'name' ], 'medientyp' );
			        if ( 0 === $term || null === $term ) {
				        $term_id = wp_insert_term( $tax[ 'name' ], 'medientyp' );
				        add_term_meta ( $term_id[ 'term_id' ], 'mp_original_id', $tax[ 'id' ], true );
			        }
                }
		    }
		    // Phase 2 - Parent <> 0
		    foreach ( $data as $tax ) {
			    if ( $tax[ 'parent'] != 0 ) {
				    $term = term_exists( $tax[ 'name' ], 'medientyp' );
				    if ( 0 === $term || null === $term ) {
				        // ID des Parent ermitteln.
					    $args = array(
						    'hide_empty' => false, // also retrieve terms which are not used yet
						    'meta_query' => array(
							    array(
								    'key'       => 'mp_original_id',
								    'value'     => $tax[ 'parent' ],
								    'compare'   => 'LIKE'
							    )
						    )
					    );
					    $terms = get_terms( 'medientyp', $args );
					    $parentID = $terms[0]->term_id;
					    $term_id = wp_insert_term( $tax[ 'name' ], 'medientyp' , array ( 'parent' => $parentID ) );
					    add_term_meta ( $term_id[ 'term_id' ], 'mp_original_id', $tax[ 'id' ], true );
				    }
			    }
		    }
	    }
    }

	public static function importBildungsstufen() {
		$url = "https://material.rpi-virtuell.de/wp-json/wp/v2/bildungsstufe/?page=1&per_page=100";
		$response = self::getRemoteMaterial( $url );
		$body = wp_remote_retrieve_body($response);
		$data = json_decode($body, true);
		$header = wp_remote_retrieve_headers( $response );
		if ( is_array( $data ) ) {
			// Phase 1 - Parent = 0
			foreach ( $data as $tax ) {
				if ( $tax[ 'parent'] == 0 ) {
					$term = term_exists( $tax[ 'name' ], 'bildungsstufe' );
					if ( 0 === $term || null === $term ) {
						$term_id = wp_insert_term( $tax[ 'name' ], 'bildungsstufe' );
						add_term_meta ( $term_id[ 'term_id' ], 'mp_original_id', $tax[ 'id' ], true );
					}
				}
			}
			// Phase 2 - Parent <> 0
			foreach ( $data as $tax ) {
				if ( $tax[ 'parent'] != 0 ) {
					$term = term_exists( $tax[ 'name' ], 'bildungsstufe' );
					if ( 0 === $term || null === $term ) {
						// ID des Parent ermitteln.
						$args = array(
							'hide_empty' => false, // also retrieve terms which are not used yet
							'meta_query' => array(
								array(
									'key'       => 'mp_original_id',
									'value'     => $tax[ 'parent' ],
									'compare'   => 'LIKE'
								)
							)
						);
						$terms = get_terms( 'bildungsstufe', $args );
						$parentID = $terms[0]->term_id;
						$term_id = wp_insert_term( $tax[ 'name' ], 'bildungsstufe' , array ( 'parent' => $parentID ) );
						add_term_meta ( $term_id[ 'term_id' ], 'mp_original_id', $tax[ 'id' ], true );
					}
				}
			}
		}
	}

	public static function importMaterial( $url ) {
		global $wpdb;
        $import = true;
        $max = get_option( 'mympool-max-count', 10 );
        $count = 0;
        $page = 1;
        while ( $import ) {
            $url = add_query_arg( 'page', $page, $url );
	        error_log( ' import :' . $url );

	        $response = self::getRemoteMaterial( $url );
	        $body = wp_remote_retrieve_body($response);
	        $data = json_decode($body, true);
	        $header = wp_remote_retrieve_headers( $response );
	        if ( is_array( $data ) ) {
		        foreach ( $data as $inx => $remote_item_data ) {
		            if ( $count >= $max ) {
		                $import = false;
		                break;
                    }
			        $post_arr = array(
				        'post_name'    => $remote_item_data['slug'],
				        'post_title'   => $remote_item_data['material_titel'],
				        'post_content' => $remote_item_data['material_beschreibung'],
				        'post_status'  => 'publish',
				        'post_author'  => get_current_user_id(),
				        'post_type'    => 'material',
				        'meta_input'   => array(
					        'material_url'              => $remote_item_data['material_url'],
					        'material_titel'            => $remote_item_data['material_titel'],
					        'material_kurzbeschreibung' => $remote_item_data['material_kurzbeschreibung'],
					        'material_beschreibung'     => $remote_item_data['material_beschreibung'],
					        'material_screenshot'       => $remote_item_data['material_screenshot'],
				        ),
			        );
			        // Prüfen ob Slug schon vorhanden.
			        $id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_name = '%s' AND  post_type = 'material'  ", $remote_item_data['slug'] ) );
			        if ( empty( $id ) ) {
				        $materialid = wp_insert_post( $post_arr );
				        error_log( ' importing post :' . $remote_item_data['slug'] );
				        // Taxonomien dazu speichern.
                        foreach ( $remote_item_data['material_altersstufe']  as $tax ) {
                            wp_set_post_terms( $materialid, $tax[ 'name' ], 'altersstufe');
                        }
				        foreach ( $remote_item_data['material_medientyp']  as $tax ) {
					        $term = term_exists( $tax[ 'name' ], 'medientyp' );
					        if ( 0 !== $term && null !== $term ) {
						        wp_set_post_terms( $materialid, $term[ 'term_id'], 'medientyp');
						        error_log( ' medien  :' . $term[ 'term_id'] );
					        }
				        }
				        foreach ( $remote_item_data['material_bildungsstufe']  as $tax ) {
					        $term = term_exists( $tax[ 'name' ], 'bildungsstufe' );
					        if ( 0 !== $term && null !== $term ) {
						        error_log( ' bildung  :' . $term[ 'term_id'] );
						        wp_set_post_terms( $materialid, $term[ 'term_id'], 'bildungsstufe');
					        }
				        }
			        } else {
				        error_log( ' ignoring post :' . $remote_item_data['slug'] );
			        }
			        $count++;
		        }
                $page++;
	        }
        }
	}


	public static function registerMaterialPostType() {
		$args = array(
			'public' => true,
			'label'  => 'Material'
		);
		register_post_type( 'material', $args );
	}

	public static function registerTaxonomyBildungsstufe() {
		register_taxonomy(
			'bildungsstufe',
			'material',
			array(
				'label' => __( 'Bildungsstufe' ),
				'rewrite' => array( 'slug' => 'bildungsstufe' ),
				'hierarchical' => true,
				'public' => true,
			)
		);
	}

	public static function registerTaxonomyMedientyp() {
		register_taxonomy(
			'medientyp',
			array( 'material' ),
			array(
				'label' => __( 'Medientyp' ),
				'rewrite' => array( 'slug' => 'medientyp' ),
                'hierarchical' => true,
                'public' => true,
			)
		);
	}

	public static function registerTaxonomyAltersstufe() {
		register_taxonomy(
			'altersstufe',
			'material',
			array(
				'label' => __( 'Altersstufe' ),
				'rewrite' => array( 'slug' => 'altersstufe' ),
			)
		);
	}

	public static function getRemoteMaterial( $url ) {
		error_log( ' getRemoteMaterial :' . $url );
		$timeout = get_option( 'mympool-timeout', 5 );
		$args = array(
			'timeout'     => $timeout,
        );
		$response = wp_remote_get($url, $args );
		if (is_wp_error($response) || !isset($response['body'])) {
			error_log( ' getRemoteMaterial ' . $response->get_error_message() );
		    return;
		} // bad response
		$body = wp_remote_retrieve_body($response);
		if (is_wp_error($body)) return; // bad body
		$data = json_decode($body, true);
		if (!$data || empty($data)) return; // bad data

 		return $response;
	}

	public static function callbackDeleteAll() {
		error_log( ' callbackDeleteAll');
		self::deleteTerms( 'bildungsstufe' );
		self::deleteTerms( 'medientyp' );
		self::deleteTerms( 'altersstufe' );
		self::deletePosts( 'material' );
		wp_die();
	}

	public static function callbackImportAll() {
		error_log( ' callbackImportAll');
		$url = get_option( 'mympool-urls' );
		self::importMedientyp();
		self::importBildungsstufen();
		self::importMaterial( $url );
		wp_die();
	}

	public static function deleteTerms ( $tax ) {
		$terms = get_terms( $tax, array( 'fields' => 'ids', 'hide_empty' => false ) );
		foreach ( $terms as $value ) {
			wp_delete_term( $value, $tax );
		}
	}

	public static function deletePosts ( $postType ) {
		$mycustomposts = get_posts( array( 'post_type' => $postType, 'numberposts' => -1));
		foreach( $mycustomposts as $mypost ) {
			wp_delete_post( $mypost->ID, true);
		}
	}


	public static  function get_pagination($query = false, $range = 4) {
        ob_start();
		global $paged, $wp_query;

		$q = ($query) ? $query : $wp_query;
		$max_page = $q->max_num_pages;

		if($max_page > 1) {
			$temp_q = $wp_query;	// save a temporary copy
			$wp_query = $q;			// overwrite with our query

			echo '<div class="paginationWrap clearfix"><div class="pagination">';

			if (!$paged){ $paged = 1;}

			previous_posts_link('<span class="prev-post"> &laquo;</span> ');

			if ($max_page > $range) {
				if ($paged < $range) {
					for($i = 1; $i <= ($range + 1); $i++) {
						echo " <a href='" . get_pagenum_link($i) ."'";
						if($i==$paged) echo " class='current'";
						echo ">$i</a> ";
					}
				} elseif($paged >= ($max_page - ceil(($range/2)))){
					for($i = $max_page - $range; $i <= $max_page; $i++){
						echo " <a href='" . get_pagenum_link($i) ."'";
						if($i==$paged) echo " class='current'";
						echo ">$i</a> ";
					}
				} elseif($paged >= $range && $paged < ($max_page - ceil(($range/2)))){
					for($i = ($paged - ceil($range/2)); $i <= ($paged + ceil(($range/2))); $i++){
						echo " <a href='" . get_pagenum_link($i) ."'";
						if($i==$paged) echo " class='current'";
						echo ">$i</a> ";
					}
				}
			} else{
				for($i = 1; $i <= $max_page; $i++){
					echo " <a href='" . get_pagenum_link($i) ."'";
					if($i==$paged) echo " class='current'";
					echo ">$i</a> ";
				}
			}
			next_posts_link(' <span class="next-post">&raquo; </span>');
			$wp_query = $temp_q;

			echo '</div></div>';
            $ret = ob_get_contents();
            ob_clean();
            return $ret;
		}
	}

}

add_action( 'init',array( 'MyMaterialpool', 'init' ) );
add_action ( 'plugins_loaded', function(){
	$myMaterialpool = new MyMaterialpool();
});

