<?php

namespace Log1x\Crumb;

use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class Crumb
{
    /**
     * The breadcrumb configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * The breadcrumb items.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $breadcrumb = [];

    /**
     * Initialize the Crumb instance.
     *
     * @param  array $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->breadcrumb = collect();
    }

    /**
     * Add an item to the breadcrumb collection.
     *
     * @param  string $key
     * @param  string $value
     * @param  int    $id
     * @param  bool   $blog
     * @return $this
     */
    protected function add($key, $value = null, $id = null, $blog = false)
    {
        if (
            $blog === true &&
            get_option('show_on_front') === 'page' &&
            !empty($blog = get_option('page_for_posts')) &&
            !empty($this->config['blog'])
        ) {
            $this->add(
                $this->config['blog'],
                get_permalink($blog),
                $blog
            );
        }

        $this->breadcrumb->push([
            'id' => $id,
            'label' => $key,
            'url' => $value,
        ]);

        return $this->breadcrumb;
    }

    protected function term_ancestors($term_id, $taxonomy)
    {
        $ancestors = get_ancestors($term_id, $taxonomy);
        $ancestors = array_reverse($ancestors);

        foreach ($ancestors as $ancestor) {
            $ancestor = get_term($ancestor, $taxonomy);

            if (!is_wp_error($ancestor) && $ancestor) {
                $this->add($ancestor->name, get_term_link($ancestor));
            }
        }
    }

    /**
     * Build the breadcrumb collection.
     *
     * @return \Illuminate\Support\Collection
     */
    public function build()
    {
        if (is_front_page()) {
            return $this->breadcrumb;
        }

        $this->add(
            $this->config['home'],
            home_url()
        );

        $before = apply_filters(('crumb_before'), []);
        if (is_array($before)) {
            foreach ($before as $item) {
                $this->add($item['label'], $item['url']);
            }
        }

        if (
            is_home() &&
            !empty($this->config['blog'])
        ) {
            return $this->add(
                $this->config['blog']
            );
        }

        if (class_exists('woocommerce')) {
            if (is_product_category()) {

                // shop page
                $this->add(
                    get_the_title(wc_get_page_id('shop')),
                    get_permalink(wc_get_page_id('shop')),
                );

                $current_term = $GLOBALS['wp_query']->get_queried_object();
                $this->term_ancestors($current_term->term_id, 'product_cat');
                return $this->add($current_term->name);
            }

            if (is_product()) {

                // shop page
                $this->add(
                    get_the_title(wc_get_page_id('shop')),
                    get_permalink(wc_get_page_id('shop')),
                );

                $terms = wc_get_product_terms(
                    get_the_ID(),
                    'product_cat',
                    apply_filters(
                        'woocommerce_breadcrumb_product_terms_args',
                        array(
                            'orderby' => 'parent',
                            'order' => 'DESC',
                        )
                    )
                );

                if ($terms) {
                    $main_term = apply_filters('woocommerce_breadcrumb_main_term', $terms[0], $terms);
                    $this->term_ancestors($main_term->term_id, 'product_cat');
                    $this->add($main_term->name, get_term_link($main_term));
                }

                return $this->add(
                    get_the_title(),
                    null,
                    get_the_ID()
                );
            }
        }

        if (is_page()) {
            $ancestors = collect(
                get_ancestors(get_the_ID(), 'page')
            )->reverse();

            if ($ancestors->isNotEmpty()) {
                $ancestors->each(function ($item) {
                    $this->add(
                        get_the_title($item),
                        get_permalink($item),
                        $item
                    );
                });
            }

            return $this->add(
                get_the_title(),
                null,
                get_the_ID()
            );
        }

        if (is_category()) {
            $category = single_cat_title('', false);

            return $this->add(
                $category,
                null,
                get_cat_ID($category),
                true
            );
        }

        if (is_tag()) {
            $tag = single_tag_title('', false);

            return $this->add(
                $tag,
                null,
                get_term_by('name', $tag, 'post_tag')->term_id,
                true
            );
        }

        if (is_date()) {
            if (is_month()) {
                return $this->add(
                    get_the_date('F Y'),
                    null,
                    null,
                    true
                );
            }

            if (is_year()) {
                return $this->add(
                    get_the_date('Y'),
                    null,
                    null,
                    true
                );
            }

            return $this->add(
                get_the_date(),
                null,
                null,
                true
            );
        }

        if (is_tax()) {
            $term = single_term_title('', false);

            return $this->add(
                $term,
                null,
                get_term_by('name', $term, get_query_var('taxonomy'))->term_id
            );
        }

        if (is_search()) {
            return $this->add(
                sprintf($this->config['search'], get_search_query())
            );
        }

        if (is_author()) {
            return $this->add(
                sprintf($this->config['author'], get_the_author()),
                null,
                get_the_author_meta('ID'),
                true
            );
        }

        if (is_post_type_archive()) {
            return $this->add(
                post_type_archive_title('', false)
            );
        }

        if (is_404()) {
            return $this->add(
                $this->config['not_found']
            );
        }

        if (is_singular('post')) {
            $categories = get_the_category(get_the_ID());

            if (!empty($categories)) {
                foreach ($categories as $index => $category) {
                    $this->add(
                        $category->name,
                        get_category_link($category),
                        $category->term_id,
                        $index === 0
                    );
                }

                return $this->add(
                    get_the_title(),
                    null,
                    get_the_ID()
                );
            }

            return $this->add(
                get_the_title(),
                null,
                get_the_ID(),
                true
            );
        }

        $type = get_post_type_object(
            get_post_type()
        );

        if (!empty($type)) {
            $this->add(
                $type->label,
                get_post_type_archive_link($type->name),
                get_queried_object_id()
            );
        }

        return $this->add(
            get_the_title(),
            null,
            get_the_ID()
        );
    }
}
