<?php
/*
 * REST endpoint: policy / analysis search API for our own Search page
 */

// our special Relevance-calculating WP_Query
require_once 'functions_searchapi_relevancequery.php';


/*
 * URI is /wp-json/data/search
 * Search for Policy and Analysis entries matching the query
 * see the search page (page-search.php et al) to see what generates these queries and consumes these results
 */
function search_api ($data=null) {
    //
    // compose the filtering for Policy posts
    //

    $filter = array(
        'post_type' => 'policy',
		'post_status' => 'publish',
    	'posts_per_page' => 1000000,  // effectively disable pagination
    );

    if (trim(@$_GET['keyword'])) {
        $filter['s'] = trim($_GET['keyword']);
    }

    if (@$_GET['topics']) { // comma-joined term IDs, shorter URLs than slugs (2-4 characters apiece, instead of 5-15), and slightly faster too
    	$filter['tax_query'] = array(
    		array(
    			'taxonomy' => 'policy_topics',
    			'field'    => 'term_id',
    			'operator' => 'IN',
    			'terms'    => explode(',', $_GET['topics']),
    		),
       	);
    }

    if (@$_GET['policytypes']) { // comma-joined strings; these aren't a real taxonomy at all but arbitrary ACF strings, so are strings inswtead of ID#s
        $filter['meta_query'] = array(
            array(
            	'key' => 'policy_type',
                'compare' => 'IN',
            	'value' => explode(',', $_GET['policytypes']),
            )
        );
    }

    //
    // sorting is really variable: some built-in WP, some ACF
    // some a custom WP_Query subclass which creates a relevance score
    //
    if (! @$_GET['orderby']) $_GET['orderby'] = "relevance";
    switch ($_GET['orderby']) {
        case 'relevance':
            $filter['order'] = 'DESC';
            $filter['orderby'] = 'relevance';

            $filter['relevance_scoring'] = array(
                'tax_query' => array(
                    'policy_topics' => 15.0,
                ),
                'title_keyword' => 1.0,
                'content_keyword' => 0.25,
            );

            break;
        case 'title':
            $filter['order'] = 'ASC';
            $filter['orderby'] = 'post_title';
            break;
        case 'pubdate':
            $filter['order'] = 'DESC'; // most recent first
            $filter['orderby'] = 'post_date';
            break;
        case 'enacted':
            $filter['orderby'] = 'meta_value';	
            $filter['meta_key'] = 'date_enacted';
            $filter['order'] = 'DESC'; // recent/future first
            break;
        case 'policytype':
            $filter['orderby'] = 'meta_value';	
            $filter['meta_key'] = 'policy_type';
            $filter['order'] = 'ASC';
            break;
        case 'agencytype':
            $filter['orderby'] = 'meta_value';	
            $filter['meta_key'] = 'agency_type';
            $filter['order'] = 'ASC';
            break;
    }

    //
    // peform the query
    //
    $query = new WP_Query_WithRelevance($filter);

    //
    // construct the list of results to be sent to the client, just the fields we need in the format we want
    //
    $matching_policies = array();

    while ($query->have_posts()) {
        $query->the_post();

        $thisresult = array(
            'id' => get_the_id(),
            'title' => truncate_text(trim(strip_tags(get_the_title())), 75),
            'url' => get_the_permalink(),
            'pubdate' => get_the_date(),
            'date_enacted' => get_field('date_enacted') ? yyyymmdd_to_pretty(get_field('date_enacted')) : '',
            'agency_type' => get_field('agency_type'),
            'policy_type' => get_field('policy_type'),
            'key_policy' => get_field('key_policy'),
        );

        $thisresult['locations'] = array();
        while ( have_rows('locations') ): the_row();
            $point = get_sub_field('location_marker');

            $thisresult['locations'][] = array(
                'name' => get_sub_field('location_name'),
                'lat' => (float) $point['lat'],
                'lng' => (float) $point['lng'],
            );
        endwhile;

        $matching_policies[] = $thisresult;
    }

    //
    // create the final output structure
    //
    return array(
        'results_policy' => $matching_policies,
    );
}

add_action('rest_api_init', function () {
    register_rest_route('data', '/search', array(
        'methods' => 'GET',
        'callback' => 'search_api',
    ));
});
