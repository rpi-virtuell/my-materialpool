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
 * Version:           0.0.12
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

	public static $template = "<div style='margin-bottom: 30px;border: 1px solid #000000;'>
	<h2>{material_title}</h2>
	<div>{material_kurzbeschreibung}</div>
    <div><a href='{material_url}'>Zum Material</a></div>
</div>
";

	public static $template_view = '<div id="mymaterialpool">
    <div id="mymaterialpoolfaccet"style="float:left; width: 28%;">
        Facetten
{facetlist}
    </div>
    <div  id="mymaterialpoolresult" style="float:left; width: 68%;">
    <form>
        <input name="mp-search" id="mp-search" type="text" placeholder="Suchbegriff" style="width: 100%;" value="{searchquery}" >
    </form> 
    {removefacets}
    {pagination}
    {anzahl} 
    <br>
    {results}
    </div>
</div>
';

	public static $template_viewfacet = '<div id="mymaterialpoolfaccet">
        Facetten
{facetlist}
</div>
';

	public static $template_viewresult = '<div  id="mymaterialpoolresult" >
    <form>
        <input name="mp-search" id="mp-search" type="text" placeholder="Suchbegriff" style="width: 100%;" value="{searchquery}" >
    </form> 
    {removefacets}
    {pagination}
    {anzahl} 
    <br>
    {results}
    </div>
 
