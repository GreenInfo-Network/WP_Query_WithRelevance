# WP_Query_WithRelevance

Wordpress WP_Query subclass which adds "relevance" as a new `orderby` option.

Relevance is calculated by a combination of factors:
* Overlap between the post taxonomies and taxonomies requested in `tax_query` filter
* Frequency of keywords in the post title and content, keywords given by the `s` filter

This may be used with a different `orderby` value in order, in which case it will perform a regular `WP_Query()` with no additional overhead. Therefore, full compatibility is provided for both relevance-sorted queries and "regular" queries.


## Basic Usage

Place the `wpquery_withrelevance.php` file into your file structure, and then load it in your functions.php file. You now have access to `WP_Query_WithRelevance()`

The query takes [typical WP_Query() filters](https://codex.wordpress.org/Class_Reference/WP_Query) and only invokes its special behavior when `orderby = keyword` is given.

```
require_once 'wpquery_withrelevance.php';

$filter = array( // basic filter: published posts
    'post_type' => 'post',
	'post_status' => 'publish',
    'posts_per_page' => 50,
);

$filter['s'] = "Mayonnaise Conspiracy";  // a keyword filter

$filter['tax_query'] = array(  // filter posts having any of these tags
    array(
    	'taxonomy' => 'tags',
    	'field'    => 'slug',
    	'operator' => 'IN',
    	'terms'    => array('sandwiches', 'condiments', 'lunch'),
    ),
);

$filter['meta_query'] = array(  // filter posts having these values for this custom field
    array(
    	'key' => 'manufacturer',
        'compare' => 'IN',
        'value' => aray('Hellmans', 'Best Foods'),
    )
);

if ($_GET['sorting']) == 'best') {
    $filter['orderby'] = "relevance"; // this is the magic word!
}
else {
    $filter['orderby'] = "title"; // but it's also okay to not use relevance, a regular WP_Query() is done
}

$query = new WP_Query_WithRelevance($filter);
```

*If `orderby` is any value except "relevance" then relevance calculation is skipped entirely.* This is effectively the same as running a regular `WP_Query()` and provides compatibility so you don't need to use a different interface, nor suffer a performance hit, if you want to sort by "regular" capabilities.



## Ordering is By Relevance Only

Multi-field sorting is not supported by `WP_Query_WithRelevance()`

The `orderby` field must be exactly the word "relevance" and ordering is done by relevance score only. No additional ordering may be specified.



## Adjusting the Relevance Scoring

The built-in scoring weights should work well for most folks. But, you may fine-tune the relevance scoring by supplying an additional `relevance_scoring` structure. This specifies the weighting value to give to keywords matched in the title, keywords matched in the content body, taxonomies in common, and so forth.

```
$filter['relevance_scoring'] = array(
    // weighting by taxonomy: the built in "tags" taxo plus a custom taxo, with different weights
    'tax_query' => array(
        'tags' => 10.0,
        'authors' => 25.0,
    ),
    // filtering by custom fields (meta fields) with different weights
    'meta_query' => array(
        'manufacturer' => 3.0,
        'flavor' => 5.5,
    ),
    // the points per word occurrence, in post title and content
    'title_keyword' => 1.0,
    'content_keyword' => 0.25,
);
```



## Credits

This was written by [Greg Allensworth](https://github.com/gregallensworth/django) at [GreenInfo Network](https://github.com/GreenInfo-Network/)

This is all-original code, but was inspired and guided by reference to https://github.com/mannieschumpert/wp-relevance-query
