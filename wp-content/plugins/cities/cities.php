<?php

/* **************************************************
 * Plugin Name: Cities
 * Plugin URI: https://github.com/geoffmyers/cities
 * Description: Directory of the 100 most populous cities
 * Version: 1.0
 * Author: SimDex LLC
 * Author URI: https://simdex.org
 * Text Domain: cities
 * Domain Path: /languages
 * ************************************************** */

ini_set('display_errors', 1);

/* **************************************************
 * Constants
 * ************************************************** */

define('GOOGLE_MAPS_API_KEY', 'AIzaSyBpGqY12L7fOL5HXiQGBRl_TEiHP3nhAz4');
define('DEBUG', false);

/* **************************************************
 * Actions
 * ************************************************** */

add_action('wp_enqueue_scripts', 'enqueueStylesScripts');
add_action('pre_get_posts', 'changeCitiesOrder');
add_action('wp_footer', 'showDebug');

/* **************************************************
 * Filters
 * ************************************************** */

add_filter('the_content', 'addCityMeta');
add_filter('the_content', 'addCityMap');

/* **************************************************
 * Shortcodes
 * ************************************************** */

add_shortcode('list_cities', 'listCities');

/* **************************************************
 * Functions
 * ************************************************** */

function enqueueStylesScripts()
{
    wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css');
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.10.19/js/jquery.dataTables.min.js', ['jquery'], '1.10.19', true);

    wp_enqueue_style('cities', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('cities', plugin_dir_url(__FILE__) . 'js/script.js', ['jquery'], '1.0.0', true);
}

function addCityMap($the_content)
{
	global $post;

	if ($post->post_type == 'city')
	{
		$country = get_the_terms($post->ID, 'country')[0]->name;

		$city_map = '
		<iframe class="city-map" src="https://www.google.com/maps/embed/v1/search?q=' . $post->post_title . ' ' . $country . '&zoom=8&key=' . GOOGLE_MAPS_API_KEY . '" allowfullscreen></iframe>
		';

		$the_content = $city_map . $the_content;
	}

	return $the_content;
}

function addCityMeta($the_content)
{
	global $post;

	if ($post->post_type == 'city')
	{
		$country = get_the_terms($post->ID, 'country')[0]->name;
		$definition = get_the_terms($post->ID, 'definition')[0]->name;

		$country_link = get_term_link(get_the_terms($post->ID, 'country')[0]);
		$definition_link = get_term_link(get_the_terms($post->ID, 'definition')[0]);

		$city_meta = '
		<table class="city-meta">
			<tbody>
				<tr class="country">
					<th>Country</th>
					<td><a href="' . $country_link .'"><img alt="' . get_field('country_code') . '" src="https://www.countryflags.io/' . get_field('country_code') . '/flat/32.png"> ' . $country . '</a></td>
				</tr>
				<tr class="definition">
					<th>Definition</th>
					<td><a href="' . $definition_link . '">' . $definition . '</a></td>
				</tr>
				<tr class="rank">
					<th>Rank</th>
					<td>#' . get_field('rank') . '</td>
				</tr>
				<tr class="population">
					<th>Population</th>
					<td>' . get_field('population') . '</td>
				</tr>
				<tr class="total-area">
					<th>Total Area</th>
					<td>' . get_field('total_area') . ' km<sup>2</sup></td>
				</tr>
				<tr class="population-density">
					<th>Population Density</th>
					<td>' . get_field('population_density') . ' per km<sup>2</sup></td>
				</tr>
				<tr class="elevation">
					<th>Elevation</th>
					<td>' . get_field('elevation') . ' m</td>
				</tr>
				<tr class="image-url">
					<th>Image</th>
					<td><a target="_blank" href="' . get_field('image_url') . '">View Image</a></td>
				</tr>
			</tbody>
		</table>
		';

		$the_content = $city_meta . $the_content;
	}

	return $the_content;
}

function listCities()
{
	$arguments =
	[
		'post_type'      => 'city',
		'order'          => 'ASC',
		'orderby'        => 'meta_value_num',
		'meta_key'       => 'rank',
		'posts_per_page' => -1
	];

	$query = new WP_Query($arguments);

	if ($query->have_posts())
	{
	    $html = '
		<table class="cities">
		    <thead>
		        <tr>
		        	<th>City</th>
		        	<th>Country</th>
		        	<th>Defintion</th>
		        	<th>Rank (#)</th>
		        	<th>Population</th>
		        	<th>Total Area (km<sup>2</sup>)</th>
		        	<th>Population Density (per km<sup>2</sup>)</th>
		        	<th>Elevation (m)</th>
		        	<th>Image</th>
		        </tr>
		    </thead>
		    <tbody>
		';

	    while ($query->have_posts())
	    {
	        $query->the_post();

	        $country = get_the_terms($post->ID, 'country')[0]->name;
			$definition = get_the_terms($post->ID, 'definition')[0]->name;

			$country_link = get_term_link(get_the_terms($post->ID, 'country')[0]);
			$definition_link = get_term_link(get_the_terms($post->ID, 'definition')[0]);

	        $html .= '
	        <tr>
	        	<td class="city"><a href="' . get_permalink() . '">' . get_the_title() . '</a></td>
	        	<td class="country"><a href="' . $country_link . '"><img alt="' . get_field('country_code') . '" src="https://www.countryflags.io/' . get_field('country_code') . '/flat/32.png"> ' . $country . '</a></td>
	        	<td class="definition"><a href="' . $definition_link . '">' . $definition . '</a></td>
	        	<td class="rank">' . get_field('rank') . '</td>
	        	<td class="population">' . get_field('population') . '</td>
	        	<td class="total-area">' . get_field('total_area') . '</td>
	        	<td class="population-density">' . get_field('population_density') . '</td>
	        	<td class="elevation">' . get_field('elevation') . '</td>
	        	<td class="image-url"><a target="_blank" href="' . get_field('image_url') . '">View Image</a></td>
        	</tr>
	        ';
	    }

	    $html .= '
	    	</tbody>
	    </table>
	    ';

	    wp_reset_postdata();
	}
	else
	{
		$html = '<p>Sorry, no cities were found.</p>';
	}

	return $html;
}

function changeCitiesOrder($query)
{
	if (is_tax('country') || is_tax('definition'))
	{
		$query->set('order', 'ASC');
		$query->set('orderby', 'meta_value_num');
		$query->set('meta_key', 'rank');
	}

	return;
}

function showDebug()
{
	if (isset($_GET['debug']) || DEBUG)
	{
		global $wp_query;
		global $post;

		echo '<div class="debug">';
		echo '<h2>Debug</h2>';

		echo '<h3>WP_Query Object</h3>';
		echo '<pre>';
		print_r($wp_query);
		echo '</pre>';

		echo '<h3>WP_Post Object</h3>';
		echo '<pre>';
		print_r($post);
		echo '</pre>';

		echo '<h3>Post Terms (Country)</h3>';
		echo '<pre>';
		print_r(get_the_terms($post->ID, 'country'));
		echo '</pre>';	

		echo '<h3>Post Terms (Definition)</h3>';
		echo '<pre>';
		print_r(get_the_terms($post->ID, 'definition'));
		echo '</pre>';

		echo '<h3>Post Meta</h3>';
		echo '<pre>';
		print_r(get_post_meta($post->ID));
		echo '</pre>';

		echo '</div>';
	}
}