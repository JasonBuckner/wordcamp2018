/* **************************************************
 * Plugin Name: Cities
 * Plugin URI: https://apps.simdex.org/cities
 * Description: Directory of cities
 * Version: 1.0
 * Author: SimDex LLC
 * Author URI: https://simdex.org
 * Text Domain: cities
 * Domain Path: /languages
 * ************************************************** */
 
jQuery(document).ready(
	function ()
	{
    	jQuery('.cities').DataTable({
			'order': [[3, 'asc']]
		});
	}
);