';
    public  function __construct() {
		self::$plugin_url = plugin_dir_url( __FILE__ );

	    add_action( 'wp_ajax_mympool_delete_all',  array( 'MyMaterialpool', 'callbackDeleteAll' ) );
	    add_action( 'wp_ajax_mympool_import_all',  array( 'MyMaterialpool', 'callbackImportAll' ) );
	    add_action( 'my_materialpool_import',      array( 'MyMaterialpool', 'callbackImportAll' ) );

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
        global $myMaterialpoolKeywordCount;
		$atts = array_change_key_case((array)$atts, CASE_LOWER);
		$scatts = shortcode_atts([
			'view' => '',
            'facets' => 'medientyp, bildungsstufe, altersstufe, materialschlagworte',
            'schlagwortfilter' => '',
            'medientypfilter' => '',
            'altersstufefilter' => '',
            'bildungsstufefilter' => '',
            'min_schlagwort_treffer' => '',
		], $atts );

		$myMaterialpoolKeywordCount = $scatts[ 'min_schlagwort_treffer' ];
        global $paged;
        global $the_query;
		global $wp;

		if ( ! $paged ) {
			$paged = 1;
		}

		if ( $scatts[ 'schlagwortfilter' ]  != '' ) {
			$schlagwortfilterArray = explode( ',', $scatts[ 'schlagwortfilter' ]  );
			foreach ( $schlagwortfilterArray as $key => $item ) {
				$taxArray2[] = array(
					'taxonomy' => 'materialschlagworte',
					'field'    => 'slug',
					'terms'    => trim ( $item ),
				);
			}
		}

		if ( $scatts[ 'medientypfilter' ]  != '' ) {
			$medientypfilterArray = explode( ',', $scatts[ 'medientypfilter' ]  );
			foreach ( $medientypfilterArray as $key => $item ) {
				$taxArray2[] = array(
					'taxonomy' => 'medientyp',
					'field'    => 'slug',
					'terms'    => trim ( $item ),
				);
			}
		}


		if ( $scatts[ 'altersstufefilter' ]  != '' ) {
			$altersstufefilterArray = explode( ',', $scatts[ 'altersstufefilter' ]  );
			foreach ( $altersstufefilterArray as $key => $item ) {
				$taxArray2[] = array(
					'taxonomy' => 'altersstufe',
					'field'    => 'slug',
					'terms'    => trim ( $item ),
				);
			}
		}

		if ( $scatts[ 'bildungsstufefilter' ]  != '' ) {
			$bildungsstufefilterArray = explode( ',', $scatts[ 'bildungsstufefilter' ]  );
			foreach ( $bildungsstufefilterArray as $key => $item ) {
				$taxArray2[] = array(
					'taxonomy' => 'bildungsstufe',
					'field'    => 'slug',
					'terms'    => trim ( $item ),
				);
			}
		}

		$bildungsstufeArray = array();
		$bildungsstufen     = get_query_var( "mpoolfacet_bildungsstufe" );
		if ( is_array( $bildungsstufen ) ) {
			foreach ( $bildungsstufen as $bildungsstufe ) {
				$taxArray[] = array(
					'taxonomy' => 'bildungsstufe',
					'field'    => 'slug',
					'terms'    => $bildungsstufe,
				);
			}
		}

		$medientypeArray = array();
		$medientypen     = get_query_var( "mpoolfacet_medientyp" );
		if ( is_array( $medientypen ) ) {
			foreach ( $medientypen as $medientype ) {
				$taxArray[] = array(
					'taxonomy' => 'medientyp',
					'field'    => 'slug',
					'terms'    => $medientype,
				);
			}
		}
		$altersstufeArray = array();
		$altersstufen     = get_query_var( "mpoolfacet_altersstufe" );
		if ( is_array( $altersstufen ) ) {
			foreach ( $altersstufen as $altersstufe ) {
				$taxArray[] = array(
					'taxonomy' => 'altersstufe',
					'field'    => 'slug',
					'terms'    => $altersstufe,
				);
			}
		}

		$materialschlagworteArray = array();
		$materialschlagworte     = get_query_var( "mpoolfacet_materialschlagworte" );
		if ( is_array( $materialschlagworte ) ) {
			foreach ( $materialschlagworte as $materialschlagwort ) {
				$taxArray[] = array(
					'taxonomy' => 'materialschlagworte',
					'field'    => 'slug',
					'terms'    => $materialschlagwort,
				);
			}
		}

		$materialrubrikArray = array();
		$materialrubriken     = get_query_var( "mpoolfacet_rubrik" );
		if ( is_array( $materialrubriken ) ) {
			foreach ( $materialrubriken as $materialrubrik ) {
				$taxArray[] = array(
					'taxonomy' => 'rubrik',
					'field'    => 'slug',
					'terms'    => $materialrubrik,
				);
			}
		}

		$taxquery = array();
		if ( isset( $taxArray ) && is_array( $taxArray ) ) {
			if ( count( $taxArray ) > 1 ) {
				$taxquery['relation'] = 'AND';
			}
			foreach ( $taxArray as $tax ) {
				$taxquery[] = $tax;
			}
		}
		if ( isset( $taxArray2 ) && is_array( $taxArray2 ) ) {
			if ( empty( $taxquery )   ) {
				$taxquery['relation'] = 'AND';
			}
			foreach ( $taxArray2 as $tax ) {
				$taxquery[] = $tax;
			}
		}

		$search = get_query_var( "mp-search" );
		$args   = array(
			'post_type'      => 'material',
			'posts_per_page' => - 1,
			'tax_query'      => $taxquery,
			's'              => $search
		);

		$the_query = new WP_Query( $args );
		$anzahl    = $the_query->post_count;
		$args      = array(
			'post_type'      => 'material',
			'paged'          => $paged,
			'posts_per_page' => 10,
			'tax_query'      => $taxquery,
			's'              => $search
		);



		if ( $scatts[ 'view' ]  == '' ) {
            $content = '';
			$template = get_option('mympool-templateview', self::$template_view  );


			$the_query = new WP_Query( $args );
			$taxs = get_taxonomies( array( 'public' => true, 'query_var' => true ), 'objects' );
			foreach ( $taxs as $tax ) {
				if ( $tax->name != 'medientyp' && $tax->name != 'bildungsstufe' && $tax->name != 'altersstufe' && $tax->name != 'materialschlagworte'&& $tax->name != 'rubrik') {
					continue;
				}
				if ( $scatts[ 'facets' ]  != '' ) {
				    $facetArray = explode( ',', $scatts[ 'facets' ]  );
					foreach ( $facetArray as $key => $item ) {
					    if ( trim( $item )  == 'schlagwort' ) {
					        $item = "materialschlagworte";
                        }
                        $facetArray[ $key ] = trim ( $item );
				    }
				    if ( ! in_array( $tax->name, $facetArray ) ) {
				        continue;
				    }
				}
				if ( ! $tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
					continue;
				} else if ( $tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
					$termID = term_exists( get_query_var( $tax->query_var ) );
					$terms  = get_terms( $tax->name, array( 'child_of' => $termID ) );
				} else {
					$terms = get_terms( $tax->name );
				}
				if ( sizeof( $terms ) == 0 ) {
					continue;
				}

				add_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ), 10, 3 );
				add_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter' ), 10, 3 );
				if ( $tax->name == 'materialschlagworte' ) {
    				add_filter( 'list_cats', array( 'MyMaterialpool', 'modify_list_cats' ), 10, 2 );
				}
				ob_start();
				wp_list_categories( array(
					'taxonomy'   => $tax->name,
					'show_count' => true,
					'title_li'   => $tax->labels->name,
				) );
				switch ( $tax->name ) {
                    case 'medientyp':
                        $obmedientyp = ob_get_contents();
                        break;
					case 'bildungsstufe':
						$obbildungsstufe = ob_get_contents();
						break;
					case 'altersstufe':
						$obaltersstufe = ob_get_contents();
						break;
					case 'materialschlagworte':
						$obmaterialschlagworte = ob_get_contents();
						break;
					case 'rubrik':
						$obrubrik = ob_get_contents();
						break;
                }
				ob_clean();
				remove_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ) );
				remove_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter' ) );
				if ( $tax->name == 'materialschlagworte' ) {
					remove_filter( 'list_cats', array( 'MyMaterialpool', 'modify_list_cats' ));
				}
			}
			// Facetten in Reihenfolge ausgeben
			$out = explode( ',', $scatts[ 'facets' ] ) ;
			foreach ( $out as $outfacet ) {
				$outfacet = trim( $outfacet );
				switch ( $outfacet ) {
					case 'medientyp':
						$content .= $obmedientyp;
						break;
					case 'bildungsstufe':
						$content .= $obbildungsstufe;
						break;
					case 'altersstufe':
						$content .= $obaltersstufe;
						break;
					case 'schlagwort':
						$content .= $obmaterialschlagworte;
						break;
					case 'rubrik':
						$content .= $obrubrik;
						break;
				}
			}

			$template = str_replace( '{facetlist}', $content, $template ); $content = '';
			$template = str_replace( '{searchquery}', $search, $template );

			if ( $the_query->have_posts() ) {
				if ( isset( $taxArray ) && is_array( $taxArray ) ) {
					$facet = '';
					foreach ( $taxArray as $tax ) {

						if ( is_array( $tax ) ) {
							$facet .= " <a href='" . self::removeUrl( $_SERVER['REQUEST_URI'], $tax['taxonomy'], $tax['terms'] ) . "'>" . $tax['terms'] . "</a> ";
						}
					}
				}
				if ( $facet != '' ) {
					$content .= "Facetten entfernen: " . $facet . "<br>";
				}
				$template = str_replace( '{removefacets}', $content, $template );$content = '';
				$template = str_replace( '{pagination}', self::get_pagination( $the_query ), $template );$content = '';
				$template = str_replace( '{anzahl}', "Anzahl: " . $anzahl, $template );$content = '';

				while ( $the_query->have_posts() ) {
					$template2 = get_option( 'mympool-template', self::$template );
					$the_query->the_post();
					$template2 = str_replace( '{material_title}', get_the_title(), $template2 );
					$template2 = str_replace( '{material_url}', get_metadata( 'post', get_the_ID(), 'material_url', true ), $template2 );
					$template2 = str_replace( '{material_review_url}', get_metadata( 'post', get_the_ID(), 'material_review_url', true ), $template2 );
					$template2 = str_replace( '{material_kurzbeschreibung}', get_metadata( 'post', get_the_ID(), 'material_kurzbeschreibung', true ), $template2 );
					$template2 = str_replace( '{material_beschreibung}', get_metadata( 'post', get_the_ID(), 'material_beschreibung', true ), $template2 );
					$template2 = str_replace( '{material_screenshot}', '<img src="' . get_metadata( 'post', get_the_ID(), 'material_screenshot', true ) . '" class="mymaterial_cover">', $template2 );
					$template2 = str_replace( '{material_schlagworte}', self::get_schlagworte( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_schlagworte_link}', self::get_schlagworte_link( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_rubriken}', self::get_rubriken( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_autoren}', self::get_autoren( get_the_ID() ), $template2 );

					$content  .= $template2;
				}
				$template = str_replace( '{results}', $content, $template );$content = '';
			}
            $content = $template;
		}
		if ( $scatts[ 'view' ]  == 'result' ) {
			$content = '';
			$template = get_option('mympool-templateviewresult', self::$template_view  );

			$the_query = new WP_Query( $args );
			$template = str_replace( '{searchquery}', $search, $template );

			if ( $the_query->have_posts() ) {
				if ( isset( $taxArray ) && is_array( $taxArray ) ) {
					$facet = '';
					foreach ( $taxArray as $tax ) {

						if ( is_array( $tax ) ) {
							$facet .= " <a href='" . self::removeUrl( $_SERVER['REQUEST_URI'], $tax['taxonomy'], $tax['terms'] ) . "'>" . $tax['terms'] . "</a> ";
						}
					}
				}
				if ( $facet != '' ) {
					$content .= "Facetten entfernen: " . $facet . "<br>";
				}
				$template = str_replace( '{removefacets}', $content, $template );$content = '';
				$template = str_replace( '{pagination}', self::get_pagination( $the_query ), $template );$content = '';
				$template = str_replace( '{anzahl}', "Anzahl: " . $anzahl, $template );$content = '';

				while ( $the_query->have_posts() ) {
					$template2 = get_option( 'mympool-template', self::$template );
					$the_query->the_post();
					$template2 = str_replace( '{material_title}', get_the_title(), $template2 );
					$template2 = str_replace( '{material_url}', get_metadata( 'post', get_the_ID(), 'material_url', true ), $template2 );
					$template2 = str_replace( '{material_review_url}', get_metadata( 'post', get_the_ID(), 'material_review_url', true ), $template2 );
					$template2 = str_replace( '{material_kurzbeschreibung}', get_metadata( 'post', get_the_ID(), 'material_kurzbeschreibung', true ), $template2 );
					$template2 = str_replace( '{material_beschreibung}', get_metadata( 'post', get_the_ID(), 'material_beschreibung', true ), $template2 );
					$template2 = str_replace( '{material_screenshot}', '<img src="' . get_metadata( 'post', get_the_ID(), 'material_screenshot', true ) . '" class="mymaterial_cover">', $template2 );
					$template2 = str_replace( '{material_schlagworte}', self::get_schlagworte( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_schlagworte_link}', self::get_schlagworte_link( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_rubriken}', self::get_rubriken( get_the_ID() ), $template2 );
					$template2 = str_replace( '{material_autoren}', self::get_autoren( get_the_ID() ), $template2 );

					$content  .= $template2;
				}
				$template = str_replace( '{results}', $content, $template );$content = '';
			}

			$content = $template;

		}
		if ( $scatts[ 'view' ] == 'facets' ) {
			$content = '';
			$template = get_option('mympool-templateviewfacet', self::$template_viewfacet  );

            $the_query = new WP_Query( $args );

            $taxs = get_taxonomies( array( 'public' => true, 'query_var' => true ), 'objects' );
            foreach ( $taxs as $tax ) {
                if ( $tax->name != 'medientyp' && $tax->name != 'bildungsstufe' && $tax->name != 'altersstufe' && $tax->name != 'materialschlagworte') {
                    continue;
                }
                if ( $scatts[ 'facets' ]  != '' ) {
                    $facetArray = explode( ',', $scatts[ 'facets' ]  );
                    foreach ( $facetArray as $key => $item ) {
                        if ( trim( $item )  == 'schlagwort' ) {
                            $item = "materialschlagworte";
                        }
                        $facetArray[ $key ] = trim ( $item );
                    }
                    if ( ! in_array( $tax->name, $facetArray ) ) {
                        continue;
                    }
                }
                if ( ! $tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
                    continue;
                } else if ( $tax->hierarchical && self::tax_in_query( $tax->name, $the_query ) ) {
                    $termID = term_exists( get_query_var( $tax->query_var ) );
                    $terms  = get_terms( $tax->name, array( 'child_of' => $termID ) );
                } else {
                    $terms = get_terms( $tax->name );
                }
                if ( sizeof( $terms ) == 0 ) {
                    continue;
                }

                add_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ), 10, 3 );
                add_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter' ), 10, 3 );
                if ( $tax->name == 'materialschlagworte' ) {
                    add_filter( 'list_cats', array( 'MyMaterialpool', 'modify_list_cats' ), 10, 2 );
                }
                ob_start();
                wp_list_categories( array(
                    'taxonomy'   => $tax->name,
                    'show_count' => true,
                    'title_li'   => $tax->labels->name,
                ) );
                switch ( $tax->name ) {
                    case 'medientyp':
                        $obmedientyp = ob_get_contents();
                        break;
                    case 'bildungsstufe':
                        $obbildungsstufe = ob_get_contents();
                        break;
                    case 'altersstufe':
                        $obaltersstufe = ob_get_contents();
                        break;
                    case 'materialschlagworte':
	                    $obmaterialschlagworte = ob_get_contents();
                        break;
                    case 'rubrik':
                        $obrubrik = ob_get_contents();
                        break;
                }
                ob_clean();
                remove_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ) );
                remove_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter' ) );
                if ( $tax->name == 'materialschlagworte' ) {
                    remove_filter( 'list_cats', array( 'MyMaterialpool', 'modify_list_cats' ) );
                }
            }

            // Facetten in Reihenfolge ausgeben
			$out = explode( ',', $scatts[ 'facets'] ) ;
            foreach ( $out as $outfacet ) {
                $outfacet = trim( $outfacet );
                switch ( $outfacet ) {
	                case 'medientyp':
		                $content .= $obmedientyp;
		                break;
	                case 'bildungsstufe':
		                $content .= $obbildungsstufe;
		                break;
	                case 'altersstufe':
		                $content .= $obaltersstufe;
		                break;
	                case 'schlagwort':
		                $content .= $obmaterialschlagworte;
		                break;
	                case 'rubrik':
		                $content .= $obrubrik;
		                break;
                }
            }

			$template = str_replace( '{facetlist}', $content, $template );
			$content = $template;
        }
        wp_reset_query();
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
		$vars[] = "mpoolfacet_materialschlagworte";
		$vars[] = "mpoolfacet_rubrik";
		$vars[] = "mp-search";
		return $vars;
	}

	public static function term_link_filter( $termlink, $term, $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		return esc_url(  preg_replace ( '/\/page\/.*\//','',  add_query_arg( 'mpoolfacet_'.$tax->query_var.'[]', $term->slug ) ) ) ;
	}

	public static  function get_terms_filter( $terms, $taxonomies, $args ) {
		global $the_query;
		remove_filter( 'term_link', array( 'MyMaterialpool', 'term_link_filter' ) );
		remove_filter( 'get_terms', array( 'MyMaterialpool', 'get_terms_filter') );
		$tax = get_taxonomy( $taxonomies[0] );
		$search = get_query_var("mp-search");

		foreach ( $terms as $id => &$term ) {
			$tax_query = $the_query->tax_query->queries;
			$tax_query['relationship'] = 'AND';
			$tax_query[] =	array(
				'taxonomy' 	=> 	$tax->name,
				'field' 	=> 	'slug',
				'terms'		=> 	$term->slug,

			);
			$query = new WP_Query( array( 's' =>  $search,'tax_query' => $tax_query ) );
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
	    self::registerTaxonomyMaterialschlagworte();
	    self::registerTaxonomyRubrik();
    }

    public static function addSettingsMenu() {
		add_submenu_page(
			'edit.php?post_type=material',
			'Einstellungen',
			'Einstellungen',
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
		register_setting( 'my-materialppool-settings-group', 'mympool-templateview' );
		register_setting( 'my-materialppool-settings-group', 'mympool-templateviewfacet' );
		register_setting( 'my-materialppool-settings-group', 'mympool-templateviewresult' );
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
                        <td>
                            <textarea name="mympool-urls" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-urls' ) ); ?></textarea>
                            <p>
                                Urls: <br>
                                Alle Materialien - https://material.rpi-virtuell.de/wp-json/wp/v2/material <br>
                                Material einer Bildungsstufe - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?bildungsstufe={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?bildungsstufe=youth<br><br>
                                Material eines Medientypes - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?medientyp={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?medientyp=video<br><br>
                                Material einer Altersstufe - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?altersstufe={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?altersstufe=18-99<br><br>
                                Material eines/r Autor(in) - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?author={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?autor=joerg-lohrer<br><br>
                                Material einer Einrichtung - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?einrichtung={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?einrichtung=forum-erwachsenenbildung<br><br>
                                Inklusives Material - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?inklusion=inclusive<br><br>
                                Material zu einem Schlagwort - https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material/?schlagwort={slug} <br>
                                Beispiel: https://material.rpi-virtuell.de/wp-json/mymaterial/v1/material?schlagwort=jesus<br><br>
                            </p>
                         </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Maximale Anzahl (pro URL)</th>
                        <td><input type="text" name="mympool-max-count" class=" code" value="<?php echo esc_attr( get_option('mympool-max-count', 100 ) ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Timeout (in Sekunden, default: 5)</th>
                        <td><input type="text" name="mympool-timeout" class=" code" value="<?php echo esc_attr( get_option('mympool-timeout', 30 ) ); ?>"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Template eines Ergebnisblocks</th>
                        <td><textarea name="mympool-template" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-template', self::$template ) ); ?></textarea>
                        <p>
                            Folgende Macros sind möglich: {material_title}, {material_url}, {material_review_url}, {material_kurzbeschreibung}, {material_beschreibung}, {material_screenshot}, {material_schlagworte}, {material_schlagworte_link}, {material_rubriken}, {material_autoren}


                        </p></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Template Ansicht</th>
                        <td><textarea name="mympool-templateview" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-templateview', self::$template_view ) ); ?></textarea>
                            <p>
                                Folgende Macros sind möglich: {facetlist}, {searchquery}, {removefacets}, {pagination}, {anzahl}, {results}
                           </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Template view=facet</th>
                        <td><textarea name="mympool-templateviewfacet" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-templateviewfacet', self::$template_viewfacet ) ); ?></textarea>
                            <p>
                                Folgende Macros sind möglich: {facetlist}
                            </p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row">Template view=result</th>
                        <td><textarea name="mympool-templateviewresult" class="large-text code" rows="8" ><?php echo esc_attr( get_option('mympool-templateviewresult', self::$template_viewresult ) ); ?></textarea>
                            <p>
                                Folgende Macros sind möglich: {searchquery}, {removefacets}, {pagination}, {anzahl}, {results}
                            </p>
                        </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row">Shortcodes</th>
                        <td><p>
                            [mymaterialpool] gibt Facetten und Suchergebnisliste aus.<br>
                            [mymaterialpool view="result"] gibt die Suchergebnisliste aus.<br>
                            [mymaterialpool view="facet"] gibt die Facetten aus.<br>
                            [mymaterialpool facets="bildungsstufe"] Beschränkt die Facetten auf die Facette Bildungsstufe.<br>
                            [mymaterialpool facets="bildungsstufe, altersstufe"] Beschränkt die Facetten auf die Facetten Bildungsstufe und Alterstufe.<br>
                            Mögliche Facetten: altersstufe, bildungsstufe, medientyp, schlagwort, rubrik<br>
                            [mymaterialpool bildungsstufefilter="schulstufen"] Filtert die zur Verfügung Materialien. Mögliche Filter sind  altersstufefilter, bildungsstufefilter, medientypfilter und schlagwortfilter.<br>
                            [mymaterialpool bildungsstufefilter="schulstufen" medientypfilter="praxishilfen"] Filtert auf Materialen der Bildungsstufen Schulstufen UND des Medientyps Praxishilfen<br>
                            [mymaterialpool schlagwortfilter="App,E-learning"] Filter auf Materialien mit den Schlagworten App UND E-Learnung.<br>
                            [mymaterialpool min_schlagwort_treffer=3] Gibt nur noch Schlagworte in der Facette aus, die min x Treffer haben.<br>
                            </p>
                            </td>
                    </tr>


                    <tr valign="top">
                        <th scope="row">Statistik</th>
                        <td>
                            Materialien: <?php echo self::countMaterial(); ?><br>
                            Bildungsstufen: <?php echo self::countBildungsstufen(); ?><br>
                            Medientypen: <?php echo self::countMedientypen(); ?><br>
                            Altersstufen: <?php echo self::countAltersstufen(); ?><br>
                            Schlagworte: <?php echo self::countSchlagworte(); ?><br>
                            Rubriken:  <?php echo self::countRubriken(); ?><br>
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

	public static function countSchlagworte() {
		return wp_count_terms( 'materialschlagworte' );
	}

	public static function countRubriken() {
		return wp_count_terms( 'rubrik' );
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
		error_log( 'in importMaterial');
		global $wpdb;
        $import = true;
        $max = get_option( 'mympool-max-count', 5 );
        $count = 0;
        $page = 1;
        while ( $import ) {
            $url = add_query_arg( 'page', $page, $url );
	        error_log( ' import :' . $url );

	        $response = self::getRemoteMaterial( $url );
	        if ( $response === false ) {
	            $import = false;
            }
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
				        'post_content' => $remote_item_data['material_beschreibung']. ' ',
				        'post_status'  => 'publish',
				        'post_author'  => get_current_user_id(),
				        'post_type'    => 'material',
				        'meta_input'   => array(
					        'material_url'              => $remote_item_data['material_url'],
					        'material_review_url'       => $remote_item_data['material_review_url'],
					        'material_titel'            => $remote_item_data['material_titel'],
					        'material_kurzbeschreibung' => $remote_item_data['material_kurzbeschreibung'],
					        'material_beschreibung'     => $remote_item_data['material_beschreibung'],
					        'material_screenshot'       => $remote_item_data['material_screenshot'],
					        'material_autoren'          => $remote_item_data['material_autoren'],
				        ),
			        );
			        // Prüfen ob Slug schon vorhanden.
			        $id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM " . $wpdb->posts . " WHERE post_name = '%s' AND  post_type = 'material'  ", $remote_item_data['slug'] ) );
			        if ( empty( $id ) ) {
				        $materialid = wp_insert_post( $post_arr );
				        // Taxonomien dazu speichern.
                        foreach ( $remote_item_data['material_altersstufe']  as $tax ) {
                            wp_set_post_terms( $materialid, $tax[ 'name' ], 'altersstufe');
                        }
				        foreach ( $remote_item_data['material_medientyp']  as $tax ) {
					        $term = term_exists( $tax[ 'name' ], 'medientyp' );
					        if ( 0 !== $term && null !== $term ) {
						        wp_set_post_terms( $materialid, $term[ 'term_id'], 'medientyp');
					        }
				        }
				        foreach ( $remote_item_data['material_bildungsstufe']  as $tax ) {
					        $term = term_exists( $tax[ 'name' ], 'bildungsstufe' );
					        if ( 0 !== $term && null !== $term ) {
						        wp_set_post_terms( $materialid, $term[ 'term_id'], 'bildungsstufe');
					        }
				        }
				        // Schlagworte hinzufügen
				        foreach ( $remote_item_data['material_schlagworte']  as $tax ) {
					        wp_set_post_terms( $materialid, $tax, 'materialschlagworte', true );
				        }
				        // Rubrik hinzufügen
				        foreach ( $remote_item_data['material_rubrik']  as $tax ) {
					        wp_set_post_terms( $materialid, $tax, 'rubrik', true );
				        }

			        } else {
				        error_log( ' ignoring post :' . $remote_item_data['slug'] );
			        }
			        $count++;
		        }
                $page++;
	        } else {
	            $import = false;
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

	public static function registerTaxonomyMaterialschlagworte() {
		register_taxonomy(
			'materialschlagworte',
			'material',
			array(
				'label' => __( 'Schlagworte' ),
				'rewrite' => array( 'slug' => 'materialschlagworte' ),
			)
		);
	}

	public static function registerTaxonomyRubrik() {
		register_taxonomy(
			'rubrik',
			'material',
			array(
				'label' => __( 'Rubriken' ),
				'rewrite' => array( 'slug' => 'rubrik' ),
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
		    return false;
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
		self::deleteTerms( 'materialschlagworte' );
		self::deleteTerms( 'rubrik' );
		self::deletePosts( 'material' );
				wp_die();
	}

	public static function callbackImportAll() {
		error_log( ' callbackImportAll');
		$url = get_option( 'mympool-urls' );
		self::importMedientyp();
		self::importBildungsstufen();
		$elements = explode( "\n", $url );
		foreach ( $elements as $element ) {
			error_log( ' import : - : ' . $element );
			self::importMaterial( $element );
        }
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

	public function get_schlagworte( $materialID ) {
		$keywords = wp_get_object_terms ( $materialID, 'materialschlagworte' );
		$back = '';
		if ( !empty( $keywords ) ) {
			if ( ! is_wp_error( $keywords ) ) {
			    $count = 0;
				foreach( $keywords as $term ) {
					if ( $count > 0 ) {
						$back .= ', ';
					}
				    $back .= $term->name;
                    $count++;
				}
			}
        }
        return $back;
    }

	public function get_schlagworte_link ( $materialID ) {
		$keywords = wp_get_object_terms ( $materialID, 'materialschlagworte' );
		$back = '';
		if ( !empty( $keywords ) ) {
			if ( ! is_wp_error( $keywords ) ) {
				$count = 0;
				foreach( $keywords as $term ) {
					if ( $count > 0 ) {
						$back .= ', ';
					}
					$back .= "<a class='mypoolschlagwortlink' href='?mpoolfacet_materialschlagworte%5B%5D=" . $term->slug . "'>" . $term->name . "</a>";
					$count++;
				}
			}
		}
		return $back;
	}

	public function get_rubriken( $materialID ) {
		$keywords = wp_get_object_terms ( $materialID, 'rubrik' );
		$back = '';
		if ( !empty( $keywords ) ) {
			if ( ! is_wp_error( $keywords ) ) {
				$count = 0;
				foreach( $keywords as $term ) {
					if ( $count > 0 ) {
						$back .= ', ';
					}
					$back .= $term->name;
					$count++;
				}
			}
		}
		return $back;
	}


	public function get_autoren( $materialID ) {
		$autoren = get_post_meta( $materialID, 'material_autoren', true );
		$back = '';
		if ( !empty( $autoren ) ) {
			$count = 0;
            foreach( $autoren as $autor ) {
                if ( $count > 0 ) {
                    $back .= ', ';
                }
                $back .= $autor[ 'name' ];
                $count++;
            }
		}
		return $back;
	}


	public function modify_list_cats ( $name, $category ) {
        global $myMaterialpoolKeywordCount;

        if (  $myMaterialpoolKeywordCount == '') {
	        return $name;
        } else {
	        if ( $category->count >= (int) $myMaterialpoolKeywordCount ) {
		        return $name;
	        } else {
		        return '';
	        }
        }
    }
}

add_action( 'init',array( 'MyMaterialpool', 'init' ) );
add_action ( 'plugins_loaded', function(){
	$myMaterialpool = new MyMaterialpool();
});

register_deactivation_hook( __FILE__, 'my_materialpool_deactivate' );
function my_materialpool_deactivate() {
	$timestamp = wp_next_scheduled( 'my_materialpool_import' );
	wp_unschedule_event( $timestamp, 'my_materialpool_import' );
}
register_activation_hook( __FILE__, 'my_materialpool_activate' );
function my_materialpool_activate() {
	if ( ! wp_next_scheduled( 'my_materialpool_import' ) ) {
		wp_schedule_event( time(), 'daily', 'my_materialpool_import' );
	}
}
