<?php
//
// a WP_Query subclass which adds a Relevance score to 
//
//
if (! defined( 'WPINC')) die;

class WP_Query_WithRelevance extends WP_Query {
    //
    // search field DEFAULT weights
    // the $args passed to this Query may/should specify weightings for specific taxonomies, meta keys, etc.
    // but these act as defaults
    //
    var $DEFAULT_WEIGHTING_TITLE_KEYWORD = 1.0;
    var $DEFAULT_WEIGHTING_CONTENT_KEYWORD = 0.25;
    var $DEFAULT_WEIGHTING_TAXONOMY_RATIO = 15.0;
    var $DEFAULT_WEIGHTING_METAKEY_RATIO = 10.0;

    //
    // constructor
    // performs a standard WP_Query but then postprocesses to add relevance, then sort by it
    //
	public function __construct($args = array()) {
        // stow and unset the orderby param
        // cuz it's not a real DB field that can be used
		if ($args['orderby'] === 'relevance') {
			$this->orderby = $args['orderby'];
            $this->order = 'DESC';
			unset($args['orderby']);
			unset($args['order']);
		}

        // perform a tpyical WP_Query
        // then if we weren't using a relevance sorting, we're actually done
		$this->process_args($args);
		parent::__construct($args);
        if (! $this->orderby) return;

        // okay, we're doing relevance postprocessing
        $this->initialize_relevance_scores();
        $this->score_keyword_relevance();
        $this->score_taxonomy_relevance();
        $this->score_metakey_relevance();
        $this->orderby_relevance();

        // debugging; you can display this at any time to just dump the list of results
        //$this->display_results_so_far();
	}

    // initializing all posts' relevance scores to 0
    private function initialize_relevance_scores() {
		foreach ($this->posts as $post) {
			$post->relevance = 0;
		}
    }

    private function score_keyword_relevance() {
        if (! $this->query_vars['s']) return; // no keyword string = this is a noop

        $words = strtoupper(trim($this->query_vars['s']));
        $words = preg_split('/\s+/', $words);

		foreach ($this->posts as $post) {
			$title = strtoupper($post->post_title);
            $content = strtoupper($post->post_content);

            foreach ($words as $thisword) {
                $post->relevance += substr_count($title, $thisword) * $this->WEIGHTING_TITLE_KEYWORD;
                $post->relevance += substr_count($content, $thisword) * $this->WEIGHTING_CONTENT_KEYWORD;
            }
		}
    }

    private function score_taxonomy_relevance() {
        if (! $this->query_vars['tax_query']) return;  // no taxo query = skip it

        // $queried_terms = taxonomy => list of taxo-term IDs, the taxos that were queried
        // $total_terms = total number of terms queried, between all taxos in $queried_terms
        $queried_term_ids = array();
		foreach ($this->query_vars['tax_query'] as $taxo) {
            foreach ($taxo['terms'] as $termid) {
                $queried_term_ids[] = $termid;
            }
        }
// GDA TODO: the above is applicable only for compare=IN so it's a list of IDs; skip any with compare!=IN

// GDA TODO
        // if we collected 0 $queried_term_ids then we have nothing to do
        // all posts match/dontmatch the one given value, and further scoring is meaningless

        // go through our posts, and find the number of terms it has in common with our query
        // score = square percentage of those requested * weighting constant
        foreach ($this->posts as $post) {
            $tagged = 0;
            foreach ($this->query_vars['tax_query'] as $taxo) {
                foreach (get_the_terms($post->ID, $taxo['taxonomy']) as $hasthisterm) {
// GDA TODO: this presumes that tax_query was ID#s and not slugs; a simple OR would cover both situations
                    if (in_array($hasthisterm->term_id, $queried_term_ids)) {
                        $tagged += 1;
                    }
                }
            }

            $ratio = (float) $tagged / sizeof($queried_term_ids);
            $post->relevance += ($ratio * $ratio * $this->WEIGHTING_TAXONOMY_RATIO);
		}
	}

    private function score_metakey_relevance() {
        if (! $this->query_vars['meta_query']) return;  // no taxo query = skip it

        //GDA TODO: this is noop right now, catch up on other improvements first
    }

    private function orderby_relevance() {
        usort($this->posts, array($this, 'usort_sorting'));
    }

    private function display_results_so_far () {  // for debugging
        foreach ($this->posts as $post) {
            printf('%d %s = %.1f' . "\n", $post->ID, $post->post_title, $post->relevance) . "\n";
        }
    }

    private function usort_sorting ($p, $q) {
        // we force DESC and only trigger if orderby==='relevance' so we can keep this simple
        if ($p->relevance == $q->relevance) return 0;
        return $p->relevance > $q->relevance ? -1 : 1;
    }
}
