<?php
/**
 * Query Optimizer - Reduces database queries
 *
 * @package kp_Database
 */

namespace KPT\WordPress;

use KPT\Logger;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// make sure we dont already load this class
if( ! class_exists( 'KPTDB_Query_Optimizer ') ) {

    class KPTDB_Query_Optimizer {
        
        /**
         * Initialize query optimizations
         */
        public static function init() {
            
            // Only optimize on frontend
            if ( is_admin() ) {
                return;
            }
            
            // Optimize queries
            add_action( 'pre_get_posts', [ __CLASS__, 'optimize_main_query' ], 1 );
            add_filter( 'posts_request', [ __CLASS__, 'optimize_post_queries' ], 10, 2 );
            
            // Prevent duplicate queries
            add_filter( 'posts_clauses', [ __CLASS__, 'prevent_count_queries' ], 10, 2 );
            
            // Cache expensive joins
            add_filter( 'posts_join', [ __CLASS__, 'cache_expensive_joins' ], 10, 2 );

            // cache widget queries
            add_filter( 'sidebars_widgets', [ __CLASS__, 'cache_widget_queries' ] );
        }
        
        /**
         * Optimize the main query
         */
        public static function optimize_main_query( $query ) {
            if ( ! $query->is_main_query() || is_admin() ) {
                return;
            }
            
            // Skip SQL_CALC_FOUND_ROWS on archives (saves 1 heavy query)
            if ( is_archive() || is_home() ) {
                $query->set( 'no_found_rows', true );
            }
            
            // set the fields to be selected and not just *
            if ( is_archive() || is_home() || is_search() ) {
                add_filter( 'posts_fields', function( $fields, $q ) use ( $query ) {
                    if ( $q === $query ) {
                        global $wpdb;

                        // Only get essential fields
                        $fields = "$wpdb->posts.ID, $wpdb->posts.post_author, $wpdb->posts.post_date, 
                                $wpdb->posts.post_title, $wpdb->posts.post_excerpt, $wpdb->posts.post_status, 
                                $wpdb->posts.comment_status, $wpdb->posts.post_name, $wpdb->posts.post_modified, 
                                $wpdb->posts.post_parent, $wpdb->posts.guid, $wpdb->posts.post_type, $wpdb->posts.post_content, 
                                $wpdb->posts.comment_count";
                    }
                    return $fields;
                }, 10, 2 );
            }
            
            // Limit post meta queries
            if ( ! is_single() && ! is_page() ) {
                $query->set( 'update_post_meta_cache', false );
            }
            
            // Limit term queries  
            if ( ! is_single() && ! is_tax() && ! is_category() && ! is_tag() ) {
                $query->set( 'update_post_term_cache', false );
            }
            
            Logger::debug( 'Main query optimized' );
        }

        /**
         * Cache widget queries
         */
        public static function cache_widget_queries( $sidebars_widgets ) {
            if ( is_admin() ) {
                return $sidebars_widgets;
            }
            
            // Pre-cache all widget options
            global $wpdb;
            $widget_options = $wpdb->get_results( 
                "SELECT option_name, option_value 
                FROM {$wpdb->options} 
                WHERE option_name LIKE 'widget_%'",
                OBJECT_K
            );
            
            foreach ( $widget_options as $option_name => $option ) {
                wp_cache_set( $option_name, maybe_unserialize( $option->option_value ), 'options' );
            }
            
            Logger::debug( 'Widget options cached', [
                'count' => count( $widget_options )
            ] );
            
            return $sidebars_widgets;
        }
        
        /**
         * Prevent unnecessary COUNT queries
         */
        public static function prevent_count_queries( $clauses, $query ) {
            // Remove SQL_CALC_FOUND_ROWS from query
            if ( ! is_admin() && $query->is_main_query() ) {
                $clauses['fields'] = str_replace( 'SQL_CALC_FOUND_ROWS', '', $clauses['fields'] );
            }
            
            return $clauses;
        }
        
        /**
         * Optimize post queries
         */
        public static function optimize_post_queries( $request, $query ) {
            if ( is_admin() || ! $query->is_main_query() ) {
                return $request;
            }
            
            // Remove unnecessary ORDER BY on home/archive pages
            if ( ( is_home() || is_archive() ) && strpos( $request, 'ORDER BY' ) !== false ) {
                // Only order by date, not menu_order
                $request = preg_replace( 
                    '/ORDER BY .* (DESC|ASC)/i', 
                    'ORDER BY post_date DESC', 
                    $request 
                );
            }
            
            return $request;
        }
        
        /**
         * Cache expensive JOIN queries
         */
        public static function cache_expensive_joins( $join, $query ) {
            if ( is_admin() || ! $query->is_main_query() ) {
                return $join;
            }
            
            // If this is a taxonomy/category query with JOINs
            if ( ( is_category() || is_tag() || is_tax() ) && ! empty( $join ) ) {
                // This JOIN result can be cached more aggressively
                add_filter( 'posts_results', function( $posts ) {
                    if ( function_exists( 'wp_cache_set' ) ) {
                        $cache_key = 'tax_join_' . md5( serialize( get_queried_object() ) );
                        wp_cache_set( $cache_key, $posts, 'kp_db_joins', 3600 );
                    }
                    return $posts;
                } );
            }
            
            return $join;
        }
    }

}
