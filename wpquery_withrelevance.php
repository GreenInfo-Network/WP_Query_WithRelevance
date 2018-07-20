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
    // performs a standard WP_Query but then postprocesses to add relevance, then sort by that relevance
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

        // perform a typical WP_Query
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
                $post->relevance += substr_count($title, $thisword) * $this->DEFAULT_WEIGHTING_TITLE_KEYWORD;
                $post->relevance += substr_count($content, $thisword) * $this->DEFAULT_WEIGHTING_CONTENT_KEYWORD;
            }
		}
    }

    private function score_taxonomy_relevance() {
        if (! $this->query_vars['tax_query']) return;  // no taxo query = skip it

        // taxonomy relevance is only calculated for IN-list operations
        // for other types of queries, all posts match that value and further scoring would be pointless

        // go over each taxo and each post, tag posts with number of matching terms within that taxo
        // this is done one taxo at a time, so we can match terms by ID, by slug, or by name
        // and so we can apply individual weighting by that taxo
		foreach ($this->query_vars['tax_query'] as $taxo) {
            if (strtoupper($taxo['operator']) !== 'IN' or ! is_array($taxo['terms'])) continue; // not a IN-list query, so relevance scoring is not useful for this taxo

            $whichfield = $taxo['field'];
            $wantterms = $taxo['terms'];

            foreach ($this->posts as $post) {
                // find number of terms in common between this post and this taxo's list
                $terms_in_common = 0;
                $thispostterms = get_the_terms($post->ID, $taxo['taxonomy']);

                foreach ($thispostterms as $hasthisterm) {
                    if (in_array($hasthisterm->{$whichfield}, $wantterms)) $terms_in_common += 1;
                }

                // express that terms-in-common as a percentage, and add to this post's relevance score
                $ratio = (float) $terms_in_common / sizeof($wantterms);
                $post->relevance += ($ratio * $ratio * $this->DEFAULT_WEIGHTING_TAXONOMY_RATIO);
            }
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
