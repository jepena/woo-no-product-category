<?php
/*
    Plugin Name:    Woo No Product Category Base URL
    Plugin URI:     
    Description:    Removes '/product-category' from your product-category permalinks.
    Author:         Jacinto Pena Jr
    Author URI:     https://jepena.github.io/
    License:        GPL-2.0+
    License URI:    http://www.gnu.org/licenses/gpl-2.0.txt
    Text Domain: 	woo-no-product-categrogy
    Domain Path: 	/languages
*/
	
	
	// Refresh rules on activation/deactivation/category changes
	register_activation_hook(__FILE__, 'no_category_base_refresh_rules');
	add_action('created_category', 'no_category_base_refresh_rules');
	add_action('edited_category', 'no_category_base_refresh_rules');
	add_action('delete_category', 'no_category_base_refresh_rules');
	
	$taxonomyOptions = array() ;
	
	function no_category_base_refresh_rules() 
	{
	        global $wp_rewrite;
	        $wp_rewrite -> flush_rules();
	}
	
	register_deactivation_hook(__FILE__, 'no_category_base_deactivate');
	function no_category_base_deactivate() {
	        remove_filter('product_rewrite_slug', 'no_category_base_rewrite_rules');
	        // We don't want to insert our custom rules again
	        no_category_base_refresh_rules();
	}
	
	// Remove category base
	add_action('init', 'no_category_base_permastruct');
	function no_category_base_permastruct() {
        global $wp_rewrite, $wp_version;
        if (version_compare($wp_version, '3.4', '<')) {   
            // For pre-3.4 support
            $wp_rewrite -> extra_permastructs['product_cat'][0] = '%product_cat%';
        } else {
            $wp_rewrite -> extra_permastructs['product_cat']['struct'] = '%product_cat%';
        }
	}
	
	// Add our custom category rewrite rules
	add_filter('product_rewrite_slug', 'no_category_base_rewrite_rules');
	function no_category_base_rewrite_rules($category_rewrite) {
	        //var_dump($category_rewrite); // For Debugging
	
	        $category_rewrite = array();
	        $categories = get_categories(array('hide_empty' => false));
	        foreach ($categories as $category) {
	                $category_nicename = $category -> slug;
	                if ($category -> parent == $category -> cat_ID)// recursive recursion
	                        $category -> parent = 0;
	                elseif ($category -> parent != 0)
	                        $category_nicename = get_category_parents($category -> parent, false, '/', true) . $category_nicename;
	                $category_rewrite['(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
	                $category_rewrite['(' . $category_nicename . ')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
	                $category_rewrite['(' . $category_nicename . ')/?$'] = 'index.php?category_name=$matches[1]';
	        }
	        // Redirect support from Old Category Base
	        global $wp_rewrite;
	        $old_category_base = get_option('permalink_structure') ? get_option('permalink_structure') : 'category';
	        $old_category_base = trim($old_category_base, '/');
	        $category_rewrite[$old_category_base . '/(.*)$'] = 'index.php?category_redirect=$matches[1]';
	
	        //var_dump($category_rewrite); // For Debugging
	        return $category_rewrite;
	}
	
	// For Debugging
	//add_filter('rewrite_rules_array', 'no_category_base_rewrite_rules_array');
	//function no_category_base_rewrite_rules_array($category_rewrite) {
	//      var_dump($category_rewrite); // For Debugging
	//}
	
	// Add 'category_redirect' query variable
	add_filter('query_vars', 'no_category_base_query_vars');
	function no_category_base_query_vars($public_query_vars) {
	        $public_query_vars[] = 'category_redirect';
	        return $public_query_vars;
	}
	
	// Redirect if 'category_redirect' is set
	add_filter('request', 'no_category_base_request');
	function no_category_base_request($query_vars) {
        //print_r($query_vars); // For Debugging
        if (isset($query_vars['category_redirect'])) {
                $catlink = trailingslashit(get_option('home')) . user_trailingslashit($query_vars['category_redirect'], 'product_cat');
                status_header(301);
                header("Location: $catlink");
                exit();
        }
        return $query_vars;
	}

    function isHierarchical( $type )
    {
        return $type === 'hierarchical';
    }
    
    function addRewriteRules( $rules )
    {
        if ( empty($taxonomyOptions) ) {
            return $rules;
        }
        wp_cache_flush();
        global  $wp_rewrite ;
        $feed = '(' . trim( implode( '|', $wp_rewrite->feeds ) ) . ')';
        $customRules = [];
        /**
         * Remove WPML filters while getting terms, to get all languages
         */
        
        if ( isset( $GLOBALS['sitepress'] ) ) {
            $sitepress = $GLOBALS['sitepress'];
            $has_get_terms_args_filter = remove_filter( 'get_terms_args', array( $sitepress, 'get_terms_args_filter' ) );
            $has_get_term_filter = remove_filter( 'get_term', array( $sitepress, 'get_term_adjust_id' ), 1 );
            $has_terms_clauses_filter = remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
        }
        
        foreach ( $taxonomyOptions as $taxonomy => $option ) {
            
            if ( !empty($option) ) {
                $terms = get_categories( [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                ] );
                $hierarchical = $this->isHierarchical( $option );
                $suffix = false;
                foreach ( $terms as $term ) {
                    $slug = $this->buildTermPath( $term, $hierarchical, $suffix );
                    $customRules["{$slug}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug;
                    $customRules["{$slug}/embed/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&embed=true';
                    $customRules["{$slug}/{$wp_rewrite->feed_base}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                    $customRules["{$slug}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                    $customRules["{$slug}/{$wp_rewrite->pagination_base}/?([0-9]{1,})/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&paged=$matches[1]';
                    // Polylang compatibility
                    // $polylangURLslug = $this->getPolylangLangSlug();
                    
                    if ( $polylangURLslug ) {
                        $slug = $polylangURLslug . $slug;
                        $customRules["{$slug}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug;
                        $customRules["{$slug}/embed/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&embed=true';
                        $customRules["{$slug}/{$wp_rewrite->feed_base}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                        $customRules["{$slug}/{$feed}/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&feed=$matches[1]';
                        $customRules["{$slug}/{$wp_rewrite->pagination_base}/?([0-9]{1,})/?\$"] = 'index.php?' . $taxonomy . '=' . $term->slug . '&paged=$matches[1]';
                    }
                
                }
            }
        
        }
        /**
         * Register WPML filters back
         */
        
        if ( isset( $sitepress ) ) {
            if ( !empty($has_terms_clauses_filter) ) {
                add_filter(
                    'terms_clauses',
                    array( $sitepress, 'terms_clauses' ),
                    10,
                    3
                );
            }
            if ( !empty($has_get_term_filter) ) {
                add_filter(
                    'get_term',
                    array( $sitepress, 'get_term_adjust_id' ),
                    1,
                    1
                );
            }
            if ( !empty($has_get_terms_args_filter) ) {
                add_filter(
                    'get_terms_args',
                    array( $sitepress, 'get_terms_args_filter' ),
                    10,
                    2
                );
            }
        }
        
        return $customRules + $rules;
    }
    add_filter( 'rewrite_rules_array', 'addRewriteRules', 99 );

?>
  
